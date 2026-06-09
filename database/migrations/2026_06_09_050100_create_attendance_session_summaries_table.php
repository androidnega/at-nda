<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Attendance Map — Phase 1 schema: per-session summary cache.
 *
 * Per owner decision D2: aggregates live in their own small table
 * instead of on attendance_sessions (which is hot, read on every
 * mark + dashboard render). Each row is ~80 bytes, so even 100 k
 * sessions stay under 10 MB.
 *
 * Populated by AttendanceSessionSummaryService::rebuild() — called
 * once when a session is deactivated, and lazily on first map read
 * for closed sessions whose summary is missing. The map view NEVER
 * recomputes these values on the fly.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('attendance_session_summaries')) {
            return;
        }

        Schema::create('attendance_session_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_session_id')->unique()->constrained('attendance_sessions')->cascadeOnDelete();

            // Raw counts.
            $table->unsignedInteger('attendance_count')->default(0);
            $table->unsignedInteger('present_count')->default(0);

            // Distance metrics, metres. SMALLINT covers any realistic
            // geofence (max 65 535 m = 65 km).
            $table->unsignedSmallInteger('average_distance')->nullable();
            $table->unsignedSmallInteger('minimum_distance')->nullable();
            $table->unsignedSmallInteger('maximum_distance')->nullable();

            // Color-bucket counts: inside radius / within 10 % of edge /
            // outside the radius. Helps the admin spot drifters at a glance.
            $table->unsignedInteger('inside_count')->default(0);
            $table->unsignedInteger('edge_count')->default(0);
            $table->unsignedInteger('outside_count')->default(0);

            // Identity of the two extreme marks so the map can deep-link.
            // FK to students; ON DELETE SET NULL so deleting a student
            // doesn't break the summary row.
            $table->foreignId('closest_student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->foreignId('farthest_student_id')->nullable()->constrained('students')->nullOnDelete();

            // When the session went inactive and when this row was
            // last computed — lets the lazy rebuild path know whether
            // a cached summary is still trustworthy.
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('refreshed_at')->nullable();

            $table->timestamps();

            // `attendance_session_id` is already UNIQUE via ->unique(),
            // so the lookup path (one row per session) is fully indexed.
            $table->index('closed_at', 'session_summaries_closed_at_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_session_summaries');
    }
};
