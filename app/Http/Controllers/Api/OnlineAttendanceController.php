<?php

namespace App\Http\Controllers\Api;

use App\Events\SessionLiveEvent;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\Course;
use App\Models\Student;
use App\Services\AttendanceRiskService;
use App\Services\AuditLogService;
use App\Services\DeviceFingerprintService;
use App\Services\OnlineCodeService;
use App\Support\PostMarkAutoLogout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * Mobile/API online attendance — rolling session code (mode = online).
 */
class OnlineAttendanceController extends Controller
{
    public function __construct(
        private readonly OnlineCodeService $codes,
        private readonly DeviceFingerprintService $deviceLogs,
        private readonly AttendanceRiskService $risk,
    ) {
    }

    /**
     * POST /api/attendance/online-code
     *
     * Body: { index_number, session_id, code, course_id?, client? }
     */
    public function submitCode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'index_number' => 'required|string',
            'session_id' => 'required|integer',
            'course_id' => ['nullable', 'integer', Rule::exists('courses', 'id')],
            'code' => 'required|string|max:16',
            'client' => 'sometimes|array',
            'client_meta' => 'sometimes|array',
        ]);

        $indexUpper = strtoupper(trim($validated['index_number']));
        $student = Student::whereRaw('UPPER(TRIM(index_number)) = ?', [$indexUpper])->first();
        if (! $student) {
            return response()->json(['message' => 'Student not found'], 404);
        }

        $authUser = $request->user();
        if ($authUser instanceof Student && (int) $authUser->id !== (int) $student->id) {
            return response()->json([
                'message' => 'You can only submit attendance for your own account.',
            ], 403);
        }

        $session = AttendanceSession::with('course')->find((int) $validated['session_id']);
        if (! $session || (string) $session->mode !== 'online') {
            return response()->json(['message' => 'Online session not found'], 404);
        }

        if (! $session->is_active || ! $session->isValid()) {
            return response()->json([
                'message' => 'Online session is not active or has ended.',
                'expired' => true,
            ], 410);
        }

        $course = $session->course ?? Course::find($session->course_id);
        if (! $course) {
            return response()->json(['message' => 'Course not found'], 404);
        }

        if (! empty($validated['course_id']) && (int) $validated['course_id'] !== (int) $course->id) {
            return response()->json(['message' => 'Session does not match course'], 422);
        }

        if (! $this->codes->validate((string) $validated['code'], $session)) {
            return response()->json([
                'message' => 'That code is not valid for the current session.',
            ], 422);
        }

        $client = isset($validated['client']) && is_array($validated['client'])
            ? $validated['client']
            : (isset($validated['client_meta']) && is_array($validated['client_meta']) ? $validated['client_meta'] : []);

        $existing = Attendance::query()
            ->where('student_id', $student->id)
            ->where('attendance_session_id', $session->id)
            ->first();

        if ($existing !== null) {
            if ($existing->status !== 'present') {
                $existing->update(['status' => 'present', 'attendance_time' => now()]);
            }

            $logoutSeconds = PostMarkAutoLogout::armForMobile($student, $session);

            return response()->json([
                'status' => 'already_marked',
                'already_marked' => true,
                'message' => 'You are already marked present for this online session.',
                'auto_logout_seconds' => $logoutSeconds,
            ]);
        }

        try {
            $attendance = DB::transaction(function () use ($student, $session, $course, $request) {
                $row = Attendance::create([
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
                        'channel' => 'online_code_api',
                        'student_id' => (int) $student->id,
                        'index_number' => (string) $student->index_number,
                    ],
                ]);

                return $row;
            });
        } catch (\Throwable $e) {
            Log::error('api online-attendance: persist failed', [
                'student_id' => $student->id,
                'session_id' => $session->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'We could not save your attendance. Please try again.',
            ], 500);
        }

        $deviceLog = $this->deviceLogs->record($request, $student, $session, $client);
        $this->risk->score($attendance, $student, $session, $deviceLog);

        $logoutSeconds = PostMarkAutoLogout::armForMobile($student, $session);

        $presentCount = Attendance::where('attendance_session_id', $session->id)
            ->where('status', 'present')
            ->count();
        try {
            event(new SessionLiveEvent($session->fresh(['course']), 'attendance_marked', ['present_count' => $presentCount]));
        } catch (\Throwable $e) {
            Log::warning('SessionLiveEvent dispatch failed (online api): '.$e->getMessage(), ['session_id' => $session->id]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Marked present for this online lecture.',
            'auto_logout_seconds' => $logoutSeconds,
        ]);
    }
}
