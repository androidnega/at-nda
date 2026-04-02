<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->index(['student_id', 'attendance_session_id'], 'attendances_student_session_idx');
            $table->index('course_id', 'attendances_course_id_idx');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropIndex('attendances_student_session_idx');
            $table->dropIndex('attendances_course_id_idx');
        });
    }
};
