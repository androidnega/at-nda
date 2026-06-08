<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Online-attendance sub-mode for an online session: 'qr' (students upload
 * a screenshot of the rep's QR), 'code' (students type the existing
 * session_code), or 'both' (either is accepted).
 *
 * Other plumbing already exists on attendance_sessions:
 *  - session_code (varchar 48)  — manual code (COURSECODE-XXXX format)
 *  - expires_at   (datetime)    — auto-close deadline
 *  - qr_token     (varchar)     — secure QR payload
 *
 * So this migration only adds the sub-mode flag.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('attendance_sessions', 'online_submode')) {
            Schema::table('attendance_sessions', function (Blueprint $table) {
                // Nullable: in-person sessions ignore this column entirely.
                // Cap at 16 so we can grow ('qr_strict', 'code_short', etc.)
                // without another migration.
                $table->string('online_submode', 16)->nullable()->after('mode');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('attendance_sessions', 'online_submode')) {
            Schema::table('attendance_sessions', function (Blueprint $table) {
                $table->dropColumn('online_submode');
            });
        }
    }
};
