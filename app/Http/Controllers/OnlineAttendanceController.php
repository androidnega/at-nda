<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\Course;
use App\Models\Student;
use App\Services\AttendanceRiskService;
use App\Services\AuditLogService;
use App\Services\DeviceFingerprintService;
use App\Services\OnlineCodeService;
use App\Support\SchemaFeatures;
use App\Support\PostMarkAutoLogout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * Student-facing controller for marking attendance in an ONLINE session.
 *
 * In contrast with AttendanceController (which handles in-person GPS /
 * QR / Wi-Fi flows), online sessions:
 *   - have no spatial check,
 *   - have no QR / screenshot / OCR pipeline,
 *   - accept a single 4-digit rolling code (rotates every
 *     config('attendance.online_code_rotation_seconds') seconds).
 *
 * After a successful mark we ALSO capture per-submission device
 * telemetry and run AttendanceRiskService. Both are best-effort and
 * never block the attendance — see PART 7 / PART 12 of the spec.
 *
 * Existing plumbing reused (zero parallel system):
 *  - attendance_sessions row with mode = 'online'
 *  - attendances row written exactly the same way as in-person modes
 *  - AuditLogService::record(MARK_CREATED)
 */
class OnlineAttendanceController extends Controller
{
    public function __construct(
        private readonly OnlineCodeService $codes,
        private readonly DeviceFingerprintService $deviceLogs,
        private readonly AttendanceRiskService $risk,
    ) {
    }

    private function getStudent(Request $request): ?Student
    {
        $id = $request->session()->get('student_id');

        return $id ? Student::find($id) : null;
    }

    /**
     * GET /web/attendance/{course}/online — show the code-entry page.
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

        // Has the student already marked? Short-circuit the form into a
        // "you're already in" state rather than rendering an input that
        // would just bounce.
        $alreadyMarked = Attendance::query()
            ->where('student_id', $student->id)
            ->where('attendance_session_id', $session->id)
            ->where('status', 'present')
            ->exists();

        return view('attendance.online', [
            'course'        => $course,
            'session'       => $session,
            'student'       => $student,
            'expiresAt'     => $session->expires_at,
            'codeLength'    => $this->codes->codeLength(),
            'alreadyMarked' => $alreadyMarked,
        ]);
    }

    /**
     * POST /web/attendance/{course}/online/code — student typed the
     * current rolling code.
     */
    public function submitCode(Request $request, Course $course): JsonResponse
    {
        $student = $this->getStudent($request);
        if (! $student) {
            return response()->json(['ok' => false, 'message' => 'Sign in to mark attendance.'], 401);
        }

        $session = $this->resolveActiveOnlineSession($course, $student);
        if (! $session) {
            return response()->json([
                'ok'      => false,
                'message' => 'Online session is not active or has ended.',
                'expired' => true,
            ], 410);
        }

        $validated = $request->validate([
            'code'   => 'required|string|max:16',
            // Optional client telemetry block. Always accepted, never required —
            // a stripped browser without JS just doesn't supply it, attendance
            // still gets marked. See PART 7.
            'client' => 'sometimes|array',
        ]);

        // PART 7: validation MUST NEVER hide attendance from a legitimate
        // student. The only hard checks are: session active + code is valid.
        // Risk scoring / device logging happen AFTER the attendance row.
        if (! $this->codes->validate((string) $validated['code'], $session)) {
            return response()->json([
                'ok'      => false,
                'message' => 'That code is not valid for the current session.',
            ], 422);
        }

        // Idempotent insert: if the student already marked, return success
        // without re-inserting.
        $existing = Attendance::query()
            ->where('student_id', $student->id)
            ->where('attendance_session_id', $session->id)
            ->first();

        if ($existing !== null) {
            if ($existing->status !== 'present') {
                $existing->update(['status' => 'present', 'attendance_time' => now()]);
            }

            PostMarkAutoLogout::arm($request);

            return response()->json([
                'ok'       => true,
                'message'  => 'You are already marked present for this online session.',
                'redirect' => route('web.attendance.success', ['course' => $course->id]),
            ]);
        }

        // Persist the attendance — single source of truth that the
        // student was present. NOTHING below this point can block it.
        try {
            $attendance = DB::transaction(function () use ($student, $session, $course, $request) {
                $row = Attendance::create([
                    'student_id'            => $student->id,
                    'course_id'             => $course->id,
                    'attendance_session_id' => $session->id,
                    'attendance_week_id'    => $session->attendance_week_id,
                    'status'                => 'present',
                    'attendance_time'       => now(),
                    'device_ip'             => (string) $request->ip(),
                    'user_agent'            => mb_substr((string) $request->userAgent(), 0, 480),
                    'synced'                => true,
                ]);

                AuditLogService::record(AuditLogService::MARK_CREATED, [
                    'request'               => $request,
                    'course_id'             => (int) $course->id,
                    'class_id'              => $student->class_id ? (int) $student->class_id : null,
                    'attendance_session_id' => (int) $session->id,
                    'subject_type'          => 'attendance',
                    'subject_id'            => null,
                    'payload'               => [
                        'channel'      => 'online_code',
                        'student_id'   => (int) $student->id,
                        'index_number' => (string) $student->index_number,
                    ],
                ]);

                return $row;
            });
        } catch (\Throwable $e) {
            Log::error('online-attendance: persist failed', [
                'student_id' => $student->id,
                'session_id' => $session->id,
                'error'      => $e->getMessage(),
            ]);

            return response()->json([
                'ok'      => false,
                'message' => 'We could not save your attendance. Please try again.',
            ], 500);
        }

        // PART 8 / 9 — capture device telemetry (best-effort).
        $deviceLog = $this->deviceLogs->record(
            $request,
            $student,
            $session,
            isset($validated['client']) && is_array($validated['client']) ? $validated['client'] : []
        );

        // PART 10 / 11 / 12 — risk scoring (NEVER blocks).
        $this->risk->score($attendance, $student, $session, $deviceLog);

        PostMarkAutoLogout::arm($request);

        return response()->json([
            'ok'       => true,
            'message'  => 'Marked present for this online lecture.',
            'redirect' => route('web.attendance.success', ['course' => $course->id]),
        ]);
    }

    /**
     * Find the live online session for this course/student. Returns null
     * if none, or if the session has expired (in which case is_active is
     * flipped to false once so subsequent requests skip straight to the
     * "ended" branch).
     */
    private function resolveActiveOnlineSession(Course $course, Student $student): ?AttendanceSession
    {
        $session = AttendanceSession::query()
            ->where('course_id', $course->id)
            ->where('mode', 'online')
            ->where('is_active', true)
            ->when((bool) $student->class_id, function ($q) use ($student) {
                if (SchemaFeatures::hasAttendanceSessionsClassId()) {
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

        if ($session->expires_at && $session->expires_at->isPast()) {
            try {
                $session->is_active = false;
                $session->saveQuietly();
            } catch (\Throwable $e) {
                Log::warning('online-attendance: auto-close failed', [
                    'session_id' => $session->id,
                    'error'      => $e->getMessage(),
                ]);
            }

            return null;
        }

        return $session;
    }
}
