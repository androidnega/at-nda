<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceSession;
use App\Models\Course;
use App\Models\Student;
use App\Services\Api\ClassRepApiService;
use App\Support\ApiEnvelope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * REST-style class-rep routes with {@see ApiEnvelope} (legacy /rep/* unchanged).
 */
class ClassRepRestController extends Controller
{
    public function __construct(
        private readonly ClassRepApiService $classRepApi,
    ) {}

    /**
     * GET|POST /api/class-rep/dashboard — credentials in JSON body (POST) or query (GET).
     */
    public function dashboard(Request $request): JsonResponse
    {
        $student = $this->classRepApi->authenticateFlexible($request);
        if ($student instanceof JsonResponse) {
            return $student;
        }

        $dto = $this->classRepApi->buildDashboard($student);

        return ApiEnvelope::success($dto->toArray(), 'Dashboard loaded');
    }

    /**
     * GET|POST /api/class-rep/students
     */
    public function students(Request $request): JsonResponse
    {
        $student = $this->classRepApi->authenticateFlexible($request);
        if ($student instanceof JsonResponse) {
            return $student;
        }

        $list = $this->classRepApi->studentsPayload($student);

        return ApiEnvelope::success([
            'students' => $list,
            'count' => count($list),
        ], 'Students loaded');
    }

    /**
     * GET|POST /api/class-rep/student-detail — body/query: index_number, password, student_id.
     */
    public function studentDetail(Request $request): JsonResponse
    {
        $rep = $this->classRepApi->authenticateFlexible($request);
        if ($rep instanceof JsonResponse) {
            return $rep;
        }

        $studentId = $request->input('student_id') ?? $request->query('student_id');
        if ($studentId === null || $studentId === '') {
            return ApiEnvelope::error('student_id is required', 422);
        }
        if (! is_numeric($studentId)) {
            return ApiEnvelope::error('student_id must be an integer', 422);
        }

        $target = Student::query()->find((int) $studentId);
        if ($target === null) {
            return ApiEnvelope::error('Student not found', 404);
        }

        $classIds = $rep->repManagedClassIds();
        if (! $target->class_id || ! $classIds->contains($target->class_id)) {
            return ApiEnvelope::error('You can only view students in your classes.', 403);
        }

        $payload = $this->classRepApi->buildStudentDetailPayload($target);

        return ApiEnvelope::success($payload, 'Student loaded');
    }

    /**
     * POST /api/class-rep/sessions/open — same body as /api/rep/sessions/open; response in envelope.
     */
    public function openSession(Request $request): JsonResponse
    {
        $student = $this->classRepApi->authenticate($request);
        if ($student instanceof JsonResponse) {
            return $student;
        }

        $inner = $this->classRepApi->openSession($request, $student);
        $payload = $inner->getData(true);
        if (! is_array($payload)) {
            return ApiEnvelope::error('Unexpected response', 500);
        }

        if (($payload['success'] ?? false) !== true) {
            return $inner;
        }

        return ApiEnvelope::success([
            'week_number' => $payload['week_number'] ?? null,
            'session' => $payload['session'] ?? null,
        ], (string) ($payload['message'] ?? 'Session opened'));
    }

    /**
     * POST /api/class-rep/sessions/close — body: index_number, password, session_id.
     */
    public function closeSession(Request $request): JsonResponse
    {
        $student = $this->classRepApi->authenticate($request);
        if ($student instanceof JsonResponse) {
            return $student;
        }

        $inner = $this->classRepApi->closeSessionById($request, $student);
        $payload = $inner->getData(true);
        if (! is_array($payload)) {
            return ApiEnvelope::error('Unexpected response', 500);
        }

        if (($payload['success'] ?? false) !== true) {
            return $inner;
        }

        return ApiEnvelope::success([
            'session_id' => $request->input('session_id'),
        ], (string) ($payload['message'] ?? 'Session closed'));
    }

    /**
     * POST /api/class-rep/sessions/extend
     * Body: session_id, additional_minutes
     */
    public function extendSession(Request $request): JsonResponse
    {
        $student = $this->classRepApi->authenticate($request);
        if ($student instanceof JsonResponse) {
            return $student;
        }

        $inner = $this->classRepApi->extendSessionById($request, $student);
        $payload = $inner->getData(true);
        if (! is_array($payload)) {
            return ApiEnvelope::error('Unexpected response', 500);
        }

        if (($payload['success'] ?? false) !== true) {
            return $inner;
        }

        return ApiEnvelope::success([
            'session_id' => $request->input('session_id'),
        ], (string) ($payload['message'] ?? 'Session extended'));
    }

    /**
     * POST /api/class-rep/sessions/prune-ghosts — delete ended sessions that
     * have zero attendance rows, scoped to courses in the rep's classes.
     *
     * Body (optional): course_id (limit to one course), days (only sessions
     * ended within N days), dry_run (preview only).
     *
     * Returns the deleted ids per course so the Flutter app can refresh
     * its local cache.
     */
    public function pruneGhostSessions(Request $request): JsonResponse
    {
        $rep = $this->classRepApi->authenticate($request);
        if ($rep instanceof JsonResponse) {
            return $rep;
        }

        $validated = $request->validate([
            'course_id' => 'nullable|integer|min:1',
            'days' => 'nullable|integer|min:1|max:3650',
            'dry_run' => 'nullable|boolean',
        ]);
        $dryRun = (bool) ($validated['dry_run'] ?? false);
        $days = isset($validated['days']) ? (int) $validated['days'] : null;
        $courseId = isset($validated['course_id']) ? (int) $validated['course_id'] : null;

        $classIds = $rep->repManagedClassIds()->all();
        if (empty($classIds)) {
            return ApiEnvelope::error('You do not manage any classes.', 403);
        }

        $allowedCourseIds = Course::query()
            ->whereIn('class_id', $classIds)
            ->pluck('id')
            ->map(fn ($v) => (int) $v)
            ->all();

        if ($courseId !== null) {
            if (! in_array($courseId, $allowedCourseIds, true)) {
                return ApiEnvelope::error('Course is not in your classes.', 403);
            }
            $allowedCourseIds = [$courseId];
        }

        if (empty($allowedCourseIds)) {
            return ApiEnvelope::success([
                'deleted' => 0,
                'sessions' => [],
                'dry_run' => $dryRun,
            ], 'Nothing to prune.');
        }

        $query = AttendanceSession::query()
            ->select(['id', 'course_id'])
            ->whereIn('course_id', $allowedCourseIds)
            ->whereRaw('COALESCE(end_time, expires_at) IS NOT NULL')
            ->whereRaw('COALESCE(end_time, expires_at) < ?', [now()])
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('attendances')
                    ->whereColumn('attendances.attendance_session_id', 'attendance_sessions.id');
            });

        if ($days !== null) {
            $query->whereRaw('COALESCE(end_time, expires_at) >= ?', [now()->subDays($days)]);
        }

        $rows = $query->get();
        $ids = $rows->pluck('id')->map(fn ($v) => (int) $v)->all();

        $perCourse = [];
        foreach ($rows as $row) {
            $cid = (int) $row->course_id;
            $perCourse[$cid] = ($perCourse[$cid] ?? 0) + 1;
        }

        if ($ids === [] || $dryRun) {
            return ApiEnvelope::success([
                'deleted' => 0,
                'would_delete' => count($ids),
                'sessions' => $ids,
                'per_course' => $perCourse,
                'dry_run' => $dryRun,
            ], $dryRun ? 'Dry-run preview.' : 'Nothing to prune.');
        }

        AttendanceSession::query()->whereIn('id', $ids)->delete();

        return ApiEnvelope::success([
            'deleted' => count($ids),
            'sessions' => $ids,
            'per_course' => $perCourse,
            'dry_run' => false,
        ], 'Ghost sessions pruned.');
    }
}
