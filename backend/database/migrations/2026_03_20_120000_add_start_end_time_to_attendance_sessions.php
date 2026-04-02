<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_sessions', function (Blueprint $table) {
            if (!Schema::hasColumn('attendance_sessions', 'start_time')) {
                $table->timestamp('start_time')->nullable()->after('is_active');
            }
            if (!Schema::hasColumn('attendance_sessions', 'end_time')) {
                $table->timestamp('end_time')->nullable()->after('start_time');
            }
        });

        if (Schema::hasColumn('attendance_sessions', 'start_time')) {
            DB::table('attendance_sessions')->whereNull('start_time')->update([
                'start_time' => DB::raw('created_at'),
                'end_time' => DB::raw('COALESCE(expires_at, created_at)'),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('attendance_sessions', function (Blueprint $table) {
            if (Schema::hasColumn('attendance_sessions', 'end_time')) {
                $table->dropColumn('end_time');
            }
            if (Schema::hasColumn('attendance_sessions', 'start_time')) {
                $table->dropColumn('start_time');
            }
        });
    }
};
