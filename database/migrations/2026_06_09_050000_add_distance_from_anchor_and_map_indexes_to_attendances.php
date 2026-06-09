<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Attendance Map — Phase 1 schema additions.
 *
 * Adds the single column the map redesign needs (`distance_from_anchor`)
 * plus the three composite indexes that drive the new map's hot queries
 * (viewport bounding-box fetch and session-scoped history).
 *
 * Per the owner decision (D1 → reuse `attendances` instead of creating
 * a parallel `attendance_location_logs` table): everything the map
 * stores about a single mark lives on this row.
 *
 *   distance_from_anchor : metres from the session anchor at the
 *                          moment of validation. SMALLINT UNSIGNED
 *                          is enough for any realistic geofence
 *                          (0–65 535 m); the value is clamped on
 *                          write by AttendanceLocation::storableMeters.
 *                          NULL for non-anchored marks (QR, Wi-Fi,
 *                          online) and for legacy rows.
 *
 * Composite indexes:
 *   (attendance_session_id, attendance_time) — per-session history feed
 *   (attendance_time)                        — date-range filters
 *   (lat, lng)                               — viewport bounding-box
 *
 * Safe to re-run: column + index creation guarded with hasColumn /
 * try-catch, so a partially-applied deploy can be repaired by simply
 * running `migrate --force` again.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('attendances')) {
            return;
        }

        Schema::table('attendances', function (Blueprint $table) {
            if (! Schema::hasColumn('attendances', 'distance_from_anchor')) {
                $table->unsignedSmallInteger('distance_from_anchor')
                    ->nullable()
                    ->after('lng');
            }
        });

        Schema::table('attendances', function (Blueprint $table) {
            try {
                $table->index(['attendance_session_id', 'attendance_time'], 'attendances_session_time_idx');
            } catch (\Throwable $e) {
                // already exists
            }
            try {
                $table->index('attendance_time', 'attendances_time_idx');
            } catch (\Throwable $e) {
                // already exists
            }
            try {
                $table->index(['lat', 'lng'], 'attendances_latlng_idx');
            } catch (\Throwable $e) {
                // already exists
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('attendances')) {
            return;
        }

        Schema::table('attendances', function (Blueprint $table) {
            foreach (['attendances_session_time_idx', 'attendances_time_idx', 'attendances_latlng_idx'] as $idx) {
                try {
                    $table->dropIndex($idx);
                } catch (\Throwable $e) {
                    // missing — ignore
                }
            }

            if (Schema::hasColumn('attendances', 'distance_from_anchor')) {
                $table->dropColumn('distance_from_anchor');
            }
        });
    }
};
