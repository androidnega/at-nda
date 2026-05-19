<?php

namespace App\Http\Controllers;

use App\Events\SessionLiveEvent;
use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\Course;
use App\Models\Student;
use App\Models\SystemSetting;
use App\Services\AttendanceOfflineSyncService;
use App\Support\SecureQrToken;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function form(Course $course, Request $request): View
    {
        $activeSession = $course->activeSession();
        $settings = SystemSetting::get();
        $loggedInStudent = null;
        if ($request->session()->has('student_id')) {
            $loggedInStudent = Student::find($request->session()->get('student_id'));
        }

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
        if (!$student) {
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

        if ($settings->enable_ip_binding && !$settings->allow_multiple_index_on_device && $student->bound_ip) {
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
            $isClassRep
        );
        if (! $session) {
            return response()->json(['verified' => false, 'message' => 'Session closed or expired'], 422);
        }

        $supplementalRepMark = $isClassRep && ! $session->isValid();

        // Venue is anchored when the session opens; students are not required to send coordinates.
        // Optional lat/lng still validate against the session geofence when both are provided.
        if (! $supplementalRepMark && $session->requiresLocation()) {
            if (!$session->hasLocation()) {
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
        ]);

        if (! empty($validated['wifi_ssid'])) {
            $validated['wifi_ssid'] = trim(preg_replace('/[\x00-\x1F\x7F]/u', '', (string) $validated['wifi_ssid']));
        }

        $settings = SystemSetting::get();
        $ip = $request->ip();

        $student = Student::findByIndex($validated['index_number']);
        if (!$student) {
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

        if ($settings->enable_ip_binding && !$settings->allow_multiple_index_on_device && $student->bound_ip) {
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
            $session = AttendanceSession::query()
                ->where('course_id', $course->id)
                ->where(function ($q) use ($sessionCode) {
                    $q->where('session_code', $sessionCode)
                        ->orWhereRaw('LOWER(session_code) = ?', [strtolower($sessionCode)]);
                })
                ->first();
            if (! $session || ! $session->isValid()) {
                return response()->json(['success' => false, 'message' => 'Invalid or expired session code'], 422);
            }
        } else {
            $sessionId = isset($validated['session_id']) ? (int) $validated['session_id'] : null;
            $session = AttendanceSession::resolveForMarking(
                $course,
                $validated['session_token'] ?? null,
                $sessionId > 0 ? $sessionId : null,
                $isClassRep
            );
        }

        if (! $session) {
            return response()->json(['success' => false, 'message' => 'Session closed or expired'], 422);
        }

        $supplementalRepMark = $isClassRep && ! $session->isValid();

        $mode = $session->mode;

        if (! $supplementalRepMark && $mode === 'qr') {
            if (!$isClassRep) {
                $qrErr = $this->validateQrProofJson($session, $validated);
                if ($qrErr !== null) {
                    return $qrErr;
                }
            }
        } elseif (! $supplementalRepMark && $mode === 'hybrid') {
            if (!$session->hasLocation()) {
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
            if (!$isClassRep) {
                $qrErr = $this->validateQrProofJson($session, $validated);
                if ($qrErr !== null) {
                    return $qrErr;
                }
            }
        } elseif (! $supplementalRepMark && $mode === 'location') {
            if (!$session->hasLocation()) {
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

                Attendance::create([
                    'student_id' => $student->id,
                    'course_id' => $course->id,
                    'attendance_session_id' => $session->id,
                    'attendance_week_id' => $session->attendance_week_id,
                    'attendance_time' => $now,
                    'check_in_time' => $now,
                    'status' => $status,
                    'synced' => true,
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

        Attendance::create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'attendance_session_id' => $session->id,
            'attendance_week_id' => $session->attendance_week_id,
            'attendance_time' => now(),
            'status' => 'present',
            'synced' => true,
        ]);

        $presentCount = Attendance::where('attendance_session_id', $session->id)
            ->where('status', 'present')
            ->count();
        event(new SessionLiveEvent($session->fresh(['course']), 'attendance_marked', ['present_count' => $presentCount]));

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
    private function validateQrProofJson(AttendanceSession $session, array $validated): ?JsonResponse
    {
        $manual = isset($validated['session_code']) ? trim((string) $validated['session_code']) : '';
        if ($manual !== '' && strcasecmp($manual, (string) ($session->session_code ?? '')) === 0) {
            return null;
        }

        $tok = isset($validated['session_token']) ? trim((string) $validated['session_token']) : '';
        if ($tok === '') {
            return response()->json(['success' => false, 'message' => 'Invalid QR code or session code'], 422);
        }
        if (! SecureQrToken::isValidSubmission($tok, $session)) {
            return response()->json(['success' => false, 'message' => 'Invalid QR code'], 403);
        }
        if (array_key_exists('session_id', $validated)
            && $validated['session_id'] !== null
            && $validated['session_id'] !== ''
            && (int) $validated['session_id'] !== (int) $session->id) {
            return response()->json(['success' => false, 'message' => 'Invalid QR code'], 403);
        }

        return null;
    }

    private function distance(float $lat1, float $lng1, float $lat2, float $lng2): float
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
}
