<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Attendance weeks are now scoped per (course, class) so each class starts at
 * Week 1 instead of inheriting the highest week number from another class that
 * shares the same course (e.g. Software Engineering shared across two cohorts).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('attendance_weeks')) {
            return;
        }

        if (! Schema::hasColumn('attendance_weeks', 'class_id')) {
            Schema::table('attendance_weeks', function (Blueprint $table): void {
                $table->unsignedBigInteger('class_id')->nullable()->after('course_id');
                $table->index(['course_id', 'class_id', 'week_date'], 'attendance_weeks_course_class_date_idx');
            });

            if (Schema::hasTable('classes')) {
                try {
                    Schema::table('attendance_weeks', function (Blueprint $table): void {
                        $table->foreign('class_id')
                            ->references('id')
                            ->on('classes')
                            ->nullOnDelete();
                    });
                } catch (\Throwable $e) {
                    // SQLite or older MySQL may not support adding FKs after the fact.
                }
            }
        }

        // Backfill: best-effort assign class_id from the most recent attendance
        // session for that week, falling back to the course's primary class.
        if (Schema::hasColumn('attendance_weeks', 'class_id')) {
            DB::table('attendance_weeks')->whereNull('class_id')->orderBy('id')->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    $classId = self::guessClassIdFromAttendance((int) $row->id, (int) $row->course_id);
                    if ($classId === null) {
                        $classId = self::guessClassIdFromCourse((int) $row->course_id);
                    }
                    if ($classId !== null) {
                        DB::table('attendance_weeks')->where('id', $row->id)->update(['class_id' => $classId]);
                    }
                }
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('attendance_weeks')) {
            return;
        }

        Schema::table('attendance_weeks', function (Blueprint $table): void {
            if (Schema::hasColumn('attendance_weeks', 'class_id')) {
                try {
                    $table->dropForeign(['class_id']);
                } catch (\Throwable $e) {
                    // Safe to ignore in environments without FK metadata.
                }
                try {
                    $table->dropIndex('attendance_weeks_course_class_date_idx');
                } catch (\Throwable $e) {
                    // Index may not exist on rollback retry.
                }
                $table->dropColumn('class_id');
            }
        });
    }

    private static function guessClassIdFromAttendance(int $weekId, int $courseId): ?int
    {
        if (! Schema::hasTable('attendances') || ! Schema::hasTable('students')) {
            return null;
        }

        $row = DB::table('attendances')
            ->join('students', 'attendances.student_id', '=', 'students.id')
            ->where('attendances.attendance_week_id', $weekId)
            ->whereNotNull('students.class_id')
            ->orderByDesc('attendances.id')
            ->value('students.class_id');

        return $row !== null ? (int) $row : null;
    }

    private static function guessClassIdFromCourse(int $courseId): ?int
    {
        if (Schema::hasTable('course_class')) {
            $row = DB::table('course_class')->where('course_id', $courseId)->orderBy('id')->value('class_id');
            if ($row !== null) {
                return (int) $row;
            }
        }
        if (Schema::hasColumn('courses', 'class_id')) {
            $row = DB::table('courses')->where('id', $courseId)->value('class_id');

            return $row !== null ? (int) $row : null;
        }

        return null;
    }
};
