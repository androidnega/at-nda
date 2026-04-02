<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->decimal('location_lat', 10, 7)->nullable()->change();
            $table->decimal('location_lng', 10, 7)->nullable()->change();
            $table->integer('attendance_range_m')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->decimal('location_lat', 10, 7)->nullable(false)->change();
            $table->decimal('location_lng', 10, 7)->nullable(false)->change();
            $table->integer('attendance_range_m')->nullable(false)->change();
        });
    }
};
