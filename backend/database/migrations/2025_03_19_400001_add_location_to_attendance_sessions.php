<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_sessions', function (Blueprint $table) {
            $table->decimal('location_lat', 10, 7)->nullable()->after('mode');
            $table->decimal('location_lng', 10, 7)->nullable()->after('location_lat');
            $table->integer('attendance_range_m')->nullable()->after('location_lng');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_sessions', function (Blueprint $table) {
            $table->dropColumn(['location_lat', 'location_lng', 'attendance_range_m']);
        });
    }
};
