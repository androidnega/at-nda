<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('courses', 'lecturer_id')) {
            Schema::table('courses', function (Blueprint $table) {
                $table->foreignId('lecturer_id')->nullable()->after('lecturer_name')->constrained('lecturers')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('courses', 'lecturer_id')) {
            Schema::table('courses', function (Blueprint $table) {
                $table->dropForeign(['lecturer_id']);
            });
        }
    }
};
