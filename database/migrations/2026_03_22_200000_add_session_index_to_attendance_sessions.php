<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_sessions', function (Blueprint $table) {
            if (! Schema::hasColumn('attendance_sessions', 'session_index')) {
                $table->unsignedInteger('session_index')->default(1)->after('course_id');
            }
        });

        if (Schema::hasColumn('attendance_sessions', 'session_index')) {
            $courseIds = DB::table('attendance_sessions')->distinct()->pluck('course_id');
            foreach ($courseIds as $courseId) {
                $rows = DB::table('attendance_sessions')
                    ->where('course_id', $courseId)
                    ->orderBy('id')
                    ->pluck('id');
                $n = 1;
                foreach ($rows as $id) {
                    DB::table('attendance_sessions')->where('id', $id)->update(['session_index' => $n]);
                    $n++;
                }
            }
        }
    }

    public function down(): void
    {
        Schema::table('attendance_sessions', function (Blueprint $table) {
            if (Schema::hasColumn('attendance_sessions', 'session_index')) {
                $table->dropColumn('session_index');
            }
        });
    }
};
