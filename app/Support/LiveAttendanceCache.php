<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Small TTL cache (defaults to 5s) for the "active sessions for this class/course" reads.
 * High poll traffic (Flutter app + student web tabs) used to all hit the DB whenever someone
 * was marking attendance — pointing CACHE_STORE at Redis turns those into Redis hits.
 *
 * Cache is automatically busted by AttendanceSession::saved/deleted observers (see model).
 */
final class LiveAttendanceCache
{
    private const TTL_SECONDS = 5;

    private const VERSION_TTL = 86400;

    private static function versionKey(): string
    {
        return 'live_sessions:version';
    }

    /**
     * Monotonic version stamp embedded in cache keys. Bumping this is the cheapest way to
     * invalidate every cached list at once when a session opens/closes/expires.
     */
    public static function version(): int
    {
        try {
            return (int) Cache::remember(self::versionKey(), self::VERSION_TTL, fn () => 1);
        } catch (\Throwable $e) {
            return 1;
        }
    }

    public static function bump(): void
    {
        try {
            Cache::increment(self::versionKey());
        } catch (\Throwable $e) {
            Log::debug('LiveAttendanceCache::bump failed: '.$e->getMessage());
        }
    }

    /**
     * @template T
     *
     * @param  callable(): T  $resolver
     * @return T
     */
    public static function remember(string $tag, callable $resolver)
    {
        try {
            $key = sprintf('live_sessions:%d:%s', self::version(), $tag);

            return Cache::remember($key, self::TTL_SECONDS, $resolver);
        } catch (\Throwable $e) {
            Log::debug('LiveAttendanceCache::remember bypassed: '.$e->getMessage());

            return $resolver();
        }
    }
}
