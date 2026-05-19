<?php

namespace App\Http\Controllers\Api;

use App\Events\SessionLiveEvent;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceDeletion;
use App\Models\AttendanceSession;
use App\Models\Course;
use App\Models\Student;
use App\Models\SystemSetting;
use App\Services\AttendanceOfflineSyncService;
use App\Services\MissedSessionWarningService;
use App\Support\SecureQrToken;
use App\Support\StudentApiPayload;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AttendanceController extends Controller
{
    private const LATE_MINUTES_THRESHOLD = 20;
    /**
     * POST /api/attendance — record attendance (server-side geofence, duplicate check, etc.).
     *
     * Mode `location` / `hybrid`: venue is set when the session opens; optional client lat/lng enforces geofence when sent.
     * Mode `qr` / `hybrid`: QR token for students (not class reps). Mode `wifi`: SSID is set on the session by the class rep.
     */
    public function markAttendance(Request $request): JsonResponse
    {
        $payload = $this->normalizeAttendanceRequestPayload($request->all());

        $settingsEarly = SystemSetting::get();
        if (! ($settingsEarly->enable_face_verification ?? false)) {
            unset($payload['face_descriptor']);
        }

        $validated = Validator::make($payload, [
            'index_number' => 'required|string',
            // Integer PK from /api/sessions/active `course_id` (not course code).
            'course_id' => ['nullable', 'integer', Rule::exists('courses', 'id')],
            'session_id' => 'nullable',
            // Ignored for marking (server uses session’s week); do not validate exists — avoids Flutter junk values.
            'week_id' => 'nullable|integer',
            'lat' => 'nullable|numeric',
            'lng' => 'nullable|numeric',
            // Aliases merged in normalizeAttendanceRequestPayload before validation.
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            // Reported horizontal accuracy (meters), e.g. Geolocator on Flutter — widens check slightly.
            'accuracy' => 'nullable|numeric|min:0|max:2000',
            'horizontal_accuracy' => 'nullable|numeric|min:0|max:2000',
            'qr_code' => 'nullable|string',
            'qr_sig' => 'nullable|string',
            'qr_t' => 'nullable|integer',
            'timestamp' => 'nullable|string',
            'device_ip' => 'nullable|string|max:45',
            'device_id' => 'nullable|string|max:128',
            'wifi_ssid' => 'nullable|string|max:128',
            'session_code' => 'nullable|string|max:48',
        ], [
            'course_id.exists' => 'Invalid course_id. Use the numeric course_id from GET /api/sessions/active for this session.',
            'course_id.integer' => 'course_id must be the numeric database id (integer), not the course code.',
        ])->validate();

        $settings = $settingsEarly;
        $ip = $request->ip();

        $indexUpper = strtoupper(trim($validated['index_number']));
        $student = Student::whereRaw('UPPER(TRIM(index_number)) = ?', [$indexUpper])->first();
        if (!$student) {
            return response()->json(['message' => 'Student not found'], 404);
        }

        if ($settings->require_password_on_first_login ?? true) {
            if (empty($student->password)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Set password first',
                    'password_required' => true,
                ], 422);
            }
        }

        if ($settings->enable_ip_binding && $student->bound_ip && $student->bound_ip !== $ip) {
            return response()->json(['status' => 'error', 'message' => 'Device mismatch. Contact admin.'], 403);
        }

        if ($settings->enable_ip_binding && !$settings->allow_multiple_index_on_device && $student->bound_ip) {
            $other = Student::where('bound_ip', $ip)->where('id', '!=', $student->id)->first();
            if ($other && $other->index_number !== $student->index_number) {
                return response()->json(['status' => 'error', 'message' => 'This device is linked to another student.'], 403);
            }
        }

        $course = null;
        if (!empty($validated['course_id'])) {
            $course = Course::find($validated['course_id']);
        }

        $sessionToken = isset($validated['qr_code']) && (string) $validated['qr_code'] !== ''
            ? trim((string) $validated['qr_code'])
            : null;
        if ($sessionToken === null && isset($validated['session_id']) && ! ctype_digit((string) $validated['session_id'])) {
            $sessionToken = trim((string) $validated['session_id']);
        }
        if ($sessionToken === '') {
            $sessionToken = null;
        }

        $hasCourse = !empty($validated['course_id']);
        $hasSession = isset($validated['session_id']) && $validated['session_id'] !== '';
        $hasQrToken = $sessionToken !== null;
        $hasSessionCode = isset($validated['session_code']) && trim((string) $validated['session_code']) !== '';

        if (! $hasCourse && ! $hasSession && ! $hasQrToken && ! $hasSessionCode) {
            return response()->json([
                'message' => 'Send course_id, numeric session_id, qr_code (scan), or session_code (manual entry).',
            ], 422);
        }

        $session = $this->resolveSession($validated, $course, $sessionToken, $student);

        if (! $session) {
            return response()->json(['message' => 'Session not found'], 404);
        }

        $course = $session->course ?? Course::find($session->course_id);
        if (! $course) {
            return response()->json(['message' => 'Course not found'], 404);
        }

        if ($student->isClassRepForCourse((int) $course->id)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Class reps are auto-marked and cannot mark attendance manually.',
            ], 403);
        }
        if (! $session->isValid()) {
            return response()->json(['status' => 'error', 'message' => 'Session closed or expired'], 422);
        }

        $supplementalRepMark = false;

        if ($student->class_id && ! $course->studentMayAttend($student)) {
            return response()->json(['status' => 'error', 'message' => 'Course not for your class'], 403);
        }

        $latitude = isset($validated['lat']) ? (float) $validated['lat'] : null;
        $longitude = isset($validated['lng']) ? (float) $validated['lng'] : null;

        $accuracyMeters = null;
        if (isset($validated['accuracy'])) {
            $accuracyMeters = (float) $validated['accuracy'];
        } elseif (isset($validated['horizontal_accuracy'])) {
            $accuracyMeters = (float) $validated['horizontal_accuracy'];
        }

        if ($session->requiresQrProof() && !($settings->enable_qr ?? true)) {
            return response()->json(['status' => 'error', 'message' => 'QR attendance is disabled'], 422);
        }

        if ($session->requiresQrProof()) {
            $submitted = trim((string) (
                $request->input('qr_code')
                ?? $request->input('session_token')
                ?? ($validated['qr_code'] ?? '')
                ?? ''
            ));
            $manualCode = trim((string) ($validated['session_code'] ?? ''));
            $codeOk = $manualCode !== ''
                && strcasecmp($manualCode, (string) ($session->session_code ?? '')) === 0;

            if (! $codeOk && ($submitted === '' || ! SecureQrToken::isValidSubmission($submitted, $session))) {
                return response()->json(['message' => 'Invalid QR or session code'], 403);
            }
            $pk = $request->input('session_id');
            if ($pk !== null && $pk !== '' && ctype_digit((string) $pk) && (int) $pk !== (int) $session->id) {
                return response()->json(['message' => 'Invalid QR or session code'], 403);
            }
        }

        if (! $supplementalRepMark) {
            $geofenceResponse = $this->validateSessionGeofence($session, $course, $latitude, $longitude, $accuracyMeters);
            if ($geofenceResponse !== null) {
                return $geofenceResponse;
            }

            $wifiResponse = $this->validateWifiSsidForSession($session, $validated['wifi_ssid'] ?? null);
            if ($wifiResponse !== null) {
                return $wifiResponse;
            }
        }

        $attendanceTime = isset($validated['timestamp'])
            ? Carbon::parse($validated['timestamp'])
            : now();

        $windowMinutes = $course->attendance_window_minutes ?? 60;
        if (! $supplementalRepMark && $attendanceTime->diffInMinutes(now()) > $windowMinutes) {
            return response()->json(['status' => 'error', 'message' => 'Attendance time outside allowed window'], 422);
        }

        $deviceId = $validated['device_id'] ?? null;
        $deviceIp = $validated['device_ip'] ?? $ip;

        $existing = Attendance::where('student_id', $student->id)
            ->where('attendance_session_id', $session->id)
            ->first();

        if ($session->isCheckInCheckoutMode()) {
            if ($existing) {
                return response()->json([
                    'message' => $existing->check_out_time
                        ? 'Attendance already completed'
                        : 'Already checked in. Wait for checkout to open.',
                    'checkout_enabled' => (bool) $session->checkout_enabled,
                ], 200);
            }

            $checkInAt = $attendanceTime;
            $lateBoundary = ($session->start_time ?? $session->created_at ?? now())
                ->copy()
                ->addMinutes(self::LATE_MINUTES_THRESHOLD);
            $status = $checkInAt->greaterThan($lateBoundary) ? 'late' : 'present';

            Attendance::create([
                'student_id' => $student->id,
                'course_id' => $course->id,
                'attendance_session_id' => $session->id,
                'attendance_week_id' => $session->attendance_week_id,
                'attendance_time' => $checkInAt,
                'check_in_time' => $checkInAt,
                'status' => $status,
                'synced' => true,
                'lat' => $latitude,
                'lng' => $longitude,
                'qr_code' => $request->input('qr_code') ?? $request->input('session_token') ?? $validated['qr_code'] ?? null,
                'device_ip' => $deviceIp,
                'device_id' => $deviceId,
            ]);

            return response()->json([
                'message' => 'Check-in recorded successfully',
                'status' => 'success',
                'attendance_status' => $status,
                'checkout_enabled' => (bool) $session->checkout_enabled,
            ]);
        }

        if ($existing) {
            $existingDeviceId = $existing->device_id;
            if ($existingDeviceId !== null && $deviceId !== null && $existingDeviceId !== $deviceId) {
                return response()->json([
                    'message' => 'Already marked from a different device',
                ], 403);
            }
            return response()->json(['message' => 'Already marked'], 200);
        }

        Attendance::create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'attendance_session_id' => $session->id,
            'attendance_week_id' => $session->attendance_week_id,
            'attendance_time' => $attendanceTime,
            'status' => 'present',
            'synced' => true,
            'lat' => $latitude,
            'lng' => $longitude,
            'qr_code' => $request->input('qr_code') ?? $request->input('session_token') ?? $validated['qr_code'] ?? null,
            'device_ip' => $deviceIp,
            'device_id' => $deviceId,
        ]);

        $presentCount = Attendance::where('attendance_session_id', $session->id)
            ->where('status', 'present')
            ->count();
        event(new SessionLiveEvent($session->fresh(['course']), 'attendance_marked', ['present_count' => $presentCount]));

        return response()->json([
            'message' => 'Attendance recorded successfully',
            'status' => 'success',
        ]);
    }

    /**
     * POST /api/attendance/checkout — finalize a check-in/check-out attendance session.
     */
    public function checkout(Request $request): JsonResponse
    {
        $payload = $this->normalizeAttendanceRequestPayload($request->all());
        $validated = Validator::make($payload, [
            'index_number' => 'required|string',
            'course_id' => ['nullable', 'integer', Rule::exists('courses', 'id')],
            'session_id' => 'nullable',
            'qr_code' => 'nullable|string',
            'session_code' => 'nullable|string|max:48',
            'lat' => 'nullable|numeric',
            'lng' => 'nullable|numeric',
            // Offline queue can submit original checkout time when back online.
            'timestamp' => 'nullable|string',
        ])->validate();

        $indexUpper = strtoupper(trim($validated['index_number']));
        $student = Student::whereRaw('UPPER(TRIM(index_number)) = ?', [$indexUpper])->first();
        if (! $student) {
            return response()->json(['message' => 'Student not found'], 404);
        }

        $course = !empty($validated['course_id']) ? Course::find($validated['course_id']) : null;
        $sessionToken = isset($validated['qr_code']) && (string) $validated['qr_code'] !== ''
            ? trim((string) $validated['qr_code'])
            : null;
        $session = $this->resolveSession($validated, $course, $sessionToken, $student);
        if (! $session) {
            return response()->json(['message' => 'Session not found'], 404);
        }
        if (! $session->isCheckInCheckoutMode()) {
            return response()->json(['message' => 'Checkout is available only in check-in/check-out mode'], 422);
        }

        $course = $session->course ?? Course::find($session->course_id);
        if (! $course) {
            return response()->json(['message' => 'Course not found'], 404);
        }

        $lat = isset($validated['lat']) ? (float) $validated['lat'] : null;
        $lng = isset($validated['lng']) ? (float) $validated['lng'] : null;
        $outsideRadius = $this->isOutsideSessionRadius($session, $course, $lat, $lng);

        $row = Attendance::query()
            ->where('student_id', $student->id)
            ->where('attendance_session_id', $session->id)
            ->first();

        if (! $row || $row->check_in_time === null) {
            return response()->json([
                'message' => 'Check in before checking out.',
            ], 422);
        }

        $checkoutAllowed = $session->checkout_enabled || ! $session->isValid();
        if (! $checkoutAllowed) {
            return response()->json(['message' => 'Checkout is not enabled yet'], 422);
        }

        $checkoutAt = isset($validated['timestamp']) && $validated['timestamp'] !== ''
            ? Carbon::parse($validated['timestamp'])
            : now();
        $now = now();
        // Guard against future client clocks when syncing queued checkout rows.
        if ($checkoutAt->greaterThan($now->copy()->addMinutes(5))) {
            $checkoutAt = $now->copy();
        }

        if ($row->check_out_time !== null) {
            return response()->json([
                'message' => 'Already checked out',
                'status' => 'success',
                'attendance_status' => $row->status,
            ], 200);
        }

        $finalStatus = $outsideRadius ? 'late' : ($row->status ?: 'present');
        $timeSpent = null;
        if ($row->check_in_time !== null) {
            $checkInAt = Carbon::parse($row->check_in_time);
            if ($checkoutAt->lessThan($checkInAt)) {
                $checkoutAt = $checkInAt->copy();
            }
            $timeSpent = max(0, $checkoutAt->diffInSeconds($checkInAt));
        }

        $row->update([
            'check_out_time' => $checkoutAt,
            'status' => $finalStatus,
            'time_spent_seconds' => $timeSpent,
            'lat' => $lat ?? $row->lat,
            'lng' => $lng ?? $row->lng,
        ]);

        return response()->json([
            'message' => 'Checkout recorded successfully',
            'status' => 'success',
            'attendance_status' => $finalStatus,
            'time_spent_seconds' => $timeSpent,
        ]);
    }

    /**
     * GET /api/attendance/sync — all attendance rows for the student + deleted IDs for SQLite tombstones.
     *
     * Query: index_number, password, since (optional ISO date — limits deleted_ids to after this time).
     */
    public function sync(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'index_number' => 'required|string',
            'password' => 'required|string',
            'since' => 'nullable|date',
        ]);

        $student = Student::findByIndex($validated['index_number']);
        if (!$student || !$this->validatePasswordForSync($validated['password'], $student->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $since = isset($validated['since']) && $validated['since'] !== ''
            ? Carbon::parse($validated['since'])
            : null;

        $attendances = Attendance::where('student_id', $student->id)
            ->with(['attendanceWeek', 'attendanceSession'])
            ->orderBy('id')
            ->get()
            ->map(fn (Attendance $a) => $this->formatAttendanceRow($a));

        $deletedQuery = AttendanceDeletion::where('student_id', $student->id);
        if ($since) {
            $deletedQuery->where('deleted_at', '>', $since);
        }
        $deletedIds = $deletedQuery->pluck('attendance_id')->unique()->values()->all();

        return response()->json(array_merge([
            'attendances' => $attendances,
            'deleted_ids' => $deletedIds,
        ], StudentApiPayload::attendanceSyncMeta()));
    }

    /**
     * POST /api/attendance/sync — same validation as web offline sync (QR signature, window, session).
     *
     * @body records[].index_number, records[].course_id, records[].attendance_time, records[].session_token, ...
     */
    public function syncPush(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'records' => 'required|array',
            'records.*.index_number' => 'required|string',
            'records.*.course_id' => 'required|exists:courses,id',
            'records.*.latitude' => 'nullable|numeric',
            'records.*.longitude' => 'nullable|numeric',
            'records.*.session_token' => 'nullable|string',
            'records.*.qr_sig' => 'nullable|string',
            'records.*.qr_t' => 'nullable|integer',
            'records.*.wifi_ssid' => 'nullable|string|max:128',
            'records.*.attendance_time' => 'required|string',
        ]);

        $result = AttendanceOfflineSyncService::process($validated['records']);

        return response()->json([
            'success' => true,
            'synced' => $result['synced'],
            'failed' => $result['failed'],
        ]);
    }

    /**
     * GET /api/attendance/missed-warnings — courses where the student has ≥ N missed ended sessions.
     *
     * Query: index_number, password (required). Optional: min_missed (default from config), lookback_days.
     */
    public function missedWarnings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'index_number' => 'required|string',
            'password' => 'required|string',
            'min_missed' => 'nullable|integer|min:1|max:999',
            'lookback_days' => 'nullable|integer|min:1|max:3650',
        ]);

        $student = Student::findByIndex($validated['index_number']);
        if (! $student || ! $this->validatePasswordForSync($validated['password'], $student->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $minMissed = isset($validated['min_missed']) ? (int) $validated['min_missed'] : null;
        $lookback = isset($validated['lookback_days']) ? (int) $validated['lookback_days'] : null;

        $payload = MissedSessionWarningService::buildPayload($student, $minMissed, $lookback);

        return response()->json([
            'warnings' => $payload['warnings'],
            'warnings_map' => $payload['warnings_map'],
        ]);
    }

    private function formatAttendanceRow(Attendance $a): array
    {
        return [
            'id' => $a->id,
            'student_id' => $a->student_id,
            'course_id' => $a->course_id,
            'attendance_session_id' => $a->attendance_session_id,
            'session_index' => $a->attendanceSession?->session_index,
            'week_number' => $a->attendanceWeek?->week_number,
            'attendance_week_id' => $a->attendance_week_id,
            'attendance_time' => $a->attendance_time?->toIso8601String(),
            'status' => $a->status,
            'synced' => (bool) $a->synced,
            'lat' => $a->lat !== null ? (float) $a->lat : null,
            'lng' => $a->lng !== null ? (float) $a->lng : null,
            'qr_code' => $a->qr_code,
            'device_ip' => $a->device_ip,
            'created_at' => $a->created_at?->toIso8601String(),
            'updated_at' => $a->updated_at?->toIso8601String(),
        ];
    }

    private function validatePasswordForSync(string $input, ?string $stored): bool
    {
        if (empty($stored)) {
            return false;
        }
        if (str_starts_with($stored, '$2y$') || str_starts_with($stored, '$2a$')) {
            return Hash::check($input, $stored);
        }

        return $input === $stored;
    }

    /**
     * Resolve session: numeric id (Flutter), or token + course, or active session for course.
     */
    /**
     * Server-side geofence (Haversine). Cannot be bypassed by omitting client checks.
     */
    private function validateSessionGeofence(
        AttendanceSession $session,
        Course $course,
        ?float $lat,
        ?float $lng,
        ?float $horizontalAccuracyMeters = null
    ): ?JsonResponse {
        if (!$session->requiresLocation()) {
            return null;
        }

        if (!$session->hasLocation()) {
            return response()->json([
                'message' => 'Session has no location set; attendance cannot be verified',
            ], 422);
        }

        // Venue is set when the session opens; student GPS is optional. If lat/lng are sent, enforce geofence.
        if ($lat === null || $lng === null) {
            return null;
        }

        $allowedMeters = $session->allowedGeofenceRadiusMeters($course);

        $distanceMeters = $this->haversineDistanceMeters(
            (float) $session->location_lat,
            (float) $session->location_lng,
            $lat,
            $lng
        );

        // Align with typical mobile UI: "in range" if the uncertainty circle can overlap the geofence
        // (distance <= R + accuracy), capped to limit abuse if a client sends a fake huge accuracy.
        $cap = (int) config('app.geofence_accuracy_slack_cap_m', 120);
        $slack = $horizontalAccuracyMeters !== null
            ? min($cap, max(0.0, $horizontalAccuracyMeters))
            : 0.0;
        $effectiveLimit = $allowedMeters + $slack;

        if ($distanceMeters > $effectiveLimit) {
            return response()->json([
                'message' => 'Out of range',
                'distance_meters' => (int) round($distanceMeters),
                'allowed_meters' => $allowedMeters,
                'accuracy_slack_meters' => (int) round($slack),
                'effective_limit_meters' => (int) round($effectiveLimit),
            ], 403);
        }

        return null;
    }

    /**
     * Wi‑Fi mode: student must report the same SSID the rep configured (no GPS).
     */
    private function validateWifiSsidForSession(AttendanceSession $session, mixed $clientSsid): ?JsonResponse
    {
        if (! $session->requiresWifiSsidProof()) {
            return null;
        }

        $expected = trim((string) ($session->allowed_wifi_ssid ?? ''));
        if ($expected === '') {
            return response()->json([
                'message' => 'This Wi‑Fi session is not configured on the server (missing expected SSID).',
            ], 422);
        }

        $got = trim((string) ($clientSsid ?? ''));
        if ($got !== '' && strcasecmp($got, $expected) !== 0) {
            return response()->json([
                'message' => 'Not on the required Wi‑Fi network',
                'expected_ssid' => $expected,
            ], 403);
        }

        return null;
    }

    /**
     * Haversine formula — great-circle distance in meters.
     */
    private function haversineDistanceMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $R = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLng / 2) * sin($dLng / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $R * $c;
    }

    private function isOutsideSessionRadius(
        AttendanceSession $session,
        Course $course,
        ?float $lat,
        ?float $lng
    ): bool {
        if (! $session->requiresLocation() || ! $session->hasLocation()) {
            return false;
        }
        if ($lat === null || $lng === null) {
            return true;
        }
        $distanceMeters = $this->haversineDistanceMeters(
            (float) $session->location_lat,
            (float) $session->location_lng,
            $lat,
            $lng
        );
        $allowedMeters = $session->allowedGeofenceRadiusMeters($course);
        return $distanceMeters > $allowedMeters;
    }

    private function resolveSession(array $validated, ?Course $course, ?string $sessionToken, Student $student): ?AttendanceSession
    {
        $code = isset($validated['session_code']) ? trim((string) $validated['session_code']) : '';
        if ($code !== '') {
            $byCode = AttendanceSession::with('course')
                ->where('session_code', $code)
                ->first();
            if ($byCode === null) {
                $byCode = AttendanceSession::with('course')
                    ->whereRaw('LOWER(session_code) = ?', [strtolower($code)])
                    ->first();
            }
            if ($byCode && $byCode->isValid()) {
                if ($course && (int) $byCode->course_id !== (int) $course->id) {
                    return null;
                }

                return $byCode;
            }

            return null;
        }

        $rawSessionId = $validated['session_id'] ?? null;

        if ($rawSessionId !== null && $rawSessionId !== '' && ctype_digit((string) $rawSessionId)) {
            $session = AttendanceSession::with('course')->find((int) $rawSessionId);
            if ($session && $course && (int) $session->course_id !== (int) $course->id) {
                return null;
            }

            return $session;
        }

        /**
         * Scan-only / Flutter: often sends qr_token with no course_id. Resolve globally by token.
         */
        if (! $course && $sessionToken) {
            return AttendanceSession::findActiveGloballyByQrOrSessionToken($sessionToken);
        }

        if ($course) {
            $isRep = $student->isClassRepForCourse((int) $course->id);

            return AttendanceSession::resolveForMarking($course, $sessionToken, null, $isRep);
        }

        return null;
    }

    /**
     * Coerce JSON quirks: empty strings, numeric strings, string "null" → proper null / int.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function normalizeAttendanceRequestPayload(array $input): array
    {
        foreach (['course_id', 'week_id'] as $key) {
            if (! array_key_exists($key, $input)) {
                continue;
            }
            $v = $input[$key];
            if ($v === null || $v === '' || $v === 'null') {
                $input[$key] = null;

                continue;
            }
            if (is_string($v) && is_numeric($v)) {
                $input[$key] = str_contains((string) $v, '.')
                    ? (int) floor((float) $v)
                    : (int) $v;
            }
        }

        foreach (['lat', 'lng', 'latitude', 'longitude', 'accuracy', 'horizontal_accuracy'] as $coordKey) {
            if (! array_key_exists($coordKey, $input)) {
                continue;
            }
            $v = $input[$coordKey];
            if ($v === '' || $v === 'null') {
                $input[$coordKey] = null;
            }
        }

        // Flutter / many HTTP clients send latitude & longitude; this API historically used lat & lng only.
        if (! isset($input['lat']) || $input['lat'] === null) {
            if (isset($input['latitude']) && $input['latitude'] !== null && $input['latitude'] !== '') {
                $input['lat'] = $input['latitude'];
            }
        }
        if (! isset($input['lng']) || $input['lng'] === null) {
            if (isset($input['longitude']) && $input['longitude'] !== null && $input['longitude'] !== '') {
                $input['lng'] = $input['longitude'];
            }
        }

        if (! isset($input['accuracy']) || $input['accuracy'] === null) {
            if (isset($input['horizontal_accuracy']) && $input['horizontal_accuracy'] !== null && $input['horizontal_accuracy'] !== '') {
                $input['accuracy'] = $input['horizontal_accuracy'];
            }
        }

        if (array_key_exists('wifi_ssid', $input) && is_string($input['wifi_ssid'])) {
            $input['wifi_ssid'] = trim($input['wifi_ssid']);
        }

        if (array_key_exists('session_code', $input)) {
            $sc = $input['session_code'];
            $input['session_code'] = is_string($sc) ? trim($sc) : trim((string) ($sc ?? ''));
        }

        return $input;
    }

}
