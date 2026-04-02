<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->foreignId('venue_id')->nullable()->after('venue')->constrained()->nullOnDelete();
        });
        Schema::table('lecturers', function (Blueprint $table) {
            $table->foreignId('class_id')->nullable()->after('email')->constrained('classes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropForeign(['venue_id']);
        });
        Schema::table('lecturers', function (Blueprint $table) {
            $table->dropForeign(['class_id']);
        });
    }
};
