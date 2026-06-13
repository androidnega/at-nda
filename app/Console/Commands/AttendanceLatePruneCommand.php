<?php

namespace App\Console\Commands;

use App\Models\AttendanceLateUnrecorded;
use App\Support\SchemaFeatures;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Retention sweep for `attendance_late_unrecorded`.
 *
 * Pending rows live forever — they are unresolved business and the
 * rep / lecturer still owns the decision. Approved and denied rows
 * older than the cutoff (default 60 days) are deleted; the decision is
 * already preserved on the resulting `attendances` row (for approves)
 * or in `audit_logs` (for both approves and denies, see
 * AttendanceLateController and AuditLogService::LATE_APPROVED /
 * LATE_DENIED), so removing them does not lose audit trail.
 *
 * Examples:
 *   php artisan attendance:late:prune                  # default 60 day cutoff
 *   php artisan attendance:late:prune --days=90        # custom cutoff
 *   php artisan attendance:late:prune --dry-run        # report counts only
 *   php artisan attendance:late:prune --chunk=500      # batch delete size
 */
class AttendanceLatePruneCommand extends Command
{
    protected $signature = 'attendance:late:prune
                            {--days=60 : Delete decided rows decided_at older than N days}
                            {--chunk=500 : Number of rows per delete batch}
                            {--dry-run : Report what would be deleted, then exit}';

    protected $description = 'Delete approved/denied attendance_late_unrecorded rows older than the cutoff. Pending rows are always preserved.';

    public function handle(): int
    {
        if (! SchemaFeatures::hasAttendanceLateUnrecorded()) {
            $this->warn('attendance_late_unrecorded table is not present in this environment. Nothing to do.');

            return self::SUCCESS;
        }

        $days = max(1, (int) $this->option('days'));
        $chunk = max(50, min(5000, (int) $this->option('chunk')));
        $dryRun = (bool) $this->option('dry-run');
        $cutoff = Carbon::now()->subDays($days);

        $this->line('');
        $this->info(sprintf(
            '%s late-attendance rows decided before %s (cutoff = %d days)…',
            $dryRun ? '🔍 Counting' : '🧹 Pruning',
            $cutoff->toIso8601String(),
            $days
        ));

        $baseQuery = AttendanceLateUnrecorded::query()
            ->whereIn('decision', [
                AttendanceLateUnrecorded::DECISION_APPROVED,
                AttendanceLateUnrecorded::DECISION_DENIED,
            ])
            ->where('decided_at', '<', $cutoff);

        $total = (clone $baseQuery)->count();
        if ($total === 0) {
            $this->info('Nothing to prune.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->table(
                ['Total decided rows beyond cutoff', 'Pending rows (kept)'],
                [[$total, AttendanceLateUnrecorded::query()
                    ->where('decision', AttendanceLateUnrecorded::DECISION_PENDING)
                    ->count()]]
            );

            return self::SUCCESS;
        }

        $deleted = 0;
        while (true) {
            $ids = (clone $baseQuery)
                ->limit($chunk)
                ->pluck('id')
                ->all();
            if (empty($ids)) {
                break;
            }
            $batchDeleted = AttendanceLateUnrecorded::query()
                ->whereIn('id', $ids)
                ->delete();
            $deleted += $batchDeleted;
            if ($batchDeleted < count($ids)) {
                // Concurrent delete or shorter than chunk — keep looping
                // to drain the rest in case the query still has matches.
            }
            $this->line(sprintf('  - removed %d (running total: %d / %d)', $batchDeleted, $deleted, $total));
            if ($batchDeleted === 0) {
                break;
            }
        }

        $this->newLine();
        $this->info(sprintf('Pruned %d row(s). Pending rows are untouched.', $deleted));

        return self::SUCCESS;
    }
}
