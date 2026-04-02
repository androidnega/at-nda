<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_sessions', function (Blueprint $table) {
            $table->foreignId('lecturer_id')->nullable()->after('course_id')->constrained('lecturers')->nullOnDelete();
            $table->foreignId('venue_id')->nullable()->after('lecturer_id')->constrained('venues')->nullOnDelete();
            $table->string('qr_token', 16)->nullable()->after('session_token');
            $table->timestamp('qr_expires_at')->nullable()->after('qr_token');
        });

        $rows = DB::table('attendance_sessions')->select('id', 'course_id')->get();
        foreach ($rows as $row) {
            $course = DB::table('courses')->where('id', $row->course_id)->first();
            if (!$course) {
                continue;
            }
            DB::table('attendance_sessions')->where('id', $row->id)->update([
                'lecturer_id' => $course->lecturer_id,
                'venue_id' => $course->venue_id,
                'qr_token' => Str::random(10),
                'qr_expires_at' => now()->addSeconds(45),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('attendance_sessions', function (Blueprint $table) {
            $table->dropForeign(['lecturer_id']);
            $table->dropForeign(['venue_id']);
            $table->dropColumn(['lecturer_id', 'venue_id', 'qr_token', 'qr_expires_at']);
        });
    }
};
