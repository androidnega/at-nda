<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use App\Models\AttendanceSession;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Cleans up stale attendance data left behind by older resets / cancellations.
 *
 * Before scopeActiveWeeksOnly() existed, some admin resets could leave:
 *   - Attendance rows whose attendance_week_id points at a deleted week.
 *   - AttendanceSession rows whose attendance_week_id points at a deleted
 *     week (these are what caused the "missed class" rows on student
 *     history even after a class had been cleared).
 *
 * The dashboards now hide both at query time, but the rows still exist in
 * the DB and clutter raw exports. Run this command once on the server to
 * actually delete them.
 */
class PurgeOrphanAttendance extends Command
{
    protected $signature = 'attendance:purge-orphans
                            {--dry-run : Show how many rows would be deleted without deleting them}
                            {--cross-class : Also delete rep auto-marks that landed in another class\'s session}
                            {--duplicate-empty : Also delete extra sessions for the same (course, class, week) that nobody attended}
                            {--empty-ended : Also delete ENDED sessions that have zero attendance rows (ghost sessions)}
                            {--course= : Restrict --empty-ended to a single course id}
                            {--days= : With --empty-ended, only target sessions ended within the last N days}';

    protected $description = 'Delete attendance rows and sessions whose backing week was already cleared by admin.';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $alsoCrossClass = (bool) $this->option('cross-class');
        $alsoDuplicateEmpty = (bool) $this->option('duplicate-empty');
        $alsoEmptyEnded = (bool) $this->option('empty-ended');
        $courseFilter = $this->option('course');
        $courseFilter = $courseFilter !== null && $courseFilter !== '' ? (int) $courseFilter : null;
        $daysFilter = $this->option('days');
        $daysFilter = $daysFilter !== null && $daysFilter !== '' ? max(1, (int) $daysFilter) : null;

        // 1. Attendance rows whose attendance_week_id no longer points at
        //    a real attendance_weeks row.
        $orphanAttendance = Attendance::query()
            ->whereNotNull('attendance_week_id')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('attendance_weeks')
                    ->whereColumn('attendance_weeks.id', 'attendances.attendance_week_id');
            });
        $orphanCount = (clone $orphanAttendance)->count();

        // 2. Attendance sessions whose attendance_week_id no longer points
        //    at a real attendance_weeks row. These are what generated stale
        //    "Absent / missed class" rows on the student history.
        $orphanSessions = AttendanceSession::query()
            ->whereNotNull('attendance_week_id')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('attendance_weeks')
                    ->whereColumn('attendance_weeks.id', 'attendance_sessions.attendance_week_id');
            });
        $orphanSessionCount = (clone $orphanSessions)->count();

        $this->line('Found '.$orphanCount.' orphan attendance row(s).');
        $this->line('Found '.$orphanSessionCount.' orphan session(s).');

        // 3. (Optional) Rep auto-marks where the session was opened for
        //    a class the rep does NOT belong to. The previous auto-mark
        //    logic stamped present on every rep across all classes
        //    assigned to a shared course, polluting other classes'
        //    counters. Detect them via the new attendance_sessions.class_id
        //    when present, otherwise via the attendance_weeks.class_id
        //    fallback.
        $crossClassQuery = null;
        $crossClassCount = 0;
        if ($alsoCrossClass) {
            $hasSessionClassId = \App\Support\SchemaFeatures::hasAttendanceSessionsClassId();
            $hasWeekClassId = \App\Support\SchemaFeatures::hasAttendanceWeeksClassId();
            if ($hasSessionClassId || $hasWeekClassId) {
                $crossClassQuery = Attendance::query()
                    ->whereNotNull('attendance_session_id')
                    ->whereExists(function ($q) use ($hasSessionClassId, $hasWeekClassId) {
                        $q->select(DB::raw(1))
                            ->from('attendance_sessions as s')
                            ->whereColumn('s.id', 'attendances.attendance_session_id')
                            ->join('students as st', 'st.id', '=', 'attendances.student_id');
                        if ($hasSessionClassId) {
                            $q->whereNotNull('s.class_id')
                                ->whereColumn('s.class_id', '!=', 'st.class_id');
                        } else {
                            $q->join('attendance_weeks as w', 'w.id', '=', 's.attendance_week_id')
                                ->whereNotNull('w.class_id')
                                ->whereColumn('w.class_id', '!=', 'st.class_id');
                        }
                    });
                $crossClassCount = (clone $crossClassQuery)->count();
                $this->line('Found '.$crossClassCount.' cross-class rep auto-mark(s).');
            } else {
                $this->warn('Schema has no class_id on attendance_sessions or attendance_weeks — skipping --cross-class.');
            }
        }

        // 4. Sessions that were closed (or replaced) before anyone marked
        //    attendance for them. The history builder turns each empty
        //    session into a separate "missed class" row — collapse repeats
        //    for the same (course, class, week) here.
        $duplicateEmptyIds = [];
        if ($alsoDuplicateEmpty) {
            $duplicateEmptyIds = $this->collectDuplicateEmptySessionIds();
            $this->line('Found '.count($duplicateEmptyIds).' duplicate empty session(s).');
        }

        // 5. Hard cleanup: every ENDED session that has zero attendance rows.
        //    These are "ghost" sessions — rep opened then nobody marked.
        //    They inflate "missed" counters on the Flutter app.
        $emptyEndedIds = [];
        if ($alsoEmptyEnded) {
            $emptyEndedIds = $this->collectEmptyEndedSessionIds($courseFilter, $daysFilter);
            $this->line('Found '.count($emptyEndedIds).' empty ended session(s).');
        }

        if ($dry) {
            $this->info('Dry run — nothing deleted. Re-run without --dry-run to purge.');

            return self::SUCCESS;
        }

        if ($orphanCount === 0
            && $orphanSessionCount === 0
            && $crossClassCount === 0
            && $duplicateEmptyIds === []
            && $emptyEndedIds === []
        ) {
            $this->info('Nothing to purge.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($orphanAttendance, $orphanSessions, $crossClassQuery, $duplicateEmptyIds, $emptyEndedIds): void {
            foreach ($orphanAttendance->cursor() as $row) {
                $row->delete();
            }
            $orphanSessions->delete();
            if ($crossClassQuery !== null) {
                foreach ($crossClassQuery->cursor() as $row) {
                    $row->delete();
                }
            }
            if ($duplicateEmptyIds !== []) {
                AttendanceSession::query()->whereIn('id', $duplicateEmptyIds)->delete();
            }
            if ($emptyEndedIds !== []) {
                AttendanceSession::query()->whereIn('id', $emptyEndedIds)->delete();
            }
        });

        $this->info(
            'Purged '.$orphanCount.' attendance row(s), '
            .$orphanSessionCount.' session(s)'
            .($alsoCrossClass ? ', '.$crossClassCount.' cross-class auto-mark(s)' : '')
            .($alsoDuplicateEmpty ? ', '.count($duplicateEmptyIds).' duplicate empty session(s)' : '')
            .($alsoEmptyEnded ? ', '.count($emptyEndedIds).' empty ended session(s)' : '')
            .'.'
        );

        return self::SUCCESS;
    }

    /**
     * Ended sessions with zero attendance rows. Optionally restrict to a
     * course id and / or to sessions whose end time falls within the last
     * N days. Never touches still-active sessions — only closed ghosts.
     *
     * @return list<int>
     */
    private function collectEmptyEndedSessionIds(?int $courseId, ?int $days): array
    {
        $query = AttendanceSession::query()
            ->select('id')
            ->whereRaw('COALESCE(end_time, expires_at) IS NOT NULL')
            ->whereRaw('COALESCE(end_time, expires_at) < ?', [now()])
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('attendances')
                    ->whereColumn('attendances.attendance_session_id', 'attendance_sessions.id');
            });

        if ($courseId !== null && $courseId > 0) {
            $query->where('course_id', $courseId);
        }
        if ($days !== null && $days > 0) {
            $query->whereRaw('COALESCE(end_time, expires_at) >= ?', [now()->subDays($days)]);
        }

        return $query->pluck('id')->map(fn ($v) => (int) $v)->all();
    }

    /**
     * Sessions that nobody marked attendance for, where another session for
     * the same (course_id, attendance_week_id) already exists. The newest
     * row in each group is kept just in case it's still live; everything
     * older is dropped.
     *
     * @return list<int>
     */
    private function collectDuplicateEmptySessionIds(): array
    {
        $sessions = AttendanceSession::query()
            ->select(['id', 'course_id', 'attendance_week_id', 'created_at'])
            ->whereNotNull('attendance_week_id')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('attendances')
                    ->whereColumn('attendances.attendance_session_id', 'attendance_sessions.id');
            })
            ->orderBy('attendance_week_id')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $byGroup = [];
        $ids = [];
        foreach ($sessions as $row) {
            $key = (int) $row->course_id.'-'.(int) $row->attendance_week_id;
            if (! isset($byGroup[$key])) {
                $byGroup[$key] = true;
                // First in the desc-sorted list per group → most recent, keep it.
                continue;
            }
            $ids[] = (int) $row->id;
        }

        return array_values($ids);
    }
}
