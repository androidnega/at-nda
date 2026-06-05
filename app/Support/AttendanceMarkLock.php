<?php

namespace App\Support;

use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Short-lived lock that collapses concurrent mark-attendance POSTs from the same student
 * into a single DB write. Backed by whatever cache driver is configured — Redis recommended
 * for production (set CACHE_STORE=redis) so several PHP workers share the same lock.
 */
final class AttendanceMarkLock
{
    private const TTL_SECONDS = 10;

    private const WAIT_SECONDS = 4;

    /**
     * Run $callback while holding the per-student/per-session attendance lock.
     * If the cache store has no lock support (e.g. array driver in tests), the
     * callback is executed without locking so behaviour stays correct.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public static function run(int $sessionId, int $studentId, callable $callback)
    {
        $store = Cache::store();

        if (! $store->getStore() instanceof LockProvider) {
            return $callback();
        }

        $key = sprintf('attendance:mark:%d:%d', $sessionId, $studentId);

        /** @var Lock $lock */
        $lock = $store->lock($key, self::TTL_SECONDS);

        try {
            // block() throws LockTimeoutException if it can't acquire within the wait window.
            return $lock->block(self::WAIT_SECONDS, $callback);
        } catch (\Illuminate\Contracts\Cache\LockTimeoutException $e) {
            Log::info('attendance mark lock timeout', [
                'session_id' => $sessionId,
                'student_id' => $studentId,
            ]);

            return $callback();
        }
    }
}
