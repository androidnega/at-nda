<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            if (! Schema::hasColumn('attendances', 'check_in_time')) {
                $table->timestamp('check_in_time')->nullable()->after('attendance_time');
            }
            if (! Schema::hasColumn('attendances', 'check_out_time')) {
                $table->timestamp('check_out_time')->nullable()->after('check_in_time');
            }
            if (! Schema::hasColumn('attendances', 'time_spent_seconds')) {
                $table->unsignedInteger('time_spent_seconds')->nullable()->after('check_out_time');
            }
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            if (Schema::hasColumn('attendances', 'time_spent_seconds')) {
                $table->dropColumn('time_spent_seconds');
            }
            if (Schema::hasColumn('attendances', 'check_out_time')) {
                $table->dropColumn('check_out_time');
            }
            if (Schema::hasColumn('attendances', 'check_in_time')) {
                $table->dropColumn('check_in_time');
            }
        });
    }
};
