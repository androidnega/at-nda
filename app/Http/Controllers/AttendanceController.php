<?php

namespace App\Http\Controllers;

use App\Events\SessionLiveEvent;
use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\Course;
use App\Models\Student;                                                 
use App\Models\SystemSetting;
use App\Services\AttendanceFraudGuard;
use App\Services\AttendanceOfflineSyncService;
use App\Services\AuditLogService;
use App\Support\AttendanceLocation;
use App\Support\AttendanceMarkLock;
use App\Support\AttendanceSessionClassScope;
use App\Support\SecureQrToken;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function form(Course $course, Request $request): View|RedirectResponse
    {
        // Resolve the signed-in student FIRST — a recent refactor moved
        // the $activeSession line above the assignment, which produced a
        // hard "Undefined variable $loggedInStudent" 500 on every web
        // /attendance/{course} hit (the student "Mark attendance" button).
        $loggedInStudent = null;
        if ($request->session()->has('student_id')) {
            $loggedInStudent = Student::find($request->session()->get('student_id'));
        }

        $activeSession = $loggedInStudent?->class_id
            ? $course->activeSessionForClass((int) $loggedInStudent->class_id)
            : null;

        // Online sessions have a dedicated UI (rolling code, no GPS / no
        // QR / no Wi-Fi anchor). Redirect signed-in students straight
        // there instead of rendering the generic form that would just
        // throw "no location available". PART 6 of the spec.
        if ($loggedInStudent !== null && $activeSession !== null && $activeSession->mode === 'online') {
            return redirect()->route('web.attendance.online.show', ['course' => $course->id]);
        }

        $settings = SystemSetting::get();

        $isClassRep = $loggedInStudent
            ? $loggedInStudent->isClassRepForCourse((int) $course->id)
            : false;

        return view('attendance.form', compact(
            'course',
            'activeSession',
            'settings',
            'loggedInStudent',
            'isClassRep',
        ));
    }

    /** Permanent redirect from legacy /attendance/{course} URLs (cacheable routes; no closures). */
    public function legacyRedirectToForm(Course $course): RedirectResponse
    {
        return redirect()->route('web.attendance.form', $course, 301);
    }

    public function legacyRedirectToSuccess(Course $course): RedirectResponse
    {
        return redirect()->route('web.attendance.success', $course, 301);
    }

    /**
     * Share-friendly entry URL for web attendance check-in.
     * Guests will still be prompted for index number on the attendance page.
     */
    public function directEntry(Course $course): RedirectResponse
    {
        return redirect()->route('web.attendance.form', $course, 302);
    }

    public function verify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'index_number' => 'required|string',
            'course_id' => 'required|exists:courses,id',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'session_token' => 'nullable|string',
            'session_id' => 'nullable|integer',
        ]);

        $settings = SystemSetting::get();
        $ip = $request->ip();

        $student = Student::findByIndex($validated['index_number']);
        if (! $student) {
            return response()->json(['verified' => false, 'message' => 'Student not found'], 404);
        }

        $requireFaceVerification = (bool) ($settings->enable_face_verification ?? true);

        if ($requireFaceVerification && ! $student->profile_image) {
            return response()->json([
                'verified' => false,
                'message' => 'Add a profile photo to your student account (use the camera on the profile page) before marking attendance.',
                'needs_profile_photo' => true,
            ], 422);
        }

        if ($settings->enable_ip_binding && $student->bound_ip && $student->bound_ip !== $ip) {
            return response()->json(['verified' => false, 'message' => 'Device mismatch. Contact admin.'], 403);
        }

        if ($settings->enable_ip_binding && ! $settings->allow_multiple_index_on_device && $student->bound_ip) {
            $other = Student::where('bound_ip', $ip)->where('id', '!=', $student->id)->first();
            if ($other && $other->index_number !== $student->index_number) {
                return response()->json(['verified' => false, 'message' => 'This device is linked to another student.'], 403);
            }
        }

        $course = Course::findOrFail($validated['course_id']);
        if ($student->isClassRepForCourse((int) $course->id)) {
            return response()->json([
                'verified' => false,
                'message' => 'Class reps are auto-marked when a session is active.',
            ], 403);
        }
        $isClassRep = $student->isClassRepForCourse((int) $course->id);
        $sessionId = isset($validated['session_id']) ? (int) $validated['session_id'] : null;
        $session = AttendanceSession::resolveForMarking(
            $course,
            $validated['session_token'] ?? null,
            $sessionId > 0 ? $sessionId : null,
            $isClassRep,
            $student->class_id ? (int) $student->class_id : null,
        );
        if (! $session) {
            return response()->json(['verified' => false, 'message' => 'Session closed or expired'], 422);
        }

        $supplementalRepMark = $isClassRep && ! $session->isValid();

        // Venue is anchored when the session opens; students are not required to send coordinates.
        // Optional lat/lng still validate against the session geofence when both are provided.
        if (! $supplementalRepMark && $session->requiresLocation()) {
            if (! $session->hasLocation()) {
                return response()->json(['verified' => false, 'message' => 'Session has no location set'], 422);
            }
            $lat = $validated['latitude'] ?? null;
            $lng = $validated['longitude'] ?? null;
            if ($lat !== null && $lng !== null && is_numeric($lat) && is_numeric($lng)) {
                $distance = $this->distance(
                    (float) $session->location_lat,
                    (float) $session->location_lng,
                    (float) $lat,
                    (float) $lng
                );
                if ($distance > $session->allowedGeofenceRadiusMeters($course)) {
                    return response()->json([
                        'verified' => false,
                        'message' => 'Out of range',
                        'distance' => round($distance),
                        'allowed_meters' => $session->allowedGeofenceRadiusMeters($course),
                    ], 422);
                }
            }
        }

        $profileImageUrl = $student->profileImageUrl();

        return response()->json([
            'verified' => true,
            'student' => ['id' => $student->id, 'index_number' => $student->index_number],
            'profile_image_url' => $profileImageUrl,
            'require_face_verification' => $requireFaceVerification,
            'face_match_threshold' => (float) ($settings->face_match_threshold ?? 0.5),
        ]);
    }

    public function success(Course $course): View
    {
        return view('attendance.success', compact('course'));
    }

    public function mark(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'index_number' => 'required|string',
            'course_id' => 'required|exists:courses,id',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'session_token' => 'nullable|string',
            'session_id' => 'nullable|integer',
            'session_code' => 'nullable|string|max:48',
            'qr_sig' => 'nullable|string',
            'qr_t' => 'nullable|integer',
            'wifi_ssid' => 'nullable|string|max:128',
            'client_meta' => 'nullable',
        ]);

        // ---- [QR-DEBUG] inbound payload ----
        // Logs a sanitized snapshot of what arrived so an operator
        // running `tail -F storage/logs/laravel-YYYY-MM-DD.log | grep
        // QR-DEBUG` can see every mark attempt end-to-end. NEVER log
        // the full session_token (it's a credential); head/tail only.
        $tok = (string) ($validated['session_token'] ?? '');
        $debugId = bin2hex(random_bytes(4)); // ties all lines for one request together
        Log::info('[QR-DEBUG] mark.received', [
            'debug_id' => $debugId,
            'ip' => $request->ip(),
            'ua' => substr((string) $request->userAgent(), 0, 80),
            'course_id' => $validated['course_id'] ?? null,
            'index_number' => $validated['index_number'] ?? null,
            'session_id_hint' => $validated['session_id'] ?? null,
            'session_token_len' => strlen($tok),
            'session_token_head' => substr($tok, 0, 8),
            'session_token_tail' => strlen($tok) > 12 ? substr($tok, -8) : '',
            'has_session_code' => isset($validated['session_code']) && trim((string) $validated['session_code']) !== '',
            'lat' => $validated['latitude'] ?? null,
            'lng' => $validated['longitude'] ?? null,
        ]);
        $request->attributes->set('qr_debug_id', $debugId);

        if (! empty($validated['wifi_ssid'])) {
            $validated['wifi_ssid'] = trim(preg_replace('/[\x00-\x1F\x7F]/u', '', (string) $validated['wifi_ssid']));
        }

        $settings = SystemSetting::get();
        $ip = $request->ip();

        $student = Student::findByIndex($validated['index_number']);
        if (! $student) {
            return response()->json(['success' => false, 'message' => 'Student not found'], 404);
        }

        $requireFaceVerification = (bool) ($settings->enable_face_verification ?? true);

        if ($requireFaceVerification && ! $student->profile_image) {
            return response()->json([
                'success' => false,
                'message' => 'Profile photo required. Update your profile with a camera photo before marking attendance.',
                'needs_profile_photo' => true,
            ], 422);
        }

        if ($settings->enable_ip_binding && $student->bound_ip && $student->bound_ip !== $ip) {
            return response()->json(['success' => false, 'message' => 'Device mismatch. Contact admin.'], 403);
        }

        if ($settings->enable_ip_binding && ! $settings->allow_multiple_index_on_device && $student->bound_ip) {
            $other = Student::where('bound_ip', $ip)->where('id', '!=', $student->id)->first();
            if ($other && $other->index_number !== $student->index_number) {
                return response()->json(['success' => false, 'message' => 'This device is linked to another student.'], 403);
            }
        }

        $course = Course::findOrFail($validated['course_id']);
        if ($student->isClassRepForCourse((int) $course->id)) {
            return response()->json([
                'success' => false,
                'message' => 'Class reps are auto-marked when a session is active.',
            ], 403);
        }
        $isClassRep = $student->isClassRepForCourse((int) $course->id);
        $sessionCode = isset($validated['session_code']) ? trim((string) $validated['session_code']) : '';

        if ($sessionCode !== '') {
            // First try the legacy static `session_code` column. Falls
            // back to the new rotating 6-char code (deterministic per
            // active session + current window). We have to do the static
            // lookup first because admins/lecturers may still issue the
            // long static codes; the rotating code is matched by trying
            // every *currently active* session for this course.
            $session = AttendanceSession::query()
                ->where('course_id', $course->id)
                ->where(function ($q) use ($sessionCode) {
                    $q->where('session_code', $sessionCode)
                        ->orWhereRaw('LOWER(session_code) = ?', [strtolower($sessionCode)]);
                })
                ->first();

            if (! $session) {
                $candidate = strtoupper(trim($sessionCode));
                // Rotating codes are exactly 6 chars from our restricted
                // alphabet — cheap test before we touch the DB.
                if (strlen($candidate) === 6 && preg_match('/^[A-Z2-9]{6}$/', $candidate)) {
                    $activeSessions = AttendanceSession::query()
                        ->where('course_id', $course->id)
                        ->where('is_active', true)
                        ->where(function ($q) {
                            $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                        })
                        ->get();
                    foreach ($activeSessions as $candidateSession) {
                        if (SecureQrToken::isValidRotatingCode($candidate, $candidateSession)) {
                            $session = $candidateSession;
                            break;
                        }
                    }
                }
            }

            if (! $session || ! $session->isValid()) {
                return response()->json(['success' => false, 'message' => 'Invalid or expired session code'], 422);
            }
            if ($student->class_id && ! AttendanceSessionClassScope::sessionBelongsToClass($session, (int) $student->class_id)) {
                return response()->json(['success' => false, 'message' => 'This session is not for your class'], 403);
            }
        } else {
            $sessionId = isset($validated['session_id']) ? (int) $validated['session_id'] : null;
            $session = AttendanceSession::resolveForMarking(
                $course,
                $validated['session_token'] ?? null,
                $sessionId > 0 ? $sessionId : null,
                $isClassRep,
                $student->class_id ? (int) $student->class_id : null,
            );
        }

        if (! $session) {
            Log::warning('[QR-DEBUG] mark.session_not_resolved', [
                'debug_id' => $debugId,
                'course_id' => $course->id,
                'session_id_hint' => $validated['session_id'] ?? null,
                'session_code_provided' => $sessionCode !== '',
            ]);

            return response()->json(['success' => false, 'message' => 'Session closed or expired'], 422);
        }

        $supplementalRepMark = $isClassRep && ! $session->isValid();

        $mode = $session->mode;

        Log::info('[QR-DEBUG] mark.session_resolved', [
            'debug_id' => $debugId,
            'session_id' => $session->id,
            'mode' => $mode,
            'is_active' => (bool) $session->is_active,
            'is_valid' => $session->isValid(),
            'expires_at' => optional($session->expires_at)?->toIso8601String(),
            'is_class_rep' => $isClassRep,
            'supplemental_rep_mark' => $supplementalRepMark,
        ]);

        if (! $supplementalRepMark && $mode === 'qr') {
            if (! $isClassRep) {
                $qrErr = $this->validateQrProofJson($session, $validated, $debugId);
                if ($qrErr !== null) {
                    return $qrErr;
                }
            }
        } elseif (! $supplementalRepMark && $mode === 'hybrid') {
            if (! $session->hasLocation()) {
                return response()->json(['success' => false, 'message' => 'Session has no location set'], 422);
            }
            if (! empty($validated['latitude']) && ! empty($validated['longitude'])) {
                $distance = $this->distance(
                    (float) $session->location_lat,
                    (float) $session->location_lng,
                    (float) $validated['latitude'],
                    (float) $validated['longitude']
                );
                if ($distance > $session->allowedGeofenceRadiusMeters($course)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Out of range',
                        'distance' => round($distance),
                        'allowed_meters' => $session->allowedGeofenceRadiusMeters($course),
                    ], 422);
                }
            }
            if (! $isClassRep) {
                $qrErr = $this->validateQrProofJson($session, $validated, $debugId);
                if ($qrErr !== null) {
                    return $qrErr;
                }
            }
        } elseif (! $supplementalRepMark && $mode === 'location') {
            if (! $session->hasLocation()) {
                return response()->json(['success' => false, 'message' => 'Session has no location set'], 422);
            }
            if (! empty($validated['latitude']) && ! empty($validated['longitude'])) {
                $distance = $this->distance(
                    (float) $session->location_lat,
                    (float) $session->location_lng,
                    (float) $validated['latitude'],
                    (float) $validated['longitude']
                );
                if ($distance > $session->allowedGeofenceRadiusMeters($course)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Out of range',
                        'distance' => round($distance),
                        'allowed_meters' => $session->allowedGeofenceRadiusMeters($course),
                    ], 422);
                }
            }
        } elseif (! $supplementalRepMark && $mode === 'wifi') {
            $expected = trim((string) ($session->allowed_wifi_ssid ?? ''));
            if ($expected === '') {
                return response()->json(['success' => false, 'message' => 'Wi‑Fi session not configured'], 422);
            }
            $got = trim((string) ($validated['wifi_ssid'] ?? ''));
            if ($got !== '' && strcasecmp($got, $expected) !== 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Not on the required Wi‑Fi network',
                    'expected_ssid' => $expected,
                ], 403);
            }
        }

        $existing = Attendance::where('student_id', $student->id)
            ->where('course_id', $course->id)
            ->where('attendance_session_id', $session->id)
            ->first();

        if ($session->isCheckInCheckoutMode()) {
            $now = now();
            if (! $existing) {
                $status = 'present';
                $start = $session->start_time ? Carbon::parse($session->start_time) : null;
                if ($start !== null && $now->greaterThan($start->copy()->addMinutes(20))) {
                    $status = 'late';
                }

                $collision = AttendanceFraudGuard::detectCollision($student, $session, $request);
                if ($collision !== null) {
                    AuditLogService::record(AuditLogService::FRAUD_DETECTED, [
                        'request' => $request,
                        'subject_type' => 'student',
                        'subject_id' => $student->id,
                        'class_id' => $student->class_id,
                        'course_id' => $course->id,
                        'attendance_session_id' => $session->id,
                        'payload' => array_merge(['type' => $collision['reason']], $collision['evidence']),
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => $collision['message'],
                        'fraud' => true,
                    ], 403);
                }

                $capture = AttendanceFraudGuard::captureFromRequest($request);

                // Attendance Map redesign: persist GPS + the
                // distance-from-anchor that we computed during
                // validation above. Distance is recorded for
                // location + hybrid; for QR / Wi-Fi / online there
                // is no anchor so it stays NULL.
                [$markLat, $markLng, $markDistance] = $this->locationFieldsForMark(
                    $session,
                    $validated['latitude'] ?? null,
                    $validated['longitude'] ?? null
                );

                Attendance::create([
                    'student_id' => $student->id,
                    'course_id' => $course->id,
                    'attendance_session_id' => $session->id,
                    'attendance_week_id' => $session->attendance_week_id,
                    'attendance_time' => $now,
                    'check_in_time' => $now,
                    'status' => $status,
                    'synced' => true,
                    'lat' => $markLat,
                    'lng' => $markLng,
                    'distance_from_anchor' => $markDistance,
                    'device_ip' => $capture['device_ip'],
                    'user_agent' => $capture['user_agent'],
                    'device_fingerprint' => $capture['device_fingerprint'],
                    'client_meta' => $capture['client_meta'],
                ]);

                return response()->json([
                    'success' => true,
                    'phase' => 'checkin',
                    'message' => 'Check-in recorded. Wait for checkout time.',
                ]);
            }

            if (! empty($existing->check_out_time)) {
                return response()->json([
                    'success' => true,
                    'phase' => 'checkout',
                    'message' => 'Already checked out.',
                ]);
            }

            if (! $session->checkout_enabled) {
                return response()->json([
                    'success' => false,
                    'message' => 'Checkout is not enabled yet.',
                ], 422);
            }

            $outsideRange = false;
            if ($session->hasLocation() && ! empty($validated['latitude']) && ! empty($validated['longitude'])) {
                $distance = $this->distance(
                    (float) $session->location_lat,
                    (float) $session->location_lng,
                    (float) $validated['latitude'],
                    (float) $validated['longitude']
                );
                $outsideRange = $distance > $session->allowedGeofenceRadiusMeters($course);
            }

            $checkOutAt = $now;
            $checkInAt = $existing->check_in_time ? Carbon::parse($existing->check_in_time) : null;
            $timeSpent = $checkInAt ? max(0, $checkOutAt->diffInSeconds($checkInAt)) : null;
            $finalStatus = $outsideRange ? 'late' : ($existing->status ?: 'present');

            $existing->update([
                'check_out_time' => $checkOutAt,
                'time_spent_seconds' => $timeSpent,
                'status' => $finalStatus,
            ]);

            return response()->json([
                'success' => true,
                'phase' => 'checkout',
                'message' => $outsideRange
                    ? 'Checked out outside range. Marked absent.'
                    : 'Checkout recorded.',
            ]);
        }

        if ($existing) {
            return response()->json([
                'success' => true,
                'message' => 'Already marked',
                'redirect' => route('web.attendance.success', $course),
            ]);
        }

        // Hard fraud gate: same device fingerprint cannot mark for two
        // different students inside the same session / week. This rides
        // on a 1-year persistent cookie, so it survives the obvious
        // bypass attempts (Wi-Fi → mobile data, private window, browser
        // restart). When triggered we audit-log and refuse the mark.
        $collision = AttendanceFraudGuard::detectCollision($student, $session, $request);
        if ($collision !== null) {
            AuditLogService::record(AuditLogService::FRAUD_DETECTED, [
                'request' => $request,
                'subject_type' => 'student',
                'subject_id' => $student->id,
                'class_id' => $student->class_id,
                'course_id' => $course->id,
                'attendance_session_id' => $session->id,
                'payload' => array_merge(['type' => $collision['reason']], $collision['evidence']),
            ]);

            return response()->json([
                'success' => false,
                'message' => $collision['message'],
                'fraud' => true,
            ], 403);
        }

        $capture = AttendanceFraudGuard::captureFromRequest($request);
        $deviceIp = $capture['device_ip'];
        $userAgent = $capture['user_agent'];
        $deviceFingerprint = $capture['device_fingerprint'];
        $clientMeta = $capture['client_meta'];
        $created = false;

        // Attendance Map redesign: capture the student's coordinates +
        // the precomputed distance-from-anchor. Distance is metres
        // already validated above; for non-GPS modes we just store
        // NULLs and the map skips those marks.
        [$markLat, $markLng, $markDistance] = $this->locationFieldsForMark(
            $session,
            $validated['latitude'] ?? null,
            $validated['longitude'] ?? null
        );

        // Cache-backed lock: with hundreds of students POSTing at the same
        // moment the duplicate-key fence + lock keep us to a single insert
        // per (session, student) pair. Use CACHE_STORE=redis on production
        // so all PHP workers share the same lock.
        AttendanceMarkLock::run(
            (int) $session->id,
            (int) $student->id,
            function () use (
                $student,
                $course,
                $session,
                $deviceIp,
                $userAgent,
                $deviceFingerprint,
                $clientMeta,
                $markLat,
                $markLng,
                $markDistance,
                &$created
            ) {
                $row = Attendance::firstOrCreate(
                    [
                        'student_id' => $student->id,
                        'attendance_session_id' => $session->id,
                    ],
                    [
                        'course_id' => $course->id,
                        'attendance_week_id' => $session->attendance_week_id,
                        'attendance_time' => now(),
                        'status' => 'present',
                        'synced' => true,
                        'lat' => $markLat,
                        'lng' => $markLng,
                        'distance_from_anchor' => $markDistance,
                        'device_ip' => $deviceIp,
                        'user_agent' => $userAgent,
                        'device_fingerprint' => $deviceFingerprint,
                        'client_meta' => $clientMeta,
                    ]
                );
                $created = $row->wasRecentlyCreated;
            }
        );

        if ($created) {
            AuditLogService::record(AuditLogService::MARK_CREATED, [
                'request' => $request,
                'course_id' => (int) $course->id,
                'class_id' => $session->class_id ? (int) $session->class_id : null,
                'attendance_session_id' => (int) $session->id,
                'subject_type' => 'student',
                'subject_id' => (int) $student->id,
                'payload' => ['channel' => 'web'],
            ]);
        }

        $presentCount = Attendance::where('attendance_session_id', $session->id)
            ->where('status', 'present')
            ->count();
        try {
            event(new SessionLiveEvent($session->fresh(['course']), 'attendance_marked', ['present_count' => $presentCount]));
        } catch (\Throwable $e) {
            // Broadcasting / queue outage must never break an attendance mark.
            \Log::warning('SessionLiveEvent dispatch failed: '.$e->getMessage(), ['session_id' => $session->id]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Marked',
            'redirect' => route('web.attendance.success', $course),
        ]);
    }

    public function sync(Request $request): JsonResponse
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
     * @param  array{session_token?: string|null, qr_sig?: string|null, qr_t?: int|null}  $validated
     */
    private function validateQrProofJson(AttendanceSession $session, array $validated, ?string $debugId = null): ?JsonResponse
    {
        $manual = isset($validated['session_code']) ? trim((string) $validated['session_code']) : '';
        if ($manual !== '') {
            // Legacy static session code.
            if (strcasecmp($manual, (string) ($session->session_code ?? '')) === 0) {
                Log::info('[QR-DEBUG] qr_validation.passed', [
                    'debug_id' => $debugId,
                    'via' => 'static_session_code',
                    'session_id' => $session->id,
                ]);

                return null;
            }
            // New rotating code (6-char) — checked against current
            // window + 2 previous windows inside the helper, so a
            // student who reads it during a rotation still gets in.
            if (SecureQrToken::isValidRotatingCode($manual, $session)) {
                Log::info('[QR-DEBUG] qr_validation.passed', [
                    'debug_id' => $debugId,
                    'via' => 'rotating_code',
                    'session_id' => $session->id,
                ]);

                return null;
            }
            Log::warning('[QR-DEBUG] qr_validation.manual_code_mismatch', [
                'debug_id' => $debugId,
                'session_id' => $session->id,
                'submitted_len' => strlen($manual),
                'submitted_head' => substr(strtoupper($manual), 0, 4),
                'static_code_set' => $session->session_code !== null && $session->session_code !== '',
            ]);
        }

        $tok = isset($validated['session_token']) ? trim((string) $validated['session_token']) : '';
        if ($tok === '') {
            Log::warning('[QR-DEBUG] qr_validation.no_token_no_code', [
                'debug_id' => $debugId,
                'session_id' => $session->id,
            ]);

            return response()->json(['success' => false, 'message' => 'Invalid QR code or session code'], 422);
        }

        // Full diagnosis — tells us *which* check failed (signature,
        // expiry, session-id mismatch, missing secret, …).
        $diag = SecureQrToken::diagnoseSubmission($tok, $session);
        if (! $diag['ok']) {
            Log::warning('[QR-DEBUG] qr_validation.failed', [
                'debug_id' => $debugId,
                'session_id' => $session->id,
                'reason' => $diag['reason'],
                'parsed' => $diag['parsed'],
                'secret_configured' => $diag['secret_configured'],
                'token_session_id' => $diag['token_session_id'],
                'server_session_id' => $diag['server_session_id'],
                'token_expires_at' => $diag['expires_at'],
                'now' => now()->timestamp,
                'expected_qr_token_match' => $diag['expected_qr_token_match'],
                'token_len' => $diag['len'],
                'token_head' => $diag['head'],
                'token_tail' => $diag['tail'],
            ]);

            return response()->json(['success' => false, 'message' => 'Invalid QR code'], 403);
        }

        if (array_key_exists('session_id', $validated)
            && $validated['session_id'] !== null
            && $validated['session_id'] !== ''
            && (int) $validated['session_id'] !== (int) $session->id) {
            Log::warning('[QR-DEBUG] qr_validation.session_id_hint_mismatch', [
                'debug_id' => $debugId,
                'submitted_session_id' => (int) $validated['session_id'],
                'resolved_session_id' => (int) $session->id,
            ]);

            return response()->json(['success' => false, 'message' => 'Invalid QR code'], 403);
        }

        Log::info('[QR-DEBUG] qr_validation.passed', [
            'debug_id' => $debugId,
            'via' => 'signed_token',
            'session_id' => $session->id,
        ]);

        return null;
    }

    /**
     * Thin wrapper kept for backwards-compat with the rest of this
     * controller. Delegates to AttendanceLocation so the validation
     * path and the map write-path produce identical numbers (no
     * rounding drift).
     */
    private function distance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        return AttendanceLocation::distanceMeters($lat1, $lng1, $lat2, $lng2);
    }

    /**
     * Decide what to persist on the attendance row for the three
     * location-aware columns. We:
     *
     *   - keep lat/lng only when the session is location or hybrid
     *     (QR / Wi-Fi / online have no anchor → storing the student's
     *     GPS would be a privacy leak with no analytical value);
     *   - compute distance_from_anchor exactly once here, reusing the
     *     same haversine the validation path already evaluated.
     *
     * Returns [lat, lng, distance_from_anchor] as values ready to drop
     * straight into an Attendance::create payload (any column may be
     * null; the model's saving hook strips distance_from_anchor on
     * databases that haven't migrated yet).
     *
     * @return array{0: ?float, 1: ?float, 2: ?int}
     */
    private function locationFieldsForMark(AttendanceSession $session, mixed $lat, mixed $lng): array
    {
        if (! in_array($session->mode, ['location', 'hybrid'], true)) {
            return [null, null, null];
        }
        if ($lat === null || $lng === null || ! is_numeric($lat) || ! is_numeric($lng)) {
            return [null, null, null];
        }

        $latF = (float) $lat;
        $lngF = (float) $lng;

        $distance = AttendanceLocation::storableMetersFromPairs(
            $session->location_lat,
            $session->location_lng,
            $latF,
            $lngF,
        );

        return [$latF, $lngF, $distance];
    }
}
