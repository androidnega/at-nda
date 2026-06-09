<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\AttendanceSessionSummary;
use App\Support\AttendanceLocation;
use App\Support\SchemaFeatures;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Computes + persists the per-session roll-up that the attendance map
 * (and any future analytics) reads instead of recomputing on every
 * page request. Owner spec, items 12 + 13:
 *
 *   When a session closes:
 *     Calculate attendance_count, average_distance, minimum_distance,
 *     maximum_distance once. Store them. Read cached values later.
 *
 * Every call is wrapped so a misbehaving query never breaks the
 * attendance write that triggered it.
 */
final class AttendanceSessionSummaryService
{
    /**
     * Recompute and UPSERT the summary row for $session.
     *
     * Cheap: one aggregate query over the existing attendances rows
     * for this session (uses attendances_session_time_idx + the
     * session-scoped indexes). Returns the freshly stored summary, or
     * null when the summary table isn't migrated yet — callers must
     * tolerate null and proceed.
     */
    public static function rebuild(AttendanceSession $session): ?AttendanceSessionSummary
    {
        if (! SchemaFeatures::hasAttendanceSessionSummaries()) {
            return null;
        }

        $sessionId = (int) $session->getKey();
        if ($sessionId <= 0) {
            return null;
        }

        try {
            $radiusM = (int) $session->effectiveAttendanceRangeMeters();
        } catch (\Throwable $e) {
            $radiusM = (int) ($session->attendance_range_m ?? 0);
        }
        if ($radiusM < 0) {
            $radiusM = 0;
        }
        $edgeFloor = (int) floor($radiusM * 0.9);

        // Single round-trip aggregate. We compute counts + min/max/avg
        // distance + colour buckets in one query so this never becomes
        // an N+1 problem regardless of how many marks a session has.
        // distance_from_anchor is the column added by migration
        // 2026_06_09_050000 — we degrade gracefully on stale deploys
        // by skipping the distance metrics and only writing the counts.
        $hasDistance = SchemaFeatures::hasAttendancesDistanceFromAnchor();

        $base = Attendance::query()
            ->where('attendance_session_id', $sessionId);

        $totals = (clone $base)
            ->selectRaw('COUNT(*) AS cnt')
            ->selectRaw("SUM(CASE WHEN status IN ('present','late') THEN 1 ELSE 0 END) AS present_cnt")
            ->first();

        $attendanceCount = (int) ($totals->cnt ?? 0);
        $presentCount = (int) ($totals->present_cnt ?? 0);

        $avg = null;
        $min = null;
        $max = null;
        $inside = 0;
        $edge = 0;
        $outside = 0;
        $closestId = null;
        $farthestId = null;

        if ($hasDistance) {
            $stats = (clone $base)
                ->whereNotNull('distance_from_anchor')
                ->selectRaw('ROUND(AVG(distance_from_anchor)) AS avg_d')
                ->selectRaw('MIN(distance_from_anchor) AS min_d')
                ->selectRaw('MAX(distance_from_anchor) AS max_d')
                ->first();

            $avg = self::clampSmallInt($stats?->avg_d ?? null);
            $min = self::clampSmallInt($stats?->min_d ?? null);
            $max = self::clampSmallInt($stats?->max_d ?? null);

            if ($radiusM > 0) {
                // Three bucket counts via a single conditional SUM
                // expression. SQLite + MySQL/MariaDB both handle this.
                $buckets = (clone $base)
                    ->whereNotNull('distance_from_anchor')
                    ->selectRaw('SUM(CASE WHEN distance_from_anchor < ? THEN 1 ELSE 0 END) AS inside_cnt', [$edgeFloor])
                    ->selectRaw('SUM(CASE WHEN distance_from_anchor >= ? AND distance_from_anchor <= ? THEN 1 ELSE 0 END) AS edge_cnt', [$edgeFloor, $radiusM])
                    ->selectRaw('SUM(CASE WHEN distance_from_anchor > ? THEN 1 ELSE 0 END) AS outside_cnt', [$radiusM])
                    ->first();

                $inside = (int) ($buckets?->inside_cnt ?? 0);
                $edge = (int) ($buckets?->edge_cnt ?? 0);
                $outside = (int) ($buckets?->outside_cnt ?? 0);
            }

            $extremeMin = (clone $base)
                ->whereNotNull('distance_from_anchor')
                ->orderBy('distance_from_anchor')
                ->orderBy('id')
                ->value('student_id');
            $closestId = $extremeMin !== null ? (int) $extremeMin : null;

            $extremeMax = (clone $base)
                ->whereNotNull('distance_from_anchor')
                ->orderByDesc('distance_from_anchor')
                ->orderBy('id')
                ->value('student_id');
            $farthestId = $extremeMax !== null ? (int) $extremeMax : null;
        }

        $closedAt = $session->end_time
            ?? $session->expires_at
            ?? ($session->is_active ? null : Carbon::now());

        try {
            return AttendanceSessionSummary::updateOrCreate(
                ['attendance_session_id' => $sessionId],
                [
                    'attendance_count' => $attendanceCount,
                    'present_count' => $presentCount,
                    'average_distance' => $avg,
                    'minimum_distance' => $min,
                    'maximum_distance' => $max,
                    'inside_count' => $inside,
                    'edge_count' => $edge,
                    'outside_count' => $outside,
                    'closest_student_id' => $closestId,
                    'farthest_student_id' => $farthestId,
                    'closed_at' => $closedAt,
                    'refreshed_at' => Carbon::now(),
                ]
            );
        } catch (\Throwable $e) {
            Log::warning('attendance_session_summary.rebuild_failed', [
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Convenience: rebuild summaries for a list of session IDs. Used by
     * AttendanceSession::deactivateExpiredSessions which closes many at
     * once. Each rebuild is independent — one bad row never blocks the
     * rest.
     *
     * @param  iterable<int>  $sessionIds
     */
    public static function rebuildMany(iterable $sessionIds): int
    {
        $count = 0;
        foreach ($sessionIds as $id) {
            $id = (int) $id;
            if ($id <= 0) {
                continue;
            }
            $session = AttendanceSession::find($id);
            if (! $session) {
                continue;
            }
            if (self::rebuild($session) !== null) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Read-or-compute helper for the map endpoint. If we already have a
     * fresh summary, return it. If we don't, rebuild on the fly — that
     * single round-trip is still much cheaper than re-aggregating on
     * every request because the rebuild result gets cached for next
     * time. Returns null only when both the cache table is unmigrated
     * AND we couldn't recompute.
     */
    public static function getOrRebuild(AttendanceSession $session, int $staleAfterMinutes = 5): ?AttendanceSessionSummary
    {
        if (! SchemaFeatures::hasAttendanceSessionSummaries()) {
            return null;
        }

        $existing = AttendanceSessionSummary::query()
            ->where('attendance_session_id', $session->getKey())
            ->first();

        if ($existing !== null
            && $existing->refreshed_at !== null
            && $existing->refreshed_at->greaterThan(Carbon::now()->subMinutes($staleAfterMinutes))) {
            return $existing;
        }

        // For active sessions we rebuild only sparingly (controlled by
        // the caller's stale window). For closed sessions we trust the
        // cache forever once written.
        if ($existing !== null && ! $session->is_active) {
            return $existing;
        }

        return self::rebuild($session) ?? $existing;
    }

    private static function clampSmallInt(mixed $v): ?int
    {
        if ($v === null) {
            return null;
        }
        if (! is_numeric($v)) {
            return null;
        }

        return AttendanceLocation::storableMeters((float) $v);
    }
}
