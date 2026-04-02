<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_sessions', function (Blueprint $table) {
            $table->foreignId('attendance_week_id')->nullable()->after('course_id')->constrained('attendance_weeks')->nullOnDelete();
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->foreignId('attendance_week_id')->nullable()->after('attendance_session_id')->constrained('attendance_weeks')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('attendance_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('attendance_week_id');
        });
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropConstrainedForeignId('attendance_week_id');
        });
    }
};
