<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Shared-device flow: after a student marks attendance on the web,
 * arm a short window where sign-out is allowed even if a class
 * session is still active, then auto-log out from the success page.
 */
class PostMarkAutoLogout
{
    public const SESSION_UNTIL = 'post_mark_auto_logout_until';

    public const SESSION_DELAY = 'post_mark_auto_logout_delay';

    public const MIN_SECONDS = 25;

    public const MAX_SECONDS = 35;

    /**
     * Pick a random delay (25–35 s) and store it in session so the
     * success page and the logout endpoint can honour the same window.
     */
    public static function arm(Request $request): int
    {
        $seconds = random_int(self::MIN_SECONDS, self::MAX_SECONDS);
        // Grace beyond the countdown so a slow client can still POST logout.
        $request->session()->put(self::SESSION_UNTIL, now()->addSeconds($seconds + 45)->timestamp);
        $request->session()->put(self::SESSION_DELAY, $seconds);

        return $seconds;
    }

    public static function delaySeconds(Request $request): int
    {
        $stored = (int) $request->session()->get(self::SESSION_DELAY, 0);
        if ($stored >= self::MIN_SECONDS && $stored <= self::MAX_SECONDS) {
            return $stored;
        }

        return random_int(self::MIN_SECONDS, self::MAX_SECONDS);
    }

    public static function isArmed(Request $request): bool
    {
        return (int) $request->session()->get(self::SESSION_UNTIL, 0) >= time();
    }

    /**
     * Consume the one-time flag when the auto-logout form fires.
     */
    public static function consume(Request $request): bool
    {
        if (! self::isArmed($request)) {
            return false;
        }

        $request->session()->forget([self::SESSION_UNTIL, self::SESSION_DELAY]);

        return true;
    }
}
