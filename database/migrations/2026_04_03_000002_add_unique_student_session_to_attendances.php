<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Keep the earliest row per (student_id, attendance_session_id) before adding unique constraint.
        DB::statement('
            DELETE a1 FROM attendances a1
            INNER JOIN attendances a2
              ON a1.student_id = a2.student_id
             AND a1.attendance_session_id = a2.attendance_session_id
             AND a1.id > a2.id
            WHERE a1.attendance_session_id IS NOT NULL
        ');

        Schema::table('attendances', function (Blueprint $table) {
            $table->unique(
                ['student_id', 'attendance_session_id'],
                'attendances_student_session_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropUnique('attendances_student_session_unique');
        });
    }
};

