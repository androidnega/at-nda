<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('course_materials')) {
            return;
        }
        if (Schema::hasColumn('course_materials', 'uploaded_by_lecturer_id')) {
            return;
        }

        Schema::table('course_materials', function (Blueprint $table) {
            $table->foreignId('uploaded_by_lecturer_id')
                ->nullable()
                ->after('uploaded_by_student_id')
                ->constrained('lecturers')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('course_materials')) {
            return;
        }
        if (! Schema::hasColumn('course_materials', 'uploaded_by_lecturer_id')) {
            return;
        }
        Schema::table('course_materials', function (Blueprint $table) {
            try {
                $table->dropForeign(['uploaded_by_lecturer_id']);
            } catch (\Throwable $e) {
                // Ignore — some drivers don't surface foreign-key names.
            }
            $table->dropColumn('uploaded_by_lecturer_id');
        });
    }
};
