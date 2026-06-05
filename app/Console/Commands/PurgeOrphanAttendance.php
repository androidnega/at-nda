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
                            {--cross-class : Also delete rep auto-marks that landed in another class\'s session}';

    protected $description = 'Delete attendance rows and sessions whose backing week was already cleared by admin.';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $alsoCrossClass = (bool) $this->option('cross-class');

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

        if ($dry) {
            $this->info('Dry run — nothing deleted. Re-run without --dry-run to purge.');

            return self::SUCCESS;
        }

        if ($orphanCount === 0 && $orphanSessionCount === 0 && $crossClassCount === 0) {
            $this->info('Nothing to purge.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($orphanAttendance, $orphanSessions, $crossClassQuery): void {
            foreach ($orphanAttendance->cursor() as $row) {
                $row->delete();
            }
            $orphanSessions->delete();
            if ($crossClassQuery !== null) {
                foreach ($crossClassQuery->cursor() as $row) {
                    $row->delete();
                }
            }
        });

        $this->info(
            'Purged '.$orphanCount.' attendance row(s), '
            .$orphanSessionCount.' session(s)'
            .($alsoCrossClass ? ', and '.$crossClassCount.' cross-class auto-mark(s)' : '')
            .'.'
        );

        return self::SUCCESS;
    }
}
