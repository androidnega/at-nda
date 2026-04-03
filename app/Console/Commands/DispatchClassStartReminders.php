<?php

namespace App\Console\Commands;

use App\Models\Course;
use App\Models\InAppNotification;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DispatchClassStartReminders extends Command
{
    /**
     * Run: cron every minute
     * php artisan notifications:dispatch-class-start-reminders --minutes=30
     */
    protected $signature = 'notifications:dispatch-class-start-reminders
                            {--minutes=30 : Minutes before start to notify}
                            {--limit-courses=200 : Safety limit for courses processed per run}';

    protected $description = 'Dispatch Firebase-free class start reminders into in_app_notifications.';

    public function handle(): int
    {
        $minutesBefore = max(0, (int) $this->option('minutes'));
        if ($minutesBefore < 1) {
            $this->warn('Invalid --minutes. Must be >= 1.');
            return 1;
        }

        $limitCourses = max(1, (int) $this->option('limit-courses'));

        $now = Carbon::now();
        $todayName = strtolower($now->format('l'));

        $courses = Course::query()
            ->select(['id', 'course_name', 'course_code', 'class_id', 'start_time'])
            ->whereNotNull('class_id')
            ->whereNotNull('day_of_week')
            ->whereNotNull('start_time')
            ->whereRaw('LOWER(TRIM(day_of_week)) = ?', [$todayName])
            ->orderBy('start_time')
            ->limit($limitCourses)
            ->get();

        if ($courses->isEmpty()) {
            $this->line('No scheduled courses for today.');
            return 0;
        }

        $windowStart = $now->copy()->subMinute();

        $created = 0;

        foreach ($courses as $course) {
            if (! $course->class_id || ! $course->start_time) {
                continue;
            }

            try {
                $time = Carbon::parse((string) $course->start_time)->format('H:i:s');
            } catch (\Throwable) {
                continue;
            }

            $startAt = $now->copy()->setTimeFromTimeString($time);
            if ($startAt->lessThanOrEqualTo($now)) {
                // Start already passed — only remind for future lectures.
                continue;
            }

            $reminderAt = $startAt->copy()->subMinutes($minutesBefore);
            if (! $reminderAt->greaterThanOrEqualTo($windowStart) || $reminderAt->greaterThan($now)) {
                continue;
            }

            $students = Student::query()
                ->where('class_id', $course->class_id)
                ->select(['id'])
                ->get();

            if ($students->isEmpty()) {
                continue;
            }

            $title = 'Class starting soon';
            $hhmm = $startAt->format('H:i');
            $courseLabel = trim((string) ($course->course_code ?? '')) !== ''
                ? (string) $course->course_code
                : (string) ($course->course_name ?? 'class');

            $body = "Your class {$courseLabel} starts at {$hhmm}.";

            $rows = [];
            foreach ($students as $stu) {
                $deliveryKey = sprintf(
                    'class_start_reminder:%d:%s:%d',
                    (int) $course->id,
                    $startAt->format('Y-m-d H:i'),
                    (int) $stu->id
                );

                $rows[] = [
                    'student_id' => (int) $stu->id,
                    'course_id' => $course->id,
                    'kind' => 'class_start_reminder',
                    'title' => $title,
                    'body' => $body,
                    'starts_at' => $startAt->toDateTimeString(),
                    'delivery_key' => $deliveryKey,
                    'read_at' => null,
                    'created_at' => $now->toDateTimeString(),
                    'updated_at' => $now->toDateTimeString(),
                ];
            }

            // Unique delivery_key ensures idempotency (cron runs every minute).
            $before = InAppNotification::query()
                ->whereNull('read_at')
                ->where('kind', 'class_start_reminder')
                ->count();

            DB::table('in_app_notifications')->insertOrIgnore($rows);

            $after = InAppNotification::query()
                ->whereNull('read_at')
                ->where('kind', 'class_start_reminder')
                ->count();

            $created += max(0, $after - $before);
        }

        $this->line("Dispatched reminders. Approx newly created: {$created}");
        return 0;
    }
}

