<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('course_schedules')) {
            return;
        }
        $schedules = DB::table('course_schedules')->get()->groupBy('course_id');

        foreach ($schedules as $courseId => $courseSchedules) {
            $first = $courseSchedules->first();
            DB::table('courses')->where('id', $courseId)->update([
                'day_of_week' => $first->day_of_week,
                'start_time' => $first->start_time,
                'end_time' => $first->end_time,
                'venue' => $first->venue,
                'lecturer_name' => $first->lecturer_name,
            ]);
        }

        Schema::dropIfExists('course_schedules');
    }

    public function down(): void
    {
        Schema::create('course_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('day_of_week');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('venue')->nullable();
            $table->string('lecturer_name');
            $table->timestamps();
        });

        $courses = DB::table('courses')->whereNotNull('day_of_week')->get();
        foreach ($courses as $course) {
            DB::table('course_schedules')->insert([
                'course_id' => $course->id,
                'day_of_week' => $course->day_of_week,
                'start_time' => $course->start_time,
                'end_time' => $course->end_time,
                'venue' => $course->venue,
                'lecturer_name' => $course->lecturer_name,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn(['day_of_week', 'start_time', 'end_time', 'venue', 'lecturer_name']);
        });
    }
};
