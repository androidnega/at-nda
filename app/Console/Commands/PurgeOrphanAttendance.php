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
                            {--dry-run : Show how many rows would be deleted without deleting them}';

    protected $description = 'Delete attendance rows and sessions whose backing week was already cleared by admin.';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');

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

        if ($dry) {
            $this->info('Dry run — nothing deleted. Re-run without --dry-run to purge.');

            return self::SUCCESS;
        }

        if ($orphanCount === 0 && $orphanSessionCount === 0) {
            $this->info('Nothing to purge.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($orphanAttendance, $orphanSessions): void {
            foreach ($orphanAttendance->cursor() as $row) {
                $row->delete();
            }
            $orphanSessions->delete();
        });

        $this->info('Purged '.$orphanCount.' attendance row(s) and '.$orphanSessionCount.' session(s).');

        return self::SUCCESS;
    }
}
