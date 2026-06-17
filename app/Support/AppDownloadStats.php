<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

class AppDownloadStats
{
    public const CACHE_KEY = 'stats.app_android_downloads';

    public static function increment(): int
    {
        if (! Cache::has(self::CACHE_KEY)) {
            Cache::forever(self::CACHE_KEY, 0);
        }

        return (int) Cache::increment(self::CACHE_KEY);
    }

    public static function total(): int
    {
        return max(0, (int) Cache::get(self::CACHE_KEY, 0));
    }
}
