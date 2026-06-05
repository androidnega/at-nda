<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie as SymfonyCookie;

/**
 * Long-lived signed cookie that survives session clears + private-mode
 * shutdowns within the same browser profile. Used to tell two students
 * apart even when they wipe local storage and re-login.
 *
 * - Cookie name: `atenda_dfp`
 * - Signed by Laravel's EncryptCookies middleware (so it's tamper-proof).
 * - 365-day lifetime; HttpOnly; SameSite=Lax so it carries on POST.
 */
final class DeviceFingerprint
{
    public const COOKIE = 'atenda_dfp';

    private const TTL_MINUTES = 60 * 24 * 365; // 1 year

    private static ?string $resolved = null;

    /**
     * Read the fingerprint from the current request. Generates and queues a
     * new one if absent so the cookie is set on the response.
     */
    public static function ensure(Request $request): string
    {
        if (self::$resolved !== null) {
            return self::$resolved;
        }

        $existing = (string) $request->cookie(self::COOKIE, '');
        if (preg_match('/^[a-f0-9]{32,64}$/i', $existing) === 1) {
            self::$resolved = $existing;
            self::queueRefresh($existing);

            return $existing;
        }

        $value = bin2hex(random_bytes(24));
        self::$resolved = $value;
        self::queueRefresh($value);

        return $value;
    }

    public static function current(): ?string
    {
        if (self::$resolved !== null) {
            return self::$resolved;
        }

        $req = function_exists('request') ? request() : null;
        if (! $req) {
            return null;
        }

        return self::ensure($req);
    }

    private static function queueRefresh(string $value): void
    {
        Cookie::queue(
            self::COOKIE,
            $value,
            self::TTL_MINUTES,
            '/',
            null,
            null,
            true, // HttpOnly
            false,
            SymfonyCookie::SAMESITE_LAX
        );
    }
}
