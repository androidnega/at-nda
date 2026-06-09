<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rolling attendance codes for online sessions.
 *
 * Each online attendance_sessions row has a rotating sequence of short
 * codes (default 4 digits). At any moment exactly one is "current"
 * (starts_at <= now < expires_at); older rows are retained for a short
 * window so a student mid-submit on the previous code still validates
 * during the rotation handover.
 *
 * Rotation interval comes from config('attendance.online_code_rotation_seconds',
 * 120). Generation is lazy — the very next read after a code expires
 * mints a new one (no cron required).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('online_session_codes')) {
            return;
        }

        Schema::create('online_session_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')
                ->constrained('attendance_sessions')
                ->cascadeOnDelete();
            // 4 digits today; widened to 8 so we can opt into alphanumeric
            // codes later without another migration.
            $table->string('code', 8);
            $table->timestamp('starts_at');
            $table->timestamp('expires_at');
            $table->timestamp('created_at')->useCurrent();

            // Current-code lookup (the rep card polls this every few seconds).
            $table->index(['session_id', 'expires_at']);
            // Student submission lookup: same code may legitimately recur in
            // a DIFFERENT session minutes later, so we DO NOT make this unique.
            $table->index(['code', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('online_session_codes');
    }
};
