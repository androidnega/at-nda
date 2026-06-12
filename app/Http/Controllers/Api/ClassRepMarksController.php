<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\Course;
use App\Models\Student;
use App\Services\Api\ClassRepApiService;
use App\Services\AuditLogService;
use App\Support\ApiEnvelope;
use App\Support\RepCourseAccess;
use App\Support\SchemaFeatures;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Class-rep manual attendance marking for the mobile app.
 *
 * Two endpoints:
 *   - POST /api/class-rep/sessions/roster — per-student status for
 *     an active session (lets the mobile UI show who's marked).
 *   - POST /api/class-rep/marks           — mark a single student
 *     present/late/absent. Idempotent on attendance_uuid so the
 *     offline outbox can retry safely.
 *
 * Both endpoints accept either a Sanctum bearer or an
 * index_number+password JSON body, exactly like the other
 * /api/class-rep/* endpoints.
 */
class ClassRepMarksController extends Controller
{
    public function __construct(
        private readonly ClassRepApiService $classRepApi,
    ) {}

    /**
     * POST /api/class-rep/sessions/roster
     *
     * Body: { session_id, index_number?, password? }
     */
    public function roster(Request $request): JsonResponse
    {
        $rep = $this->classRepApi->authenticateFlexible($request);
        if ($rep instanceof JsonResponse) {
            return $rep;
        }

        $validated = $request->validate([
            'session_id' => 'required|integer|min:1',
        ]);

        $session = AttendanceSession::query()->find((int) $validated['session_id']);
        if (! $session) {
            return ApiEnvelope::error('Session not found.', 404);
        }

        $course = Course::query()->find((int) $session->course_id);
        if (! $course) {
            return ApiEnvelope::error('Course not found for this session.', 404);
        }

        // The rep must manage at least one class assigned to this
        // course; otherwise this isn't their session to see.
        if (! RepCourseAccess::canAccessCourse($rep, $course)) {
            return ApiEnvelope::error('You do not manage a class for this course.', 403);
        }

        $sessionClassId = SchemaFeatures::hasAttendanceSessionsClassId()
            ? (is_numeric($session->class_id ?? null) ? (int) $session->class_id : null)
            : null;
        // Prefer the session's pinned class when present (pivot
        // schema). Otherwise fall back to the first class the rep
        // shares with the course.
        $rosterClassId = $sessionClassId
            ?: (RepCourseAccess::scopedClassIdsForCourse($rep, $course)[0] ?? null);

        if (! $rosterClassId) {
            return ApiEnvelope::error('Unable to resolve a class roster for this session.', 422);
        }

        $students = Student::query()
            ->where('class_id', $rosterClassId)
            ->orderBy('index_number')
            ->get(['id', 'index_number', 'full_name', 'first_name', 'middle_name', 'last_name', 'class_id', 'profile_image']);

        $marks = Attendance::query()
            ->where('attendance_session_id', $session->id)
            ->whereIn('student_id', $students->pluck('id'))
            ->get(['id', 'student_id', 'status', 'attendance_time', 'marked_manually_by_id', 'marked_manually_at']);
        $marksByStudent = $marks->keyBy(fn ($a) => (int) $a->student_id);

        $rosterPayload = $students->map(function (Student $s) use ($marksByStudent) {
            $row = $marksByStudent->get((int) $s->id);
            $status = $row?->status;
            $isPresent = $status !== null && Attendance::countsAsPresent($status);
            $markedBy = null;
            if ($row && (int) ($row->marked_manually_by_id ?? 0) > 0) {
                $markedBy = 'rep';
            } elseif ($row) {
                $markedBy = 'self';
            }

            return [
                'id' => (int) $s->id,
                'index_number' => (string) $s->index_number,
                'full_name' => $s->full_name ?? trim(implode(' ', array_filter([$s->first_name, $s->middle_name, $s->last_name]))),
                'status' => $status, // 'present' | 'late' | 'absent' | null
                'is_present' => $isPresent,
                'marked_at' => optional($row?->attendance_time ?? $row?->marked_manually_at)->toIso8601String(),
                'marked_by' => $markedBy, // 'rep' | 'self' | null
                'attendance_id' => $row ? (int) $row->id : null,
            ];
        })->values()->all();

        $presentCount = collect($rosterPayload)->where('is_present', true)->count();
        $absentCount = collect($rosterPayload)
            ->where('status', 'absent')
            ->count();
        $totalCount = count($rosterPayload);

        return ApiEnvelope::success([
            'session' => [
                'id' => (int) $session->id,
                'course_id' => (int) $session->course_id,
                'course_code' => $course->course_code,
                'course_name' => $course->course_name,
                'attendance_week_id' => $session->attendance_week_id ? (int) $session->attendance_week_id : null,
                'class_id' => $rosterClassId,
                'is_active' => (bool) ($session->is_active ?? false),
                'opens_at' => optional($session->start_time)->toIso8601String(),
                'closes_at' => optional($session->end_time ?? $session->expires_at ?? null)->toIso8601String(),
            ],
            'students' => $rosterPayload,
            'counts' => [
                'present' => $presentCount,
                'absent' => $absentCount,
                'unmarked' => max(0, $totalCount - $presentCount - $absentCount),
                'total' => $totalCount,
            ],
        ], 'Roster loaded');
    }

    /**
     * POST /api/class-rep/marks
     *
     * Body: {
     *   session_id, student_id, status?, reason?, attendance_uuid?,
     *   index_number?, password?
     * }
     */
    public function mark(Request $request): JsonResponse
    {
        $rep = $this->classRepApi->authenticateFlexible($request);
        if ($rep instanceof JsonResponse) {
            return $rep;
        }

        $validated = $request->validate([
            'session_id' => 'required|integer|min:1',
            'student_id' => 'required|integer|min:1',
            'status' => 'nullable|in:present,late,absent',
            'reason' => 'nullable|string|max:500',
            // Variable length: the client generates keys like
            // "att_<ts>_<rand>" — 64-char column on the server.
            'attendance_uuid' => 'nullable|string|min:8|max:64',
        ]);

        $status = strtolower((string) ($validated['status'] ?? 'present'));
        $reason = trim((string) ($validated['reason'] ?? ''));
        $uuid = $validated['attendance_uuid'] ?? null;

        $session = AttendanceSession::query()->find((int) $validated['session_id']);
        if (! $session) {
            return ApiEnvelope::error('Session not found.', 404);
        }
        if (! ($session->is_active ?? false)) {
            return ApiEnvelope::error('This session is closed. Reopen it before marking.', 409);
        }

        $course = Course::query()->find((int) $session->course_id);
        if (! $course) {
            return ApiEnvelope::error('Course not found for this session.', 404);
        }
        if (! RepCourseAccess::canAccessCourse($rep, $course)) {
            return ApiEnvelope::error('You do not manage a class for this course.', 403);
        }

        $student = Student::query()->find((int) $validated['student_id']);
        if (! $student) {
            return ApiEnvelope::error('Student not found.', 404);
        }

        // The student must be in one of the rep's classes for this
        // course — otherwise we'd let a rep mark someone in another
        // cohort.
        $allowedClassIds = RepCourseAccess::scopedClassIdsForCourse($rep, $course);
        if (! $student->class_id || ! in_array((int) $student->class_id, $allowedClassIds, true)) {
            return ApiEnvelope::error(
                'You can only mark students in your class for this course.',
                403,
            );
        }

        // Idempotency: the outbox sends the same attendance_uuid on
        // every retry. If we've already processed it, return the
        // existing row as a 200 with idempotent_replay: true so the
        // outbox transitions to "synced" without a duplicate INSERT.
        if ($uuid) {
            $existingByUuid = Attendance::query()
                ->where('attendance_uuid', $uuid)
                ->where('attendance_session_id', $session->id)
                ->first();
            if ($existingByUuid) {
                return ApiEnvelope::success(
                    $this->markedPayload($existingByUuid, $session, true),
                    'Already recorded (idempotent replay).',
                );
            }
        }

        // Overwrite guard — same rule as the web manual-mark flow.
        // If the student is already counted as present (present OR
        // late), do not flip them to absent or "re-mark" them.
        $existing = Attendance::query()
            ->where('student_id', $student->id)
            ->where('attendance_session_id', $session->id)
            ->first();
        if ($existing && Attendance::countsAsPresent($existing->status)) {
            $label = strtolower((string) $existing->status) === 'late' ? 'late' : 'present';

            return ApiEnvelope::error(
                $student->index_number.' is already marked '.$label.' for this session.',
                409,
                [
                    'code' => 'ALREADY_MARKED',
                    'existing_status' => $existing->status,
                    'attendance_id' => (int) $existing->id,
                ],
            );
        }

        // Resolve the rep's class id for this session so the
        // attendance row stays scoped to the right cohort when the
        // session itself doesn't pin one (legacy schema).
        $sessionClassId = SchemaFeatures::hasAttendanceSessionsClassId()
            ? (is_numeric($session->class_id ?? null) ? (int) $session->class_id : null)
            : null;
        $scopedClassId = $sessionClassId ?: (int) ($allowedClassIds[0] ?? $student->class_id);

        $created = false;
        try {
            DB::beginTransaction();

            $row = $existing ?? new Attendance;
            $created = ! $row->exists;

            $row->fill([
                'student_id' => (int) $student->id,
                'course_id' => (int) $course->id,
                'attendance_session_id' => (int) $session->id,
                'attendance_week_id' => $session->attendance_week_id ? (int) $session->attendance_week_id : null,
                'status' => $status,
                'attendance_time' => $row->attendance_time ?? Carbon::now(),
                'marked_manually_by_id' => (int) $rep->id,
                'manual_reason' => $reason !== '' ? $reason : 'Marked by rep on mobile',
                'marked_manually_at' => Carbon::now(),
                'device_ip' => (string) $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 480),
                'synced' => true,
            ]);

            if (SchemaFeatures::hasAttendanceSessionsClassId() && $scopedClassId) {
                $row->class_id = $scopedClassId;
            }

            if ($uuid && empty($row->attendance_uuid)) {
                $row->attendance_uuid = $uuid;
            }

            $row->save();

            // Audit trail (best-effort — never block the mark).
            try {
                AuditLogService::record(AuditLogService::MARK_MANUAL, [
                    'actor_id' => (int) $rep->id,
                    'actor_role' => 'rep',
                    'actor_name' => $rep->full_name ?? $rep->index_number,
                    'class_id' => $scopedClassId,
                    'course_id' => (int) $course->id,
                    'attendance_session_id' => (int) $session->id,
                    'subject_type' => 'attendance',
                    'subject_id' => (int) $row->id,
                    'ip' => $request->ip(),
                    'user_agent' => substr((string) $request->userAgent(), 0, 480),
                    'payload' => [
                        'student_id' => (int) $student->id,
                        'index_number' => $student->index_number,
                        'status' => $status,
                        'reason' => $reason,
                        'source' => 'mobile_app',
                        'created_via_mobile_app' => true,
                    ],
                ]);
            } catch (Throwable $e) {
                report($e);
            }

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('[REP-MARK] failed', [
                'session_id' => $session->id,
                'student_id' => $student->id,
                'error' => $e->getMessage(),
            ]);

            return ApiEnvelope::error('Could not save the mark. Please retry.', 500);
        }

        $row->refresh();

        return ApiEnvelope::success(
            $this->markedPayload($row, $session, false, $created),
            $created ? 'Student marked.' : 'Mark updated.',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function markedPayload(
        Attendance $row,
        AttendanceSession $session,
        bool $idempotentReplay,
        ?bool $created = null,
    ): array {
        $totalPresent = Attendance::query()
            ->where('attendance_session_id', $session->id)
            ->countedAsPresent()
            ->count();

        return [
            'attendance_id' => (int) $row->id,
            'attendance_uuid' => $row->attendance_uuid,
            'student_id' => (int) $row->student_id,
            'status' => $row->status,
            'attendance_time' => optional($row->attendance_time)->toIso8601String(),
            'marked_at' => optional($row->marked_manually_at)->toIso8601String(),
            'idempotent_replay' => $idempotentReplay,
            'created' => $created,
            'session_counts' => [
                'present' => $totalPresent,
            ],
        ];
    }
}
