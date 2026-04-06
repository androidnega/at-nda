<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('system_settings', 'rep_dashboard_theme')) {
                $table->string('rep_dashboard_theme', 32)->default('classic');
            }
            if (! Schema::hasColumn('system_settings', 'student_dashboard_theme')) {
                $table->string('student_dashboard_theme', 32)->default('classic');
            }
        });
    }

    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table): void {
            if (Schema::hasColumn('system_settings', 'rep_dashboard_theme')) {
                $table->dropColumn('rep_dashboard_theme');
            }
            if (Schema::hasColumn('system_settings', 'student_dashboard_theme')) {
                $table->dropColumn('student_dashboard_theme');
            }
        });
    }
};
