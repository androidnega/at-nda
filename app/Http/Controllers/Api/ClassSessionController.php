<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\Student;
use App\Support\FlutterSessionFormatter;
use App\Support\PasswordPolicy;
use App\Support\SchemaFeatures;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        $classId = (int) $student->class_id;

        $session = AttendanceSession::query()
            ->with(['course.lecturer', 'course.venueRelation', 'lecturer', 'venue', 'attendanceWeek'])
            ->activeWithinTimeWindow()
            ->where(function ($q) use ($classId) {
                // Modern path: AttendanceSession.class_id is pinned
                // when the session is opened — this is the source of
                // truth and correctly handles courses shared between
                // multiple classes via the course_class pivot.
                if (SchemaFeatures::hasAttendanceSessionsClassId()) {
                    $q->where('class_id', $classId);
                    $q->orWhere(function ($qq) use ($classId) {
                        $qq->whereNull('class_id')
                           ->whereHas('course', fn ($cq) => $cq->forManagedClasses([$classId]));
                    });
                } else {
                    // Legacy schema fallback (pre-class_id column).
                    $q->whereHas('course', fn ($cq) => $cq->forManagedClasses([$classId]));
                }
            })
            ->latest('id')
            ->first();

        if (! $session) {
            return response()->json([
                'has_active_session' => false,
                'session' => null,
            ]);
        }

        $totalStudents = Student::query()->where('class_id', $classId)->count();
        // "Present" on the dashboard must match how attendance is
        // counted everywhere else: both 'present' and 'late' status
        // values count as having shown up. Otherwise late marks make
        // the tile read e.g. "1 of 30" when the real number is higher.
        $totalPresent = Attendance::query()
            ->where('attendance_session_id', $session->id)
            ->countedAsPresent()
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
            ->countedAsPresent()
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
        if (! $student || ! PasswordPolicy::matches((string) $password, $student->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        return $student;
    }
}

