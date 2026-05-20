<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Per-class timetable entries. Each class owns its own schedule so two classes
 * sharing the same course (e.g. Software Engineering) don't override each other.
 * Reps create / edit / delete these; admins still own the underlying course + lecturer catalog.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('class_timetables')) {
            Schema::create('class_timetables', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
                $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
                $table->string('day_of_week', 16);
                $table->time('start_time');
                $table->time('end_time');
                $table->foreignId('lecturer_id')->nullable()->constrained('lecturers')->nullOnDelete();
                $table->foreignId('venue_id')->nullable()->constrained('venues')->nullOnDelete();
                $table->string('venue', 255)->nullable();
                $table->unsignedBigInteger('created_by_student_id')->nullable();
                $table->timestamps();

                $table->index(['class_id', 'day_of_week']);
                $table->unique(['class_id', 'course_id', 'day_of_week', 'start_time'], 'class_timetables_unique_slot');
            });
        }

        // Best-effort backfill: copy each course's existing schedule into the
        // class_timetables table for every class that course is assigned to. Reps
        // can then take over editing without losing the previous admin-entered grid.
        self::backfillFromCourses();
    }

    public function down(): void
    {
        Schema::dropIfExists('class_timetables');
    }

    private static function backfillFromCourses(): void
    {
        if (! Schema::hasTable('class_timetables') || ! Schema::hasTable('courses')) {
            return;
        }

        $courseColumns = ['id', 'day_of_week', 'start_time', 'end_time', 'lecturer_id', 'venue_id', 'venue'];
        if (Schema::hasColumn('courses', 'class_id')) {
            $courseColumns[] = 'class_id';
        }

        DB::table('courses')
            ->select($courseColumns)
            ->whereNotNull('day_of_week')
            ->whereNotNull('start_time')
            ->whereNotNull('end_time')
            ->orderBy('id')
            ->chunkById(200, function ($courses): void {
                foreach ($courses as $course) {
                    $classIds = self::classIdsForCourse((int) $course->id, isset($course->class_id) ? (int) $course->class_id : null);
                    foreach ($classIds as $classId) {
                        self::insertSlotIfMissing((int) $course->id, $classId, $course);
                    }
                }
            });
    }

    /**
     * @return list<int>
     */
    private static function classIdsForCourse(int $courseId, ?int $legacyClassId): array
    {
        $ids = [];
        if (Schema::hasTable('course_class')) {
            $ids = array_map('intval', DB::table('course_class')->where('course_id', $courseId)->pluck('class_id')->all());
        }
        if ($legacyClassId !== null && $legacyClassId > 0 && ! in_array($legacyClassId, $ids, true)) {
            $ids[] = $legacyClassId;
        }

        return array_values(array_unique(array_filter($ids, fn (int $id) => $id > 0)));
    }

    private static function insertSlotIfMissing(int $courseId, int $classId, object $course): void
    {
        $day = (string) $course->day_of_week;
        $start = (string) $course->start_time;
        $end = (string) $course->end_time;
        if ($day === '' || $start === '' || $end === '') {
            return;
        }

        $startNormalized = self::normalizeTime($start);
        if ($startNormalized === null) {
            return;
        }

        $exists = DB::table('class_timetables')
            ->where('class_id', $classId)
            ->where('course_id', $courseId)
            ->where('day_of_week', $day)
            ->where('start_time', $startNormalized)
            ->exists();
        if ($exists) {
            return;
        }

        DB::table('class_timetables')->insert([
            'class_id' => $classId,
            'course_id' => $courseId,
            'day_of_week' => $day,
            'start_time' => $startNormalized,
            'end_time' => self::normalizeTime($end) ?? $end,
            'lecturer_id' => $course->lecturer_id ?? null,
            'venue_id' => $course->venue_id ?? null,
            'venue' => $course->venue ?? null,
            'created_by_student_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private static function normalizeTime(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        try {
            return \Carbon\Carbon::parse($value)->format('H:i:s');
        } catch (\Throwable $e) {
            return null;
        }
    }
};
