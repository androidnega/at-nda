<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Class reps now own credit-hours per timetable slot (so two classes that share
 * a course can record different credit weights if they need to). The value falls
 * back to courses.credit_hours when a slot doesn't set its own.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('class_timetables')) {
            return;
        }
        if (Schema::hasColumn('class_timetables', 'credit_hours')) {
            return;
        }

        Schema::table('class_timetables', function (Blueprint $table): void {
            $table->unsignedSmallInteger('credit_hours')->nullable()->after('venue');
        });

        if (Schema::hasTable('courses') && Schema::hasColumn('courses', 'credit_hours')) {
            DB::statement(
                'UPDATE class_timetables ct '
                .'JOIN courses c ON c.id = ct.course_id '
                .'SET ct.credit_hours = c.credit_hours '
                .'WHERE ct.credit_hours IS NULL'
            );
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('class_timetables')) {
            return;
        }
        if (! Schema::hasColumn('class_timetables', 'credit_hours')) {
            return;
        }
        Schema::table('class_timetables', function (Blueprint $table): void {
            $table->dropColumn('credit_hours');
        });
    }
};
