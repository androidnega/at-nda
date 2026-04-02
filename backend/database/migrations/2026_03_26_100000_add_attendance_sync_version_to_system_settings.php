<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->unsignedBigInteger('attendance_data_version')->default(0);
            $table->timestamp('last_attendance_reset_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->dropColumn(['attendance_data_version', 'last_attendance_reset_at']);
        });
    }
};
