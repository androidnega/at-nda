<?php

namespace Database\Seeders;

use App\Models\AttendanceSession;
use App\Models\AttendanceWeek;
use App\Models\Course;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Optional: creates one active attendance session for local / Flutter testing.
 * Run: php artisan db:seed --class=ActiveSessionDevSeeder
 */
class ActiveSessionDevSeeder extends Seeder
{
    public function run(): void
    {
        $course = Course::query()->orderBy('id')->first();
        if (!$course) {
            $this->command?->warn('No course in database. Create a course first.');

            return;
        }

        AttendanceSession::where('course_id', $course->id)->update(['is_active' => false]);

        $today = now()->toDateString();
        $week = AttendanceWeek::firstOrCreate(
            [
                'course_id' => $course->id,
                'week_date' => $today,
            ],
            [
                'week_number' => (int) ((AttendanceWeek::where('course_id', $course->id)->max('week_number') ?? 0) + 1),
            ]
        );

        $lat = $course->location_lat ?? 5.6037;
        $lng = $course->location_lng ?? -0.1870;
        $range = $course->attendance_range_m ?? config('app.default_attendance_range_m', 200);
        $ends = now()->addHours(2);

        AttendanceSession::create([
            'course_id' => $course->id,
            'session_index' => AttendanceSession::nextIndexForCourse($course->id),
            'attendance_week_id' => $week->id,
            'mode' => 'location',
            'is_active' => true,
            'session_token' => Str::random(32),
            'start_time' => now(),
            'end_time' => $ends,
            'expires_at' => $ends,
            'location_lat' => $lat,
            'location_lng' => $lng,
            'attendance_range_m' => (int) $range,
        ]);

        $this->command?->info('Active session created for course: ' . $course->course_name . ' (id ' . $course->id . ')');
    }
}
