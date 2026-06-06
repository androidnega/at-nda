<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Versioned cache keys for short-TTL aggregations.
 *
 * Pattern:
 *   $key = CacheVersions::key('rep:dashboard:'.$repId, ['attendance']);
 *   $value = Cache::remember($key, 30, fn () => $expensiveQuery());
 *
 * When attendance is written/updated/deleted the model observer
 * bumps the `attendance` version, so the next reader sees a fresh
 * cache key and the stale entry naturally ages out. No manual
 * Cache::forget() loops, no risk of stale dashboard counts.
 */
final class CacheVersions
{
    /**
     * Per-request memo so multiple cache lookups inside one request
     * pay for the version fetch only once.
     *
     * @var array<string, int>
     */
    private static array $memo = [];

    /**
     * Build a cache key that includes the current namespace version
     * for every namespace listed in $namespaces. Bumping any of them
     * naturally invalidates this key.
     *
     * @param  list<string>  $namespaces
     */
    public static function key(string $base, array $namespaces): string
    {
        $stamps = [];
        foreach ($namespaces as $ns) {
            $stamps[] = $ns.'='.self::current($ns);
        }

        return 'atenda:v:'.implode('&', $stamps).':'.$base;
    }

    /**
     * Read the current version counter for a namespace (1 if unset).
     */
    public static function current(string $namespace): int
    {
        if (isset(self::$memo[$namespace])) {
            return self::$memo[$namespace];
        }

        try {
            $value = (int) (Cache::get(self::storageKey($namespace)) ?? 0);
        } catch (\Throwable $e) {
            $value = 0;
        }
        if ($value <= 0) {
            $value = 1;
        }

        return self::$memo[$namespace] = $value;
    }

    /**
     * Bump the namespace counter so every cached key under it
     * becomes stale on the next read.
     */
    public static function bump(string $namespace): int
    {
        $next = self::current($namespace) + 1;
        try {
            Cache::forever(self::storageKey($namespace), $next);
        } catch (\Throwable $e) {
            // Cache backend down — readers fall back to the DB which is
            // the safe path. We re-memo so the rest of the request at
            // least sees the bumped value.
        }
        self::$memo[$namespace] = $next;

        return $next;
    }

    private static function storageKey(string $namespace): string
    {
        return 'atenda:cache_version:'.$namespace;
    }
}
