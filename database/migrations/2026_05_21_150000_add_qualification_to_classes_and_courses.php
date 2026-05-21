<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Introduce a "qualification" attribute on classes and courses so the
 * catalog can be split between HND, Diploma, and Degree cohorts.
 *
 * Classes always have a qualification (defaults to "degree", matching the
 * current install).  Courses can be tagged to a single qualification or
 * left null to mean "applies to all" — handy for general-ed courses.
 *
 * Lecturers stay shared across qualifications, so no schema change there.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('classes') && ! Schema::hasColumn('classes', 'qualification')) {
            Schema::table('classes', function (Blueprint $table) {
                $table->string('qualification', 32)->default('degree')->after('level');
            });
        }

        if (Schema::hasTable('courses') && ! Schema::hasColumn('courses', 'qualification')) {
            Schema::table('courses', function (Blueprint $table) {
                $table->string('qualification', 32)->nullable()->after('course_code');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('classes') && Schema::hasColumn('classes', 'qualification')) {
            Schema::table('classes', function (Blueprint $table) {
                $table->dropColumn('qualification');
            });
        }

        if (Schema::hasTable('courses') && Schema::hasColumn('courses', 'qualification')) {
            Schema::table('courses', function (Blueprint $table) {
                $table->dropColumn('qualification');
            });
        }
    }
};
