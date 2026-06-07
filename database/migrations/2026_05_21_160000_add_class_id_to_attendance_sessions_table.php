<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('attendance_sessions')) {
            return;
        }

        if (! Schema::hasColumn('attendance_sessions', 'class_id')) {
            Schema::table('attendance_sessions', function (Blueprint $table) {
                $table->unsignedBigInteger('class_id')->nullable()->after('course_id');
                $table->foreign('class_id')->references('id')->on('classes')->nullOnDelete();
            });
        }

        // MySQL-only UPDATE...JOIN syntax. On other drivers (sqlite in
        // tests) the table starts empty, so both backfills are no-ops
        // and are skipped.
        $isMysql = in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true);

        if ($isMysql
            && Schema::hasColumn('attendance_sessions', 'class_id')
            && Schema::hasTable('attendance_weeks')
            && Schema::hasColumn('attendance_weeks', 'class_id')) {
            DB::statement('
                UPDATE attendance_sessions s
                INNER JOIN attendance_weeks w ON w.id = s.attendance_week_id
                SET s.class_id = w.class_id
                WHERE s.class_id IS NULL AND w.class_id IS NOT NULL
            ');
        }

        if ($isMysql
            && Schema::hasColumn('attendance_sessions', 'class_id')
            && Schema::hasTable('courses')
            && Schema::hasColumn('courses', 'class_id')) {
            DB::statement('
                UPDATE attendance_sessions s
                INNER JOIN courses c ON c.id = s.course_id
                SET s.class_id = c.class_id
                WHERE s.class_id IS NULL AND c.class_id IS NOT NULL
            ');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('attendance_sessions') || ! Schema::hasColumn('attendance_sessions', 'class_id')) {
            return;
        }

        Schema::table('attendance_sessions', function (Blueprint $table) {
            $table->dropForeign(['class_id']);
            $table->dropColumn('class_id');
        });
    }
};
