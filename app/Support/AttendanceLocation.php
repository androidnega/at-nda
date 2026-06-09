<?php

namespace App\Support;

/**
 * Single source of truth for the attendance haversine + colour buckets.
 *
 * Used by:
 *   - AttendanceController, Api\AttendanceController, OfflineSync — to
 *     populate attendances.distance_from_anchor at write-time. (The
 *     value is computed once, here, then NEVER recomputed during map
 *     rendering — that is the explicit performance rule.)
 *   - AttendanceSessionSummaryService — to derive aggregate counts.
 *   - AttendanceMapController — to bucket markers into in / edge / out
 *     colours from the stored distance.
 *
 * All public methods are pure functions: no DB, no I/O, no logging.
 * Safe to call from inside tight loops.
 */
final class AttendanceLocation
{
    /** Earth radius in metres (mean radius). */
    public const EARTH_RADIUS_M = 6371000;

    /**
     * Distance between two GPS points in metres. Same haversine
     * formula already used by AttendanceController::distance(); we
     * centralise it here so the validation code path and the map
     * write-path produce identical numbers (no off-by-rounding drift).
     */
    public static function distanceMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return self::EARTH_RADIUS_M * $c;
    }

    /**
     * Clamp a metre value into the SMALLINT UNSIGNED range used by the
     * `attendances.distance_from_anchor` column (0–65 535). Anything
     * larger means the coordinates are bogus or the student is on a
     * different continent — the clamp keeps the write safe regardless.
     */
    public static function storableMeters(float $meters): int
    {
        if (! is_finite($meters) || $meters < 0) {
            return 0;
        }

        return (int) min(65535, round($meters));
    }

    /**
     * Convenience wrapper: returns a column-ready integer or NULL when
     * either of the lat/lng pairs is missing / NaN.
     */
    public static function storableMetersFromPairs(
        float|int|string|null $sessionLat,
        float|int|string|null $sessionLng,
        float|int|string|null $studentLat,
        float|int|string|null $studentLng,
    ): ?int {
        if (! self::isNumericCoord($sessionLat)
            || ! self::isNumericCoord($sessionLng)
            || ! self::isNumericCoord($studentLat)
            || ! self::isNumericCoord($studentLng)) {
            return null;
        }

        return self::storableMeters(self::distanceMeters(
            (float) $sessionLat,
            (float) $sessionLng,
            (float) $studentLat,
            (float) $studentLng,
        ));
    }

    /**
     * Colour bucket for a marker:
     *   in    — strictly inside the geofence
     *   edge  — within 10 % of the radius boundary (warning zone)
     *   out   — beyond the radius (admin review)
     *
     * Mirrors the spec exactly:
     *   "Green   = Inside valid range"
     *   "Yellow  = Within 10 percent of boundary (e.g. 360-400m of 400m)"
     *   "Red     = Only for administrator-approved exceptions"  ← outside
     *
     * Returns 'in' when either the distance or the radius is unknown,
     * because we should NEVER paint someone red without evidence.
     */
    public static function colorBucket(?int $distanceM, ?int $radiusM): string
    {
        if ($distanceM === null || $radiusM === null || $radiusM <= 0) {
            return 'in';
        }

        if ($distanceM > $radiusM) {
            return 'out';
        }

        $edgeFloor = (int) floor($radiusM * 0.9);
        if ($distanceM >= $edgeFloor) {
            return 'edge';
        }

        return 'in';
    }

    private static function isNumericCoord(float|int|string|null $v): bool
    {
        if ($v === null || $v === '') {
            return false;
        }

        return is_numeric($v);
    }
}
