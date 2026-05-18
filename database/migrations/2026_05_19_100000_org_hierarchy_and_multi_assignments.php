<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('universities', 'logo_path')) {
            Schema::table('universities', function (Blueprint $table): void {
                $table->string('logo_path')->nullable()->after('location');
            });
        }

        if (! Schema::hasColumn('classes', 'university_id')) {
            Schema::table('classes', function (Blueprint $table): void {
                $table->foreignId('university_id')->nullable()->after('id')->constrained('universities')->nullOnDelete();
            });

            DB::table('classes')->orderBy('id')->chunkById(100, function ($rows): void {
                foreach ($rows as $row) {
                    $universityId = DB::table('faculties')
                        ->where('id', $row->faculty_id)
                        ->value('university_id');
                    if ($universityId) {
                        DB::table('classes')->where('id', $row->id)->update(['university_id' => $universityId]);
                    }
                }
            });
        }

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

        if (Schema::hasColumn('classes', 'university_id')) {
            Schema::table('classes', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('university_id');
            });
        }

        if (Schema::hasColumn('universities', 'logo_path')) {
            Schema::table('universities', function (Blueprint $table): void {
                $table->dropColumn('logo_path');
            });
        }
    }
};
