<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotent pivot tables (safe if 2026_05_19 migration was not run on production).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('class_lecturer')) {
            Schema::create('class_lecturer', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('lecturer_id')->constrained('lecturers')->cascadeOnDelete();
                $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['lecturer_id', 'class_id']);
            });
        }

        if (! Schema::hasTable('course_class')) {
            Schema::create('course_class', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
                $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['course_id', 'class_id']);
            });
        }

        if (Schema::hasTable('class_lecturer') && Schema::hasColumn('lecturers', 'class_id')) {
            DB::table('lecturers')->whereNotNull('class_id')->orderBy('id')->chunkById(100, function ($rows): void {
                foreach ($rows as $row) {
                    DB::table('class_lecturer')->insertOrIgnore([
                        'lecturer_id' => $row->id,
                        'class_id' => $row->class_id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });
        }

        if (Schema::hasTable('course_class') && Schema::hasColumn('courses', 'class_id')) {
            DB::table('courses')->whereNotNull('class_id')->orderBy('id')->chunkById(100, function ($rows): void {
                foreach ($rows as $row) {
                    DB::table('course_class')->insertOrIgnore([
                        'course_id' => $row->id,
                        'class_id' => $row->class_id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('course_class');
        Schema::dropIfExists('class_lecturer');
    }
};
