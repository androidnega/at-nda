<?php

namespace App\Console\Commands;

use App\Services\AttendanceDedupService;
use Illuminate\Console\Command;

/**
 * One-shot cleanup for older deployments where a closed-and-reopened
 * session inserted a fresh attendance row instead of reusing the
 * existing one. Keeps the earliest mark per (student, course, week)
 * and removes the rest.
 *
 * Examples:
 *   php artisan attendance:dedupe-weeks --dry-run
 *   php artisan attendance:dedupe-weeks
 *   php artisan attendance:dedupe-weeks --course=42
 *   php artisan attendance:dedupe-weeks --week=180
 */
class AttendanceDedupeWeeksCommand extends Command
{
    protected $signature = 'attendance:dedupe-weeks
                            {--dry-run : Report duplicates without deleting anything}
                            {--course= : Only scan this course id}
                            {--week= : Only scan this attendance_week_id}
                            {--class= : Only scan students in this class id}';

    protected $description = 'Collapse duplicate attendance rows for the same (student, course, week) down to the earliest mark.';

    public function handle(AttendanceDedupService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $courseId = $this->option('course') ? (int) $this->option('course') : null;
        $weekId = $this->option('week') ? (int) $this->option('week') : null;
        $classId = $this->option('class') ? (int) $this->option('class') : null;

        $this->line('');
        $this->info($dryRun ? '🔍 Dry run — no rows will be deleted.' : '🧹 Cleaning duplicate weekly attendance rows…');

        $report = $service->run([
            'dry_run' => $dryRun,
            'course_id' => $courseId,
            'attendance_week_id' => $weekId,
            'class_id' => $classId,
            'actor_role' => 'console',
            'actor_name' => 'attendance:dedupe-weeks',
            'reason' => 'manual_cli_dedupe',
        ]);

        $this->newLine();
        $this->table(
            ['Groups scanned', 'Kept (canonical)', 'Duplicates ' . ($dryRun ? 'found' : 'removed')],
            [[$report['groups_scanned'], $report['kept'], $report['duplicates_removed']]]
        );

        if (! empty($report['sample'])) {
            $this->newLine();
            $this->info('Sample (first ' . count($report['sample']) . ' affected groups):');
            foreach ($report['sample'] as $row) {
                $this->line(sprintf(
                    '  student=%d course=%d week=%d → kept #%d, removed [%s]',
                    $row['student_id'],
                    $row['course_id'],
                    $row['attendance_week_id'],
                    $row['kept_id'],
                    implode(', ', $row['removed_ids'])
                ));
            }
        }

        $this->newLine();
        $this->info($dryRun
            ? 'Re-run without --dry-run to actually clean up.'
            : "Done. Each removal is logged in audit_logs under action=mark_deleted.");

        return self::SUCCESS;
    }
}
