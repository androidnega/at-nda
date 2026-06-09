<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Online-mode metadata for attendance sessions.
 *
 * meeting_platform : enum-ish short string (zoom | google_meet | teams | custom).
 * meeting_link     : optional URL the rep pastes in the meeting chat so
 *                    students can join straight from the attendance page.
 *
 * Replaces the deprecated online_submode + online_platform/online_note
 * trio. The old columns are LEFT in place (dormant) so already-deployed
 * databases keep working; nothing reads them anymore.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('attendance_sessions')) {
            return;
        }

        Schema::table('attendance_sessions', function (Blueprint $table) {
            if (! Schema::hasColumn('attendance_sessions', 'meeting_platform')) {
                $table->string('meeting_platform', 20)->nullable()->after('mode');
            }
            if (! Schema::hasColumn('attendance_sessions', 'meeting_link')) {
                $table->string('meeting_link', 500)->nullable()->after('meeting_platform');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('attendance_sessions')) {
            return;
        }

        Schema::table('attendance_sessions', function (Blueprint $table) {
            foreach (['meeting_link', 'meeting_platform'] as $col) {
                if (Schema::hasColumn('attendance_sessions', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
