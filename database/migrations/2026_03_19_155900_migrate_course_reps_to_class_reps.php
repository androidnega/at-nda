<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $reps = DB::table('course_reps')
            ->join('courses', 'course_reps.course_id', '=', 'courses.id')
            ->whereNotNull('courses.class_id')
            ->select('course_reps.student_id', 'courses.class_id as class_id', 'course_reps.role')
            ->distinct()
            ->get();

        foreach ($reps as $r) {
            DB::table('class_reps')->insertOrIgnore([
                'student_id' => $r->student_id,
                'class_id' => $r->class_id,
                'role' => $r->role ?? 'rep',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // No rollback - class_reps data stays
    }
};
