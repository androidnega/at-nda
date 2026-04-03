<?php

use App\Models\AttendanceSession;
use App\Models\Course;
use App\Support\SecureQrToken;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('attendance_sessions', 'session_code')) {
            Schema::table('attendance_sessions', function (Blueprint $table) {
                $table->string('session_code', 48)->nullable()->after('session_token');
            });
        }

        AttendanceSession::query()->whereNull('session_code')->chunkById(100, function ($sessions): void {
            foreach ($sessions as $session) {
                $course = Course::find($session->course_id);
                if (! $course) {
                    continue;
                }
                $session->session_code = AttendanceSession::generateUniqueSessionCodeForCourse($course);
                $session->saveQuietly();
                if (SecureQrToken::secret()) {
                    $session->qr_token = SecureQrToken::encode($session);
                    $session->saveQuietly();
                }
            }
        });

        Schema::table('attendance_sessions', function (Blueprint $table) {
            if (Schema::hasColumn('attendance_sessions', 'session_code')) {
                $sm = Schema::getConnection()->getDriverName();
                if (in_array($sm, ['mysql', 'mariadb'], true)) {
                    $table->unique('session_code', 'attendance_sessions_session_code_unique');
                } else {
                    $table->unique('session_code');
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('attendance_sessions', function (Blueprint $table) {
            if (Schema::hasColumn('attendance_sessions', 'session_code')) {
                try {
                    $table->dropUnique('attendance_sessions_session_code_unique');
                } catch (\Throwable) {
                    try {
                        $table->dropUnique(['session_code']);
                    } catch (\Throwable) {
                    }
                }
                $table->dropColumn('session_code');
            }
        });
    }
};
