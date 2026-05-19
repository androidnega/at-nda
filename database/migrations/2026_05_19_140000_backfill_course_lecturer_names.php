<?php

use App\Models\Course;
use App\Models\Lecturer;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasColumn('courses', 'lecturer_id')) {
            return;
        }

        Course::query()
            ->whereNotNull('lecturer_id')
            ->where(function ($q) {
                $q->whereNull('lecturer_name')->orWhere('lecturer_name', '');
            })
            ->orderBy('id')
            ->each(function (Course $course): void {
                $lecturer = Lecturer::query()->find($course->lecturer_id);
                if ($lecturer && trim((string) $lecturer->name) !== '') {
                    $course->lecturer_name = trim($lecturer->name);
                    $course->saveQuietly();
                }
            });
    }

    public function down(): void
    {
        // Non-reversible backfill.
    }
};
