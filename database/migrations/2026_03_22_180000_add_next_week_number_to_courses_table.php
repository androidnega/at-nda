<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            if (! Schema::hasColumn('courses', 'next_week_number')) {
                $table->unsignedSmallInteger('next_week_number')->nullable()->after('attendance_window_minutes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            if (Schema::hasColumn('courses', 'next_week_number')) {
                $table->dropColumn('next_week_number');
            }
        });
    }
};
