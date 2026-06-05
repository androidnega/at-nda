<?php

namespace App\Support;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * Lets a super-admin flip the cache driver to Redis (host / port / auth /
 * database / prefix) from `/dashboard/settings` without redeploying .env.
 *
 * Behaviour:
 * - When `cache_driver` is null, '', or 'database' the bundled Laravel
 *   config is left intact and we use whatever CACHE_STORE was at boot.
 * - When `cache_driver` is 'redis' AND the columns / extension are
 *   available, we patch `config('database.redis.*')` and
 *   `config('cache.default')` at runtime, then purge the cache manager
 *   so the next Cache::store() call picks up the new connection.
 */
final class RedisRuntimeConfig
{
    private static bool $applied = false;

    public static function applyOnce(): bool
    {
        if (self::$applied) {
            return true;
        }
        self::$applied = true;

        if (! SchemaFeatures::hasRedisSettings()) {
            return false;
        }

        try {
            $settings = SystemSetting::get();
        } catch (\Throwable $e) {
            return false;
        }

        $driver = strtolower((string) ($settings->cache_driver ?? ''));
        if (! in_array($driver, ['redis', 'database', 'file', 'array'], true)) {
            return false;
        }

        if ($driver !== 'redis') {
            // Honour the admin's choice to keep the simple drivers.
            config(['cache.default' => $driver]);

            return false;
        }

        $host = trim((string) ($settings->redis_host ?? ''));
        $port = (int) ($settings->redis_port ?? 0);
        if ($host === '' || $port <= 0) {
            return false;
        }

        $password = null;
        try {
            $password = $settings->redis_password_encrypted ?: null;
        } catch (\Throwable $e) {
            $password = null;
        }

        $database = (int) ($settings->redis_database ?? 0);
        $prefix = trim((string) ($settings->redis_prefix ?? ''));

        config([
            'database.redis.options.prefix' => $prefix !== '' ? $prefix : config('database.redis.options.prefix'),
            'database.redis.default.host' => $host,
            'database.redis.default.port' => $port,
            'database.redis.default.password' => $password,
            'database.redis.default.database' => $database,
            'database.redis.cache.host' => $host,
            'database.redis.cache.port' => $port,
            'database.redis.cache.password' => $password,
            'database.redis.cache.database' => $database,
            'cache.default' => 'redis',
            'cache.stores.redis.driver' => 'redis',
            'cache.stores.redis.connection' => 'cache',
        ]);

        // Force Laravel's cache manager to forget the previously resolved
        // store so the next Cache::store() call uses the new Redis config.
        try {
            Cache::purge('redis');
            Cache::purge('database');
        } catch (\Throwable $e) {
            // Older Laravel versions or already-purged store — safe to ignore.
        }

        return true;
    }

    public static function reapply(): bool
    {
        self::$applied = false;

        return self::applyOnce();
    }

    /**
     * Quick "can we round-trip a value through Redis right now?" check.
     * Returns null on success, a short error string otherwise.
     */
    public static function ping(): ?string
    {
        try {
            $client = Redis::connection();
            $client->set('atenda:redis:ping', (string) now()->timestamp, 'EX', 10);
            $got = $client->get('atenda:redis:ping');
            if ($got === null) {
                return 'Round-trip returned null — Redis may be read-only or evicting.';
            }

            return null;
        } catch (\Throwable $e) {
            Log::warning('Redis ping failed: '.$e->getMessage());

            return $e->getMessage();
        }
    }
}
