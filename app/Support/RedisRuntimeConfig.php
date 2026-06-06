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
            'database.redis.client' => self::pickClient(),
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

    /**
     * Try, in priority order, every Redis endpoint the host is likely to
     * expose:
     *
     *   1. REDIS_URL env (single-string PaaS form, e.g. Render/Railway).
     *   2. REDIS_HOST + REDIS_PORT (+ optional password / db) from .env.
     *   3. The values already saved in system_settings (so re-running
     *      auto-config never destroys a working config).
     *   4. 127.0.0.1:6379 — the cPanel / dedicated-host default once the
     *      provider enables Redis on the account.
     *
     * The first candidate that round-trips a `ping` is applied to
     * config(...) at runtime AND returned so the caller can persist it.
     *
     * @return array{
     *     ok: bool,
     *     host?: string,
     *     port?: int,
     *     database?: int,
     *     password?: string|null,
     *     prefix?: string,
     *     source?: string,
     *     attempts?: array<int, array{label: string, host: string, port: int, error: string}>,
     * }
     */
    public static function autoDiscover(): array
    {
        // Bail early with a friendly message when neither phpredis nor
        // predis/predis is available — otherwise every candidate will
        // fail with the cryptic "Class Redis not found" error.
        if (! extension_loaded('redis') && ! class_exists(\Predis\Client::class)) {
            return [
                'ok' => false,
                'attempts' => [[
                    'label' => 'preflight',
                    'host' => '-',
                    'port' => 0,
                    'error' => 'No Redis client installed. Run `composer require predis/predis` on the server, or enable the phpredis extension in php.ini.',
                ]],
            ];
        }

        $defaultPrefix = self::defaultPrefix();
        $candidates = [];

        // 1. REDIS_URL — covers Render, Railway, Upstash, Heroku.
        $url = trim((string) (env('REDIS_URL') ?? env('CACHE_URL') ?? ''));
        if ($url !== '' && ($parsed = self::parseRedisUrl($url)) !== null) {
            $candidates[] = $parsed + ['source' => 'env REDIS_URL'];
        }

        // 2. Individual REDIS_* env vars.
        $envHost = trim((string) env('REDIS_HOST', ''));
        if ($envHost !== '') {
            $candidates[] = [
                'host' => $envHost,
                'port' => (int) env('REDIS_PORT', 6379) ?: 6379,
                'password' => self::stringOrNull(env('REDIS_PASSWORD')),
                'database' => (int) env('REDIS_CACHE_DB', env('REDIS_DB', 0)),
                'prefix' => $defaultPrefix,
                'source' => 'env REDIS_HOST',
            ];
        }

        // 3. Whatever is already saved in system_settings.
        try {
            $settings = SystemSetting::get();
            $savedHost = trim((string) ($settings->redis_host ?? ''));
            if ($savedHost !== '') {
                $candidates[] = [
                    'host' => $savedHost,
                    'port' => (int) ($settings->redis_port ?? 6379) ?: 6379,
                    'password' => self::stringOrNull($settings->redis_password_encrypted ?? null),
                    'database' => (int) ($settings->redis_database ?? 0),
                    'prefix' => trim((string) ($settings->redis_prefix ?? '')) ?: $defaultPrefix,
                    'source' => 'previously saved settings',
                ];
            }
        } catch (\Throwable $e) {
            // Not fatal — we still have the localhost candidate below.
        }

        // 4. Localhost — works on cPanel once provider activates Redis,
        //    and on every dev box where you ran `redis-server`.
        $candidates[] = [
            'host' => '127.0.0.1',
            'port' => 6379,
            'password' => null,
            'database' => 0,
            'prefix' => $defaultPrefix,
            'source' => 'localhost fallback',
        ];

        $attempts = [];
        $seen = [];
        foreach ($candidates as $cand) {
            $key = $cand['host'].':'.$cand['port'].':'.($cand['password'] ?? '').':'.($cand['database'] ?? 0);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $err = self::tryConnection(
                $cand['host'],
                (int) $cand['port'],
                $cand['password'] ?? null,
                (int) ($cand['database'] ?? 0),
                $cand['prefix'] ?? $defaultPrefix
            );
            if ($err === null) {
                self::$applied = false;
                self::applyOnce(); // best-effort re-apply for the rest of this request
                return [
                    'ok' => true,
                    'host' => $cand['host'],
                    'port' => (int) $cand['port'],
                    'database' => (int) ($cand['database'] ?? 0),
                    'password' => $cand['password'] ?? null,
                    'prefix' => $cand['prefix'] ?? $defaultPrefix,
                    'source' => $cand['source'] ?? 'auto',
                    'attempts' => $attempts,
                ];
            }
            $attempts[] = [
                'label' => $cand['source'] ?? 'candidate',
                'host' => (string) $cand['host'],
                'port' => (int) $cand['port'],
                'error' => $err,
            ];
        }

        return ['ok' => false, 'attempts' => $attempts];
    }

    /**
     * Patch the live config to point at this candidate, then run the
     * standard ping. Returns null on success, a short error otherwise.
     */
    private static function tryConnection(string $host, int $port, ?string $password, int $database, string $prefix): ?string
    {
        if ($host === '' || $port <= 0) {
            return 'invalid host/port';
        }

        try {
            config([
                'database.redis.client' => self::pickClient(),
                'database.redis.options.prefix' => $prefix !== '' ? $prefix : config('database.redis.options.prefix'),
                'database.redis.default.host' => $host,
                'database.redis.default.port' => $port,
                'database.redis.default.password' => $password,
                'database.redis.default.database' => $database,
                'database.redis.cache.host' => $host,
                'database.redis.cache.port' => $port,
                'database.redis.cache.password' => $password,
                'database.redis.cache.database' => $database,
            ]);

            // Force the redis manager to reconnect with the patched values.
            try {
                app('redis')->purge('default');
                app('redis')->purge('cache');
            } catch (\Throwable $e) {
                // older Laravel — safe to ignore
            }

            return self::ping();
        } catch (\Throwable $e) {
            return $e->getMessage();
        }
    }

    private static function parseRedisUrl(string $url): ?array
    {
        $parts = @parse_url($url);
        if (! is_array($parts) || empty($parts['host'])) {
            return null;
        }

        $database = 0;
        if (! empty($parts['path']) && trim($parts['path'], '/') !== '') {
            $database = (int) trim($parts['path'], '/');
        }

        return [
            'host' => (string) $parts['host'],
            'port' => (int) ($parts['port'] ?? 6379),
            'password' => self::stringOrNull($parts['pass'] ?? null),
            'database' => $database,
            'prefix' => self::defaultPrefix(),
        ];
    }

    /**
     * Pick whichever Redis client the host actually has available.
     * phpredis (extension) is faster but unavailable on most shared
     * hosting; predis/predis is pure PHP and works anywhere as long
     * as it has been composer-installed.
     */
    private static function pickClient(): string
    {
        if (extension_loaded('redis')) {
            return 'phpredis';
        }
        if (class_exists(\Predis\Client::class)) {
            return 'predis';
        }

        // Last resort — keep whatever was configured in .env so the
        // upstream error remains explicit.
        return (string) (config('database.redis.client') ?: 'phpredis');
    }

    private static function defaultPrefix(): string
    {
        $slug = strtolower((string) config('app.name', 'atenda'));
        $slug = (string) preg_replace('/[^a-z0-9]+/', '_', $slug);
        $slug = trim($slug, '_');

        return ($slug === '' ? 'atenda' : $slug).'_cache_';
    }

    private static function stringOrNull(mixed $v): ?string
    {
        if (! is_string($v)) {
            return null;
        }
        $v = trim($v);

        return $v === '' ? null : $v;
    }
}
