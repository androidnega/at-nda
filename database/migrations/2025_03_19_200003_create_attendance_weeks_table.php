<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_weeks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->integer('week_number'); // 1-12
            $table->date('week_date'); // actual class date
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_weeks');
    }
};
