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
use App\Support\AttendanceLocation;
use App\Support\PasswordPolicy;
use App\Support\PostMarkAutoLogout;
use App\Support\SecureQrToken;
use App\Support\SessionFloorAnchor;
use App\Support\StudentApiPayload;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
            'client_meta' => 'nullable',
            // Client-generated idempotency key. Format is informal — anything
            // 8-64 chars works (we recommend `att_<crockford32>`). Same value
            // can be replayed safely; a duplicate hits the unique index and
            // collapses to "already_marked".
            'attendance_uuid' => 'nullable|string|min:8|max:64|regex:/^[A-Za-z0-9._-]+$/',
        ], [
            'course_id.exists' => 'Invalid course_id. Use the numeric course_id from GET /api/sessions/active for this session.',
            'course_id.integer' => 'course_id must be the numeric database id (integer), not the course code.',
        ])->validate();

        // Idempotency short-circuit. If the client sent an attendance_uuid
        // and we already accepted it before, return the exact same shape
        // we returned the first time, so the mobile outbox can mark the
        // row Synced without any side-effects.
        $attendanceUuid = isset($validated['attendance_uuid'])
            ? trim((string) $validated['attendance_uuid'])
            : '';
        if ($attendanceUuid !== '' && \App\Support\SchemaFeatures::hasAttendanceUuid()) {
            $existingByUuid = \App\Models\Attendance::query()
                ->where('attendance_uuid', $attendanceUuid)
                ->orderByDesc('id')
                ->first();
            if ($existingByUuid !== null) {
                return response()->json([
                    'status' => 'already_marked',
                    'already_marked' => true,
                    'attendance_uuid' => $attendanceUuid,
                    'attendance_id' => (int) $existingByUuid->id,
                    'message' => 'Attendance already recorded.',
                ], 200);
            }
        }

        $settings = $settingsEarly;
        $ip = $request->ip();

        $indexUpper = strtoupper(trim($validated['index_number']));
        $student = Student::whereRaw('UPPER(TRIM(index_number)) = ?', [$indexUpper])->first();
        if (!$student) {
            return response()->json(['message' => 'Student not found'], 404);
        }

        // Bind body.index_number to the authenticated Sanctum user — a valid
        // bearer for student A must not be able to POST attendance for
        // student B. Lecturer / admin tokens are allowed to keep their
        // existing supplemental-mark flows working.
        $authUser = $request->user();
        if ($authUser instanceof Student && (int) $authUser->id !== (int) $student->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'You can only submit attendance for your own account.',
            ], 403);
        }

        // Single-student-per-device guard for the mobile client. The Flutter
        // app stores a stable per-install UUID in device_id; the same device
        // must not be used to mark attendance for two different students.
        // This is a hard block once any prior mark exists for a *different*
        // student tied to this device_id.
        $deviceId = isset($validated['device_id']) ? trim((string) $validated['device_id']) : '';
        if ($deviceId !== '') {
            $foreignDeviceMark = \App\Models\Attendance::query()
                ->where('device_id', $deviceId)
                ->where('student_id', '!=', $student->id)
                ->orderByDesc('id')
                ->first();
            if ($foreignDeviceMark !== null) {
                \App\Services\AuditLogService::record(\App\Services\AuditLogService::FRAUD_DETECTED, [
                    'request' => $request,
                    'subject_type' => 'student',
                    'subject_id' => (int) $student->id,
                    'class_id' => $student->class_id,
                    'payload' => [
                        'type' => 'device_id_dual_student',
                        'channel' => 'api',
                        'device_id' => $deviceId,
                        'previous_student_id' => (int) $foreignDeviceMark->student_id,
                    ],
                ]);

                return response()->json([
                    'status' => 'error',
                    'message' => 'This device has already been used to mark attendance for a different student. Each phone can only be linked to one student.',
                    'fraud' => true,
                ], 403);
            }
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
            // Late offline mark — capture for lecturer approval instead of
            // silently dropping. The client transitions the row to
            // Quarantined on `late=true` and surfaces the pending status.
            return \App\Services\AttendanceLateCaptureService::captureFor(
                $request,
                $student,
                $session,
                $course,
                \App\Services\AttendanceLateCaptureService::REASON_SESSION_EXPIRED,
                $validated,
                $attendanceUuid !== '' ? $attendanceUuid : null,
                isset($validated['timestamp']) ? Carbon::parse($validated['timestamp']) : null
            );
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
            $clientMeta = $this->parseClientMeta($validated['client_meta'] ?? null);
            $geofenceResponse = $this->validateSessionGeofence(
                $session,
                $course,
                $latitude,
                $longitude,
                $accuracyMeters,
                $clientMeta,
            );
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
            return \App\Services\AttendanceLateCaptureService::captureFor(
                $request,
                $student,
                $session,
                $course,
                \App\Services\AttendanceLateCaptureService::REASON_OUTSIDE_WINDOW,
                $validated,
                $attendanceUuid !== '' ? $attendanceUuid : null,
                $attendanceTime
            );
        }

        $deviceId = $validated['device_id'] ?? null;
        $deviceIp = $validated['device_ip'] ?? $ip;

        // Attendance Map redesign: compute distance_from_anchor exactly
        // once, here, and reuse it across both write branches below.
        // Returns NULL for non-GPS modes or when coords are missing —
        // matches the helper used by the web AttendanceController.
        $distanceFromAnchor = in_array($session->mode, ['location', 'hybrid'], true)
            ? \App\Support\AttendanceLocation::storableMetersFromPairs(
                $session->location_lat,
                $session->location_lng,
                $latitude,
                $longitude
            )
            : null;

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

            $collision = \App\Services\AttendanceFraudGuard::detectCollision($student, $session, $request);
            if ($collision !== null) {
                \App\Services\AuditLogService::record(\App\Services\AuditLogService::FRAUD_DETECTED, [
                    'request' => $request,
                    'subject_type' => 'student',
                    'subject_id' => $student->id,
                    'class_id' => $student->class_id,
                    'course_id' => $course->id,
                    'attendance_session_id' => $session->id,
                    'payload' => array_merge(['type' => $collision['reason'], 'channel' => 'api'], $collision['evidence']),
                ]);

                return response()->json([
                    'status' => 'error',
                    'message' => $collision['message'],
                    'fraud' => true,
                ], 403);
            }

            $capture = \App\Services\AttendanceFraudGuard::captureFromRequest($request);

            $checkinPayload = [
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
                'distance_from_anchor' => $distanceFromAnchor,
                'qr_code' => $request->input('qr_code') ?? $request->input('session_token') ?? $validated['qr_code'] ?? null,
                'device_ip' => $deviceIp,
                'device_id' => $deviceId,
                'user_agent' => $capture['user_agent'],
                'device_fingerprint' => $capture['device_fingerprint'],
                'client_meta' => $capture['client_meta'],
            ];
            if ($attendanceUuid !== '' && \App\Support\SchemaFeatures::hasAttendanceUuid()) {
                $checkinPayload['attendance_uuid'] = $attendanceUuid;
            }

            $checkinRow = Attendance::create($checkinPayload);

            return response()->json([
                'message' => 'Check-in recorded successfully',
                'status' => 'success',
                'attendance_status' => $status,
                'checkout_enabled' => (bool) $session->checkout_enabled,
                'attendance_uuid' => $attendanceUuid !== '' ? $attendanceUuid : null,
                'attendance_id' => (int) $checkinRow->id,
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

        // Cross-student fraud guard: same persistent device cookie cannot
        // submit a second mark for a different student inside the same
        // session/week. Survives IP changes (Wi-Fi → mobile data).
        $collision = \App\Services\AttendanceFraudGuard::detectCollision($student, $session, $request);
        if ($collision !== null) {
            \App\Services\AuditLogService::record(\App\Services\AuditLogService::FRAUD_DETECTED, [
                'request' => $request,
                'subject_type' => 'student',
                'subject_id' => $student->id,
                'class_id' => $student->class_id,
                'course_id' => $course->id,
                'attendance_session_id' => $session->id,
                'payload' => array_merge(['type' => $collision['reason'], 'channel' => 'api'], $collision['evidence']),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => $collision['message'],
                'fraud' => true,
            ], 403);
        }

        // Lock per (session, student) so the same student tapping mark twice
        // — or two workers handling the retry payload from a flaky phone —
        // collapses into a single insert. Pair with CACHE_STORE=redis on
        // production for cross-worker safety.
        $capture = \App\Services\AttendanceFraudGuard::captureFromRequest($request);
        $userAgent = $capture['user_agent'];
        $deviceFingerprint = $capture['device_fingerprint'];
        $clientMeta = $capture['client_meta'];
        $created = false;

        $hasUuidColumn = \App\Support\SchemaFeatures::hasAttendanceUuid();
        $createdRowId = null;
        \App\Support\AttendanceMarkLock::run(
            (int) $session->id,
            (int) $student->id,
            function () use (
                $student, $course, $session, $attendanceTime, $latitude, $longitude,
                $distanceFromAnchor, $request, $validated, $deviceIp, $deviceId, $userAgent,
                $deviceFingerprint, $clientMeta, $attendanceUuid, $hasUuidColumn,
                &$created, &$createdRowId
            ) {
                $defaults = [
                    'course_id' => $course->id,
                    'attendance_week_id' => $session->attendance_week_id,
                    'attendance_time' => $attendanceTime,
                    'status' => 'present',
                    'synced' => true,
                    'lat' => $latitude,
                    'lng' => $longitude,
                    'distance_from_anchor' => $distanceFromAnchor,
                    'qr_code' => $request->input('qr_code') ?? $request->input('session_token') ?? $validated['qr_code'] ?? null,
                    'device_ip' => $deviceIp,
                    'device_id' => $deviceId,
                    'user_agent' => $userAgent,
                    'device_fingerprint' => $deviceFingerprint,
                    'client_meta' => $clientMeta,
                ];
                if ($attendanceUuid !== '' && $hasUuidColumn) {
                    $defaults['attendance_uuid'] = $attendanceUuid;
                }

                $row = Attendance::firstOrCreate(
                    [
                        'student_id' => $student->id,
                        'attendance_session_id' => $session->id,
                    ],
                    $defaults
                );
                $created = $row->wasRecentlyCreated;
                $createdRowId = (int) $row->id;
            }
        );

        if ($created) {
            \App\Services\AuditLogService::record(\App\Services\AuditLogService::MARK_CREATED, [
                'request' => $request,
                'course_id' => (int) $course->id,
                'class_id' => $session->class_id ? (int) $session->class_id : null,
                'attendance_session_id' => (int) $session->id,
                'subject_type' => 'student',
                'subject_id' => (int) $student->id,
                'payload' => ['channel' => 'api'],
            ]);
        }

        $presentCount = Attendance::where('attendance_session_id', $session->id)
            ->where('status', 'present')
            ->count();
        try {
            event(new SessionLiveEvent($session->fresh(['course']), 'attendance_marked', ['present_count' => $presentCount]));
        } catch (\Throwable $e) {
            \Log::warning('SessionLiveEvent dispatch failed (api): '.$e->getMessage(), ['session_id' => $session->id]);
        }

        return response()->json([
            'message' => $created
                ? 'Attendance recorded successfully'
                : 'Attendance already recorded.',
            'status' => $created ? 'success' : 'already_marked',
            'already_marked' => ! $created,
            'attendance_uuid' => $attendanceUuid !== '' ? $attendanceUuid : null,
            'attendance_id' => $createdRowId,
            'auto_logout_seconds' => PostMarkAutoLogout::armForMobile($student, $session),
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
        if (! $student || ! PasswordPolicy::matches($validated['password'], $student->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $since = isset($validated['since']) && $validated['since'] !== ''
            ? Carbon::parse($validated['since'])
            : null;

        // Eager-load course on top of the existing relations so the
        // mobile client can render real course labels offline (the
        // original payload only ever shipped raw IDs).
        $attendances = Attendance::where('student_id', $student->id)
            ->with(['attendanceWeek', 'attendanceSession', 'course:id,course_name,course_code'])
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
            'records' => 'required|array|max:100',
            'records.*.index_number' => 'required|string',
            'records.*.course_id' => 'required|exists:courses,id',
            'records.*.session_id' => 'nullable|integer',
            'records.*.latitude' => 'nullable|numeric',
            'records.*.longitude' => 'nullable|numeric',
            'records.*.accuracy' => 'nullable|numeric|min:0|max:2000',
            'records.*.session_token' => 'nullable|string',
            'records.*.qr_code' => 'nullable|string',
            'records.*.qr_sig' => 'nullable|string',
            'records.*.qr_t' => 'nullable|integer',
            'records.*.session_code' => 'nullable|string|max:48',
            'records.*.wifi_ssid' => 'nullable|string|max:128',
            'records.*.attendance_time' => 'required|string',
            'records.*.device_id' => 'nullable|string|max:128',
            'records.*.device_ip' => 'nullable|string|max:45',
            'records.*.attendance_uuid' => 'nullable|string|min:8|max:64|regex:/^[A-Za-z0-9._-]+$/',
        ]);

        $result = AttendanceOfflineSyncService::process($validated['records']);

        // Response shape is additive: legacy `synced` / `failed` counts
        // stay so older clients keep working; the new `results` array
        // gives the offline outbox per-row state transitions.
        return response()->json([
            'success' => true,
            'synced' => $result['synced'],
            'failed' => $result['failed'],
            'results' => $result['results'] ?? [],
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
        if (! $student || ! PasswordPolicy::matches($validated['password'], $student->password)) {
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
            // Additive: gives the mobile client real course labels to
            // render in the history list. Older clients that only read
            // course_id still work unchanged.
            'course_code' => $a->course?->course_code,
            'course_name' => $a->course?->course_name,
            'attendance_session_id' => $a->attendance_session_id,
            'session_index' => $a->attendanceSession?->session_index,
            'session_mode' => $a->attendanceSession?->mode,
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
        ?float $horizontalAccuracyMeters = null,
        array $clientMeta = [],
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

        $distanceMeters = AttendanceLocation::distanceMeters(
            (float) $session->location_lat,
            (float) $session->location_lng,
            $lat,
            $lng
        );

        $floorMatches = SessionFloorAnchor::floorMatches($session, $clientMeta);

        if (AttendanceLocation::passesGeofenceCheck(
            $distanceMeters,
            $allowedMeters,
            $horizontalAccuracyMeters,
            $clientMeta,
            $floorMatches,
        )) {
            return null;
        }

        $cap = (int) config('app.geofence_accuracy_slack_cap_m', 120);
        $slack = $horizontalAccuracyMeters !== null
            ? min($cap, max(0.0, $horizontalAccuracyMeters))
            : 0.0;
        $effectiveLimit = $allowedMeters + $slack + ($floorMatches ? (int) config('app.geofence_floor_match_bonus_m', 30) : 0);

        return response()->json([
            'message' => 'Out of range',
            'distance_meters' => (int) round($distanceMeters),
            'allowed_meters' => $allowedMeters,
            'accuracy_slack_meters' => (int) round($slack),
            'effective_limit_meters' => (int) round($effectiveLimit),
            'floor_match' => $floorMatches,
        ], 403);
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
        ?float $lng,
        array $clientMeta = [],
    ): bool {
        if (! $session->requiresLocation() || ! $session->hasLocation()) {
            return false;
        }
        if ($lat === null || $lng === null) {
            return true;
        }
        $distanceMeters = AttendanceLocation::distanceMeters(
            (float) $session->location_lat,
            (float) $session->location_lng,
            $lat,
            $lng
        );
        $allowedMeters = $session->allowedGeofenceRadiusMeters($course);
        $floorMatches = SessionFloorAnchor::floorMatches($session, $clientMeta);

        return ! AttendanceLocation::passesGeofenceCheck(
            $distanceMeters,
            $allowedMeters,
            null,
            $clientMeta,
            $floorMatches,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function parseClientMeta(mixed $raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }
        if (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
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

            return AttendanceSession::resolveForMarking(
                $course,
                $sessionToken,
                null,
                $isRep,
                $student->class_id ? (int) $student->class_id : null,
            );
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
