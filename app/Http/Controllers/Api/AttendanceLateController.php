<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceLateUnrecorded;
use App\Models\Course;
use App\Models\Student;
use App\Services\AuditLogService;
use App\Support\ApiEnvelope;
use App\Support\PasswordPolicy;
use App\Support\SchemaFeatures;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Class-rep / lecturer review endpoints for offline attendance that
 * arrived too late to land directly. Backs the "Awaiting lecturer
 * approval" workflow on the mobile sync status page.
 *
 * - GET    /api/attendance/late                    — list pending captures the caller can decide on
 * - POST   /api/attendance/late/{id}/approve       — create a real Attendance row
 * - POST   /api/attendance/late/{id}/deny          — mark denied
 */
class AttendanceLateController extends Controller
{
    /**
     * Resolve the authenticated student from the Sanctum bearer.
     * Returns null when no valid token is attached.
     */
    private function studentFromBearer(Request $request): ?Student
    {
        $bearer = $request->bearerToken();
        if ($bearer === null || $bearer === '') {
            return null;
        }
        $pat = PersonalAccessToken::findToken($bearer);
        if (! $pat || ! $pat->tokenable instanceof Student) {
            return null;
        }
        return $pat->tokenable;
    }

    /**
     * Fallback: index_number + password (legacy mobile path).
     */
    private function studentFromBody(Request $request): ?Student
    {
        $idx = trim((string) $request->input('index_number'));
        $pwd = (string) $request->input('password');
        if ($idx === '' || $pwd === '') {
            return null;
        }
        $stu = Student::findByIndex($idx);
        if (! $stu || ! PasswordPolicy::matches($pwd, $stu->password)) {
            return null;
        }
        return $stu;
    }

    private function authActor(Request $request): Student|JsonResponse
    {
        $stu = $this->studentFromBearer($request) ?? $this->studentFromBody($request);
        if ($stu === null) {
            return ApiEnvelope::error('Invalid credentials.', 401);
        }
        return $stu;
    }

    private function canDecideForCourse(Student $actor, int $courseId): bool
    {
        if ($actor->isClassRepForCourse($courseId)) {
            return true;
        }
        // Lecturer of the course can also approve / deny.
        $course = Course::find($courseId);
        if ($course && $course->lecturer_id && (int) $course->lecturer_id === (int) ($actor->lecturer_id ?? 0)) {
            return true;
        }
        return false;
    }

    /**
     * GET /api/attendance/late — pending late marks the caller can decide on.
     * Query: course_id (optional), include_decided (bool, default false).
     */
    public function index(Request $request): JsonResponse
    {
        if (! SchemaFeatures::hasAttendanceLateUnrecorded()) {
            return ApiEnvelope::success(['items' => [], 'count' => 0], 'Feature not deployed.');
        }
        $actor = $this->authActor($request);
        if ($actor instanceof JsonResponse) {
            return $actor;
        }

        $classIds = $actor->repManagedClassIds()->all();
        if (empty($classIds)) {
            return ApiEnvelope::error('You do not manage any classes.', 403);
        }

        $allowedCourseIds = Course::query()
            ->whereIn('class_id', $classIds)
            ->pluck('id')
            ->map(fn ($v) => (int) $v)
            ->all();

        $courseFilter = $request->query('course_id');
        if ($courseFilter !== null && $courseFilter !== '') {
            $courseFilter = (int) $courseFilter;
            if (! in_array($courseFilter, $allowedCourseIds, true)) {
                return ApiEnvelope::error('Course is not in your classes.', 403);
            }
            $allowedCourseIds = [$courseFilter];
        }

        $query = AttendanceLateUnrecorded::query()
            ->whereIn('course_id', $allowedCourseIds);

        if (! $request->boolean('include_decided', false)) {
            $query->where('decision', AttendanceLateUnrecorded::DECISION_PENDING);
        }

        $rows = $query
            ->with(['student:id,index_number,first_name,last_name', 'course:id,course_code,course_name'])
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        $items = $rows->map(function (AttendanceLateUnrecorded $r) {
            return [
                'id' => (int) $r->id,
                'attendance_uuid' => $r->attendance_uuid,
                'student_id' => (int) $r->student_id,
                'index_number' => $r->student?->index_number,
                'first_name' => $r->student?->first_name,
                'last_name' => $r->student?->last_name,
                'course_id' => $r->course_id,
                'course_code' => $r->course?->course_code,
                'course_name' => $r->course?->course_name,
                'attendance_session_id' => $r->attendance_session_id,
                'reason' => $r->reason,
                'captured_at' => $r->captured_at?->toIso8601String(),
                'sync_attempted_at' => $r->sync_attempted_at?->toIso8601String(),
                'decision' => $r->decision,
                'decided_at' => $r->decided_at?->toIso8601String(),
                'decision_notes' => $r->decision_notes,
            ];
        })->all();

        return ApiEnvelope::success([
            'items' => $items,
            'count' => count($items),
        ], 'Late captures loaded.');
    }

    /**
     * POST /api/attendance/late/{id}/approve
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        if (! SchemaFeatures::hasAttendanceLateUnrecorded()) {
            return ApiEnvelope::error('Feature not deployed.', 410);
        }
        $actor = $this->authActor($request);
        if ($actor instanceof JsonResponse) {
            return $actor;
        }
        $request->validate([
            'notes' => 'nullable|string|max:500',
        ]);

        $late = AttendanceLateUnrecorded::find($id);
        if (! $late) {
            return ApiEnvelope::error('Late capture not found.', 404);
        }
        if ($late->decision !== AttendanceLateUnrecorded::DECISION_PENDING) {
            return ApiEnvelope::error('Late capture already decided.', 409);
        }
        if (! $this->canDecideForCourse($actor, (int) $late->course_id)) {
            return ApiEnvelope::error('Not authorised for this course.', 403);
        }

        $payload = is_array($late->payload) ? $late->payload : [];
        $attendanceTime = isset($payload['timestamp'])
            ? Carbon::parse((string) $payload['timestamp'])
            : ($late->captured_at ?? now());

        $hasUuidColumn = SchemaFeatures::hasAttendanceUuid();

        try {
            $attendance = DB::transaction(function () use ($late, $payload, $attendanceTime, $hasUuidColumn, $actor) {
                // Re-check idempotency before insert.
                if ($hasUuidColumn && ! empty($late->attendance_uuid)) {
                    $byUuid = Attendance::where('attendance_uuid', $late->attendance_uuid)->first();
                    if ($byUuid !== null) {
                        return $byUuid;
                    }
                }
                $byPair = Attendance::query()
                    ->where('student_id', $late->student_id)
                    ->where('attendance_session_id', $late->attendance_session_id)
                    ->first();
                if ($byPair !== null) {
                    return $byPair;
                }

                $createPayload = [
                    'student_id' => $late->student_id,
                    'course_id' => $late->course_id,
                    'attendance_session_id' => $late->attendance_session_id,
                    'attendance_week_id' => $late->attendance_week_id,
                    'attendance_time' => $attendanceTime,
                    'status' => 'present',
                    'synced' => true,
                    'lat' => $payload['lat'] ?? $payload['latitude'] ?? null,
                    'lng' => $payload['lng'] ?? $payload['longitude'] ?? null,
                    'qr_code' => $payload['qr_code'] ?? $payload['session_token'] ?? null,
                    'device_ip' => $payload['device_ip'] ?? null,
                    'device_id' => $payload['device_id'] ?? null,
                    'user_agent' => 'late-approved',
                    'marked_manually_by_id' => $actor->id,
                    'manual_reason' => 'Late attendance approved by '.($actor->isClassRep() ? 'class rep' : 'lecturer'),
                    'marked_manually_at' => now(),
                ];
                if ($hasUuidColumn && ! empty($late->attendance_uuid)) {
                    $createPayload['attendance_uuid'] = $late->attendance_uuid;
                }
                return Attendance::create($createPayload);
            });
        } catch (\Throwable $e) {
            \Log::error('AttendanceLate.approve_failed', [
                'late_id' => $late->id,
                'error' => $e->getMessage(),
            ]);
            return ApiEnvelope::error('Could not approve: '.$e->getMessage(), 500);
        }

        $late->forceFill([
            'decision' => AttendanceLateUnrecorded::DECISION_APPROVED,
            'decided_at' => now(),
            'decided_by_user_id' => $actor->id,
            'decision_notes' => $request->input('notes'),
            'resulting_attendance_id' => $attendance->id,
        ])->save();

        // Write-once audit record. A logging failure must not surface
        // to the API caller — AuditLogService.record swallows its own
        // throwables, but wrap defensively anyway.
        try {
            AuditLogService::record(AuditLogService::LATE_APPROVED, [
                'request' => $request,
                'actor_id' => (int) $actor->id,
                'actor_role' => $actor->isClassRep() ? 'rep' : ($actor->lecturer_id ? 'lecturer' : 'student'),
                'actor_name' => trim(implode(' ', array_filter([$actor->first_name, $actor->middle_name ?? null, $actor->last_name]))),
                'class_id' => $actor->class_id,
                'course_id' => (int) $late->course_id,
                'attendance_session_id' => $late->attendance_session_id ? (int) $late->attendance_session_id : null,
                'subject_type' => Attendance::class,
                'subject_id' => (int) $attendance->id,
                'payload' => [
                    'late_id' => (int) $late->id,
                    'attendance_uuid' => $late->attendance_uuid,
                    'student_id' => (int) $late->student_id,
                    'reason' => $late->reason,
                    'notes' => $request->input('notes'),
                ],
            ]);
        } catch (\Throwable $e) {
            \Log::warning('AttendanceLate.audit_approve_failed', [
                'late_id' => (int) $late->id,
                'error' => $e->getMessage(),
            ]);
        }

        return ApiEnvelope::success([
            'late_id' => (int) $late->id,
            'attendance_id' => (int) $attendance->id,
            'attendance_uuid' => $late->attendance_uuid,
        ], 'Late attendance approved.');
    }

    /**
     * POST /api/attendance/late/{id}/deny
     */
    public function deny(Request $request, int $id): JsonResponse
    {
        if (! SchemaFeatures::hasAttendanceLateUnrecorded()) {
            return ApiEnvelope::error('Feature not deployed.', 410);
        }
        $actor = $this->authActor($request);
        if ($actor instanceof JsonResponse) {
            return $actor;
        }
        $request->validate([
            'notes' => 'nullable|string|max:500',
        ]);

        $late = AttendanceLateUnrecorded::find($id);
        if (! $late) {
            return ApiEnvelope::error('Late capture not found.', 404);
        }
        if ($late->decision !== AttendanceLateUnrecorded::DECISION_PENDING) {
            return ApiEnvelope::error('Late capture already decided.', 409);
        }
        if (! $this->canDecideForCourse($actor, (int) $late->course_id)) {
            return ApiEnvelope::error('Not authorised for this course.', 403);
        }

        $late->forceFill([
            'decision' => AttendanceLateUnrecorded::DECISION_DENIED,
            'decided_at' => now(),
            'decided_by_user_id' => $actor->id,
            'decision_notes' => $request->input('notes'),
        ])->save();

        try {
            AuditLogService::record(AuditLogService::LATE_DENIED, [
                'request' => $request,
                'actor_id' => (int) $actor->id,
                'actor_role' => $actor->isClassRep() ? 'rep' : ($actor->lecturer_id ? 'lecturer' : 'student'),
                'actor_name' => trim(implode(' ', array_filter([$actor->first_name, $actor->middle_name ?? null, $actor->last_name]))),
                'class_id' => $actor->class_id,
                'course_id' => (int) $late->course_id,
                'attendance_session_id' => $late->attendance_session_id ? (int) $late->attendance_session_id : null,
                'subject_type' => AttendanceLateUnrecorded::class,
                'subject_id' => (int) $late->id,
                'payload' => [
                    'late_id' => (int) $late->id,
                    'attendance_uuid' => $late->attendance_uuid,
                    'student_id' => (int) $late->student_id,
                    'reason' => $late->reason,
                    'notes' => $request->input('notes'),
                ],
            ]);
        } catch (\Throwable $e) {
            \Log::warning('AttendanceLate.audit_deny_failed', [
                'late_id' => (int) $late->id,
                'error' => $e->getMessage(),
            ]);
        }

        return ApiEnvelope::success([
            'late_id' => (int) $late->id,
            'attendance_uuid' => $late->attendance_uuid,
        ], 'Late attendance denied.');
    }

    /**
     * GET /api/attendance/late/status/{uuid}
     *
     * Mobile clients call this to learn whether a Quarantined row was
     * eventually approved or denied (so the local row can transition to
     * Synced / Rejected without polling all marks).
     */
    public function statusByUuid(Request $request, string $uuid): JsonResponse
    {
        if (! SchemaFeatures::hasAttendanceLateUnrecorded()) {
            return ApiEnvelope::error('Feature not deployed.', 410);
        }
        $actor = $this->authActor($request);
        if ($actor instanceof JsonResponse) {
            return $actor;
        }

        $late = AttendanceLateUnrecorded::where('attendance_uuid', $uuid)->first();
        if (! $late) {
            return ApiEnvelope::error('Not found.', 404);
        }
        if ($actor instanceof Student && (int) $actor->id !== (int) $late->student_id) {
            // Only the owning student (or staff via dashboard) can poll status.
            if (! $this->canDecideForCourse($actor, (int) $late->course_id)) {
                return ApiEnvelope::error('Not authorised.', 403);
            }
        }

        return ApiEnvelope::success([
            'attendance_uuid' => $late->attendance_uuid,
            'decision' => $late->decision,
            'resulting_attendance_id' => $late->resulting_attendance_id,
            'reason' => $late->reason,
            'decided_at' => $late->decided_at?->toIso8601String(),
        ], 'Late capture status.');
    }
}
