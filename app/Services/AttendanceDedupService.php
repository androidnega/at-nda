<?php

namespace App\Services;

use App\Models\Attendance;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * One-time / on-demand cleanup of duplicate attendance rows that exist
 * for the same (student, course, week). These come from older sessions
 * that were closed and reopened on the same day before the
 * `openOrReopenForClass()` idempotency change shipped — every reopen
 * inserted a fresh row instead of reusing the existing one.
 *
 * Strategy:
 *   - Group attendance rows by (student_id, course_id, attendance_week_id)
 *   - For any group with more than one row, keep the earliest insertion
 *     (smallest id, i.e. the original mark) and delete the rest.
 *   - Each deletion fires the standard Attendance.deleting hook, which
 *     records an `attendance_deletions` row, and an `audit_logs` entry
 *     captures the cleanup reason.
 */
class AttendanceDedupService
{
    /**
     * @param  array{
     *     dry_run?: bool,
     *     course_id?: int|null,
     *     attendance_week_id?: int|null,
     *     class_id?: int|null,
     *     actor_id?: int|null,
     *     actor_role?: string|null,
     *     actor_name?: string|null,
     *     reason?: string|null
     * }  $opts
     * @return array{groups_scanned: int, duplicates_removed: int, kept: int, sample: array<int, array<string, mixed>>}
     */
    public function run(array $opts = []): array
    {
        $dryRun = (bool) ($opts['dry_run'] ?? false);
        $courseFilter = $opts['course_id'] ?? null;
        $weekFilter = $opts['attendance_week_id'] ?? null;
        $classFilter = $opts['class_id'] ?? null;
        $reason = (string) ($opts['reason'] ?? 'auto_dedupe_weekly_marks');

        $groupsQuery = Attendance::query()
            ->select([
                'student_id',
                'course_id',
                'attendance_week_id',
                DB::raw('COUNT(*) as row_count'),
                DB::raw('MIN(id) as keep_id'),
            ])
            ->whereNotNull('attendance_week_id')
            ->groupBy('student_id', 'course_id', 'attendance_week_id')
            ->having('row_count', '>', 1);

        if ($courseFilter !== null) {
            $groupsQuery->where('course_id', (int) $courseFilter);
        }
        if ($weekFilter !== null) {
            $groupsQuery->where('attendance_week_id', (int) $weekFilter);
        }
        if ($classFilter !== null) {
            $groupsQuery->whereExists(function ($q) use ($classFilter) {
                $q->select(DB::raw(1))
                    ->from('students')
                    ->whereColumn('students.id', 'attendances.student_id')
                    ->where('students.class_id', (int) $classFilter);
            });
        }

        $groups = $groupsQuery->get();
        $groupsScanned = $groups->count();
        $duplicatesRemoved = 0;
        $kept = 0;
        $sample = [];

        foreach ($groups as $group) {
            $keepId = (int) $group->keep_id;
            $kept++;

            $deletableQuery = Attendance::query()
                ->where('student_id', (int) $group->student_id)
                ->where('course_id', (int) $group->course_id)
                ->where('attendance_week_id', (int) $group->attendance_week_id)
                ->where('id', '!=', $keepId);

            $rowsToDelete = $deletableQuery->get(['id', 'attendance_session_id', 'attendance_time']);
            $deletedIds = $rowsToDelete->pluck('id')->all();

            if (count($sample) < 25) {
                $sample[] = [
                    'student_id' => (int) $group->student_id,
                    'course_id' => (int) $group->course_id,
                    'attendance_week_id' => (int) $group->attendance_week_id,
                    'kept_id' => $keepId,
                    'removed_ids' => $deletedIds,
                ];
            }

            if ($dryRun || $deletedIds === []) {
                $duplicatesRemoved += count($deletedIds);
                continue;
            }

            // Use Eloquent ::destroy so the model's deleting hook fires
            // for every row (it writes an attendance_deletions audit
            // entry per row).
            try {
                Attendance::destroy($deletedIds);
            } catch (\Throwable $e) {
                Log::error('AttendanceDedupService: failed to delete duplicates', [
                    'student_id' => $group->student_id,
                    'course_id' => $group->course_id,
                    'attendance_week_id' => $group->attendance_week_id,
                    'ids' => $deletedIds,
                    'error' => $e->getMessage(),
                ]);
                continue;
            }

            $duplicatesRemoved += count($deletedIds);

            AuditLogService::record(AuditLogService::MARK_DELETED, [
                'actor_id' => $opts['actor_id'] ?? null,
                'actor_role' => $opts['actor_role'] ?? 'system',
                'actor_name' => $opts['actor_name'] ?? 'attendance:dedupe',
                'subject_type' => 'student',
                'subject_id' => (int) $group->student_id,
                'course_id' => (int) $group->course_id,
                'payload' => [
                    'reason' => $reason,
                    'attendance_week_id' => (int) $group->attendance_week_id,
                    'kept_attendance_id' => $keepId,
                    'removed_attendance_ids' => $deletedIds,
                ],
            ]);
        }

        return [
            'groups_scanned' => $groupsScanned,
            'duplicates_removed' => $duplicatesRemoved,
            'kept' => $kept,
            'sample' => $sample,
        ];
    }
}
