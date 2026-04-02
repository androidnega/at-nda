<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->string('day_of_week')->nullable()->after('course_code');
            $table->time('start_time')->nullable()->after('day_of_week');
            $table->time('end_time')->nullable()->after('start_time');
            $table->string('venue')->nullable()->after('end_time');
            $table->string('lecturer_name')->nullable()->after('venue');
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn(['day_of_week', 'start_time', 'end_time', 'venue', 'lecturer_name']);
        });
    }
};
