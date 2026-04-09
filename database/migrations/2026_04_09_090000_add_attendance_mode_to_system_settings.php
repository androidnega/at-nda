<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('system_settings', 'attendance_mode')) {
                $table->string('attendance_mode', 32)->default('instant')->after('mobile_app_theme_seed');
            }
            if (! Schema::hasColumn('system_settings', 'instant_mode_type')) {
                $table->string('instant_mode_type', 32)->default('location_qr')->after('attendance_mode');
            }
        });
    }

    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            if (Schema::hasColumn('system_settings', 'instant_mode_type')) {
                $table->dropColumn('instant_mode_type');
            }
            if (Schema::hasColumn('system_settings', 'attendance_mode')) {
                $table->dropColumn('attendance_mode');
            }
        });
    }
};
