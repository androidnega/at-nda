<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('attendances')) {
            return;
        }

        Schema::table('attendances', function (Blueprint $table) {
            if (! Schema::hasColumn('attendances', 'device_fingerprint')) {
                $table->string('device_fingerprint', 64)->nullable()->after('user_agent');
                $table->index(['attendance_session_id', 'device_fingerprint'], 'attendances_session_dfp_idx');
                $table->index('device_fingerprint', 'attendances_dfp_idx');
            }
            if (! Schema::hasColumn('attendances', 'client_meta')) {
                // JSON blob with the browser-side signals we collect at
                // mark-time (screen size, timezone, language, platform,
                // hardware concurrency, etc.). Useful for spotting an
                // attacker that opens two browsers from the same device
                // even when they swap IP networks between marks.
                $table->json('client_meta')->nullable()->after('device_fingerprint');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('attendances')) {
            return;
        }

        Schema::table('attendances', function (Blueprint $table) {
            foreach (['attendances_session_dfp_idx', 'attendances_dfp_idx'] as $idx) {
                try {
                    $table->dropIndex($idx);
                } catch (\Throwable $e) {
                    // Index was already gone or never created.
                }
            }
            foreach (['client_meta', 'device_fingerprint'] as $col) {
                if (Schema::hasColumn('attendances', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
