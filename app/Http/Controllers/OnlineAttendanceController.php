<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\Course;
use App\Models\Student;
use App\Services\AuditLogService;
use App\Support\SecureQrToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * Student-facing controller for marking attendance in an ONLINE session.
 *
 * Distinct from AttendanceController (which handles in-person GPS / QR /
 * Wi-Fi flows) because online sessions skip every spatial check and add
 * two new entry methods: the rep's QR uploaded as a screenshot, or the
 * session's manual code typed in.
 *
 * Existing plumbing reused so we don't duplicate state:
 *  - attendance_sessions.qr_token        — for QR uploads (decoded client-side)
 *  - attendance_sessions.session_code    — the typeable manual code
 *  - attendance_sessions.expires_at      — auto-close deadline
 *  - attendance_sessions.online_submode  — qr | code | both (which lanes are open)
 *  - mode = 'online'                     — flag that triggers "no anchor" behaviour
 */
class OnlineAttendanceController extends Controller
{
    private function getStudent(Request $request): ?Student
    {
        $id = $request->session()->get('student_id');

        return $id ? Student::find($id) : null;
    }

    /**
     * GET /web/attendance/{course}/online — show the upload-QR-or-enter-code page.
     */
    public function show(Request $request, Course $course): View|RedirectResponse
    {
        $student = $this->getStudent($request);
        if (! $student) {
            return redirect()->route('home')->with('info', 'Please sign in to mark attendance.');
        }

        $session = $this->resolveActiveOnlineSession($course, $student);
        if (! $session) {
            return redirect()
                ->route('student.dashboard')
                ->with('error', 'No active online session for '.$course->course_name.'.');
        }

        return view('attendance.online', [
            'course' => $course,
            'session' => $session,
            'student' => $student,
            // Front-end uses these to pick which inputs to render.
            'allowQr' => in_array($session->online_submode, ['qr', 'both', null], true),
            'allowCode' => in_array($session->online_submode, ['code', 'both', null], true),
            'expiresAt' => $session->expires_at,
        ]);
    }

    /**
     * POST /web/attendance/{course}/online/qr — student-uploaded QR, decoded
     * client-side (jsQR) into a token string. Server only validates.
     */
    public function submitQr(Request $request, Course $course): JsonResponse
    {
        $validated = $request->validate([
            'qr_payload' => 'required|string|max:512',
        ]);

        return $this->markCommon($request, $course, function (AttendanceSession $session) use ($validated) {
            $payload = trim((string) $validated['qr_payload']);
            // Accept either the secure SecureQrToken-encoded string or the
            // raw qr_token (for sessions where QR_SECRET isn't configured).
            $secret = SecureQrToken::secret();
            if ($secret) {
                $decoded = SecureQrToken::decode($payload);
                if (! $decoded || (int) ($decoded['session_id'] ?? 0) !== (int) $session->id) {
                    return ['ok' => false, 'message' => 'This QR is not for the active session.'];
                }
            } else {
                if ($session->qr_token === null || ! hash_equals((string) $session->qr_token, $payload)) {
                    return ['ok' => false, 'message' => 'This QR is not for the active session.'];
                }
            }

            return ['ok' => true, 'channel' => 'online_qr'];
        });
    }

    /**
     * POST /web/attendance/{course}/online/code — student typed the manual code.
     */
    public function submitCode(Request $request, Course $course): JsonResponse
    {
        $validated = $request->validate([
            'session_code' => 'required|string|max:48',
        ]);

        return $this->markCommon($request, $course, function (AttendanceSession $session) use ($validated) {
            $entered = strtoupper(trim((string) $validated['session_code']));
            $real = strtoupper((string) ($session->session_code ?? ''));
            if ($real === '' || ! hash_equals($real, $entered)) {
                return ['ok' => false, 'message' => 'That code is not valid for this session.'];
            }

            return ['ok' => true, 'channel' => 'online_code'];
        });
    }

    /**
     * Shared end-stage: resolve student + session, run the channel-specific
     * validator, then either persist a new Attendance row or no-op if the
     * student already marked in. Auto-closes the session if it has expired.
     */
    private function markCommon(Request $request, Course $course, \Closure $channelValidator): JsonResponse
    {
        $student = $this->getStudent($request);
        if (! $student) {
            return response()->json(['ok' => false, 'message' => 'Sign in to mark attendance.'], 401);
        }

        $session = $this->resolveActiveOnlineSession($course, $student);
        if (! $session) {
            return response()->json([
                'ok' => false,
                'message' => 'Online session is not active or has ended.',
                'expired' => true,
            ], 410);
        }

        // Channel-specific check (QR token vs manual code).
        $check = $channelValidator($session);
        if (empty($check['ok'])) {
            return response()->json([
                'ok' => false,
                'message' => $check['message'] ?? 'Verification failed.',
            ], 422);
        }
        $channel = (string) $check['channel'];

        // Idempotent insert: if the student already has a present row for
        // this session, just return success without touching the DB.
        $existing = Attendance::query()
            ->where('student_id', $student->id)
            ->where('attendance_session_id', $session->id)
            ->first();

        if ($existing) {
            // Nudge from absent to present if the rep had pre-recorded them.
            if ($existing->status !== 'present') {
                $existing->update(['status' => 'present', 'attendance_time' => now()]);
            }

            return response()->json([
                'ok' => true,
                'message' => 'You are already marked present for this online session.',
                'redirect' => route('web.attendance.success', ['course' => $course->id]),
            ]);
        }

        DB::transaction(function () use ($student, $session, $course, $request, $channel) {
            Attendance::create([
                'student_id' => $student->id,
                'course_id' => $course->id,
                'attendance_session_id' => $session->id,
                'attendance_week_id' => $session->attendance_week_id,
                'status' => 'present',
                'attendance_time' => now(),
                'device_ip' => (string) $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 480),
                'synced' => true,
            ]);

            AuditLogService::record(AuditLogService::MARK_CREATED, [
                'request' => $request,
                'course_id' => (int) $course->id,
                'class_id' => $student->class_id ? (int) $student->class_id : null,
                'attendance_session_id' => (int) $session->id,
                'subject_type' => 'attendance',
                'subject_id' => null,
                'payload' => [
                    'channel' => $channel,
                    'student_id' => (int) $student->id,
                    'index_number' => (string) $student->index_number,
                ],
            ]);
        });

        return response()->json([
            'ok' => true,
            'message' => 'Marked present for this online lecture.',
            'redirect' => route('web.attendance.success', ['course' => $course->id]),
        ]);
    }

    /**
     * Find the live online session for this course/student. Returns null if
     * none, or if the session has expired (also flips is_active=false in
     * that case so subsequent requests skip straight to the "ended" branch).
     */
    private function resolveActiveOnlineSession(Course $course, Student $student): ?AttendanceSession
    {
        $session = AttendanceSession::query()
            ->where('course_id', $course->id)
            ->where('mode', 'online')
            ->where('is_active', true)
            ->when((bool) $student->class_id, function ($q) use ($student) {
                // Online sessions are class-scoped when the column exists; if
                // it's absent the query just falls through course-scope.
                if (\App\Support\SchemaFeatures::hasAttendanceSessionsClassId()) {
                    $q->where(function ($qq) use ($student) {
                        $qq->whereNull('class_id')
                            ->orWhere('class_id', $student->class_id);
                    });
                }
            })
            ->orderByDesc('id')
            ->first();

        if (! $session) {
            return null;
        }

        // Auto-close if the deadline has passed. We only flip the flag once
        // (saveQuietly skips events) so we don't fire SessionLiveEvent for
        // an end that already happened.
        if ($session->expires_at && $session->expires_at->isPast()) {
            try {
                $session->is_active = false;
                $session->saveQuietly();
            } catch (\Throwable $e) {
                Log::warning('online-attendance: auto-close failed', ['session_id' => $session->id, 'error' => $e->getMessage()]);
            }

            return null;
        }

        return $session;
    }
}
