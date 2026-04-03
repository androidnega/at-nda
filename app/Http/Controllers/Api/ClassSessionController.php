<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\Student;
use App\Support\FlutterSessionFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

class ClassSessionController extends Controller
{
    /**
     * GET /api/class/active-session
     */
    public function activeSession(Request $request): JsonResponse
    {
        $student = $this->resolveAuthenticatedStudent($request);
        if ($student instanceof JsonResponse) {
            return $student;
        }

        if (! $student->class_id) {
            return response()->json(['message' => 'Student has no class assigned'], 422);
        }

        AttendanceSession::deactivateExpiredSessions();

        $session = AttendanceSession::query()
            ->with(['course.lecturer', 'course.venueRelation', 'lecturer', 'venue', 'attendanceWeek'])
            ->activeWithinTimeWindow()
            ->whereHas('course', fn ($q) => $q->where('class_id', (int) $student->class_id))
            ->latest('id')
            ->first();

        if (! $session) {
            return response()->json([
                'has_active_session' => false,
                'session' => null,
            ]);
        }

        $totalStudents = Student::query()->where('class_id', (int) $student->class_id)->count();
        $totalPresent = Attendance::query()
            ->where('attendance_session_id', $session->id)
            ->where('status', 'present')
            ->count();

        return response()->json([
            'has_active_session' => true,
            'session' => FlutterSessionFormatter::format($session),
            'total_students' => (int) $totalStudents,
            'total_present' => (int) $totalPresent,
            'start_time' => $session->start_time?->toIso8601String(),
        ]);
    }

    /**
     * GET /api/session/{id}/stats
     */
    public function stats(Request $request, AttendanceSession $session): JsonResponse
    {
        $student = $this->resolveAuthenticatedStudent($request);
        if ($student instanceof JsonResponse) {
            return $student;
        }

        $course = $session->course()->first();
        if (! $course) {
            return response()->json(['message' => 'Course not found'], 404);
        }

        if (! $student->class_id || (int) $student->class_id !== (int) $course->class_id) {
            return response()->json(['message' => 'Not allowed'], 403);
        }

        $totalStudents = Student::query()->where('class_id', (int) $course->class_id)->count();
        $totalPresent = Attendance::query()
            ->where('attendance_session_id', $session->id)
            ->where('status', 'present')
            ->count();

        return response()->json([
            'session_id' => $session->id,
            'total_students' => (int) $totalStudents,
            'total_present' => (int) $totalPresent,
            'total_absent' => (int) max($totalStudents - $totalPresent, 0),
        ]);
    }

    private function resolveAuthenticatedStudent(Request $request): Student|JsonResponse
    {
        $bearer = $request->bearerToken();
        if ($bearer) {
            $pat = PersonalAccessToken::findToken($bearer);
            if (! $pat || ! $pat->tokenable instanceof Student) {
                return response()->json(['message' => 'Invalid or expired token'], 401);
            }

            return $pat->tokenable;
        }

        $index = $request->input('index_number') ?? $request->query('index_number');
        $password = $request->input('password') ?? $request->query('password');
        if (! is_string($index) || trim($index) === '' || ! is_string($password) || trim($password) === '') {
            return response()->json(['message' => 'index_number and password are required'], 422);
        }

        $student = Student::findByIndex($index);
        if (! $student || ! $this->validatePassword((string) $password, $student->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        return $student;
    }

    private function validatePassword(string $input, ?string $stored): bool
    {
        if (empty($stored)) {
            return false;
        }
        if (str_starts_with($stored, '$2y$') || str_starts_with($stored, '$2a$')) {
            return Hash::check($input, $stored);
        }

        return $input === $stored;
    }
}

