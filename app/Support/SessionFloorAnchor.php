<?php

namespace App\Support;

use App\Models\AttendanceSession;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Barometer / altitude anchor captured when a session location is set.
 * Used to confirm same-floor attendance when GPS is weak indoors.
 */
class SessionFloorAnchor
{
    private const CACHE_PREFIX = 'session_floor_anchor:';

    /**
     * @param  array<string, mixed>  $clientMeta
     */
    public static function storeFromClientMeta(AttendanceSession $session, array $clientMeta): void
    {
        $pressure = self::numericMeta($clientMeta, ['pressure_hpa', 'pressure', 'barometer_hpa']);
        $altitude = self::numericMeta($clientMeta, ['altitude_m', 'altitude', 'altitude_meters']);

        if ($pressure === null && $altitude === null) {
            return;
        }

        $until = self::anchorUntil($session);
        Cache::put(self::cacheKey((int) $session->id), array_filter([
            'pressure_hpa' => $pressure,
            'altitude_m' => $altitude,
        ], fn ($v) => $v !== null), $until);
    }

    /**
     * True when the student's pressure/altitude matches the session anchor
     * within one typical floor (~4 hPa or ~12 m).
     *
     * @param  array<string, mixed>  $clientMeta
     */
    public static function floorMatches(AttendanceSession $session, array $clientMeta): bool
    {
        $anchor = Cache::get(self::cacheKey((int) $session->id));
        if (! is_array($anchor)) {
            return false;
        }

        $studentPressure = self::numericMeta($clientMeta, ['pressure_hpa', 'pressure', 'barometer_hpa']);
        $studentAltitude = self::numericMeta($clientMeta, ['altitude_m', 'altitude', 'altitude_meters']);

        $anchorPressure = isset($anchor['pressure_hpa']) ? (float) $anchor['pressure_hpa'] : null;
        if ($anchorPressure !== null && $studentPressure !== null) {
            return abs($studentPressure - $anchorPressure) <= 4.0;
        }

        $anchorAltitude = isset($anchor['altitude_m']) ? (float) $anchor['altitude_m'] : null;
        if ($anchorAltitude !== null && $studentAltitude !== null) {
            return abs($studentAltitude - $anchorAltitude) <= 12.0;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @param  list<string>  $keys
     */
    private static function numericMeta(array $meta, array $keys): ?float
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $meta)) {
                continue;
            }
            $v = $meta[$key];
            if (is_numeric($v)) {
                return (float) $v;
            }
        }

        return null;
    }

    private static function cacheKey(int $sessionId): string
    {
        return self::CACHE_PREFIX.$sessionId;
    }

    private static function anchorUntil(AttendanceSession $session): Carbon
    {
        foreach ([$session->end_time, $session->expires_at, $session->expected_end_time] as $t) {
            if ($t instanceof Carbon && $t->isFuture()) {
                return $t->copy()->addHour();
            }
        }

        return now()->addHours(4);
    }
}
