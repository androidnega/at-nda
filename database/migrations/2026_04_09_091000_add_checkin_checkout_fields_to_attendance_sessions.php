<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_sessions', function (Blueprint $table) {
            if (! Schema::hasColumn('attendance_sessions', 'attendance_mode')) {
                $table->string('attendance_mode', 32)->default('instant')->after('mode');
            }
            if (! Schema::hasColumn('attendance_sessions', 'expected_end_time')) {
                $table->timestamp('expected_end_time')->nullable()->after('end_time');
            }
            if (! Schema::hasColumn('attendance_sessions', 'checkout_enabled')) {
                $table->boolean('checkout_enabled')->default(false)->after('is_active');
            }
        });
    }

    public function down(): void
    {
        Schema::table('attendance_sessions', function (Blueprint $table) {
            if (Schema::hasColumn('attendance_sessions', 'checkout_enabled')) {
                $table->dropColumn('checkout_enabled');
            }
            if (Schema::hasColumn('attendance_sessions', 'expected_end_time')) {
                $table->dropColumn('expected_end_time');
            }
            if (Schema::hasColumn('attendance_sessions', 'attendance_mode')) {
                $table->dropColumn('attendance_mode');
            }
        });
    }
};
