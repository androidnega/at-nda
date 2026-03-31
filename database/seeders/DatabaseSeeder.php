<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\CourseRep;
use App\Models\Lecturer;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        Artisan::call('migrate', ['--force' => true]);

        $this->call(UniversitySeeder::class);
        $this->call(SystemSettingsSeeder::class);
        $this->call(AdminSeeder::class);

        Lecturer::firstOrCreate(
            ['email' => 'lecturer@example.com'],
            ['name' => 'Dr. Emmanuel Yeboah', 'password' => Hash::make('password')]
        );

        $class = SchoolClass::firstOrCreate(
            ['code' => 'GEN'],
            ['name' => 'General', 'level' => 100]
        );

        Venue::firstOrCreate(['name' => 'Room 101'], ['code' => 'R101', 'building' => 'Main Block', 'capacity' => 50]);
        Venue::firstOrCreate(['name' => 'Room 102'], ['code' => 'R102', 'building' => 'Main Block', 'capacity' => 40]);

        $course = Course::firstOrCreate(
            ['course_name' => 'Introduction to Programming'],
            [
                'course_code' => 'CS203',
                'class_id' => $class->id,
                'day_of_week' => 'Thursday',
                'start_time' => '09:00',
                'end_time' => '11:00',
                'venue' => 'Room 101',
                'lecturer_name' => 'Dr. Emmanuel Yeboah',
                'attendance_window_minutes' => 60,
            ]
        );
        if (!$course->class_id) {
            $course->update(['class_id' => $class->id]);
        }

        Student::firstOrCreate(
            ['index_number' => 'ITN123456'],
            ['first_name' => 'John', 'last_name' => 'Doe', 'class_id' => $class->id]
        );
        Student::firstOrCreate(
            ['index_number' => 'ITS789012'],
            ['first_name' => 'Jane', 'last_name' => 'Smith', 'class_id' => $class->id]
        );

        $repStudent = Student::firstOrCreate(
            ['index_number' => 'REP'],
            [
                'first_name' => 'Course',
                'last_name' => 'Rep',
                'class_id' => $class->id,
                'password' => Hash::make('rep'),
            ]
        );
        if (empty($repStudent->password)) {
            $repStudent->update(['password' => Hash::make('rep')]);
        }

        CourseRep::firstOrCreate(
            ['student_id' => $repStudent->id, 'course_id' => $course->id],
            ['role' => CourseRep::ROLE_REP]
        );

        $this->call(ActiveSessionDevSeeder::class);
    }
}
