<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Hash;

/**
 * Single source of truth for student / lecturer password verification.
 *
 * Historically each controller carried its own `validatePassword` method
 * that also accepted plaintext-equal comparisons as a transitional
 * accommodation. Centralising the predicate lets us flip the legacy
 * plaintext branch off in ONE place after the one-time rehash command
 * (`php artisan students:rehash-passwords`) has migrated every stored
 * password to bcrypt.
 */
final class PasswordPolicy
{
    /**
     * Returns true when $input matches $stored.
     *
     * When config('auth.allow_plaintext_legacy_passwords') is true
     * AND $stored is not bcrypt-shaped, a constant-time string compare
     * is allowed as a transitional fallback. Default is FALSE — flip
     * to true only during the rehash window described in the
     * remediation plan task §C-06.
     */
    public static function matches(string $input, ?string $stored): bool
    {
        if ($stored === null || $stored === '') {
            return false;
        }

        if (self::isBcrypt($stored)) {
            return Hash::check($input, $stored);
        }

        if (config('auth.allow_plaintext_legacy_passwords', false) === true) {
            // hash_equals avoids timing-based discrimination between
            // length / prefix mismatches; remove this branch after the
            // rehash command has run on every environment.
            return hash_equals((string) $stored, $input);
        }

        return false;
    }

    /**
     * True when a stored value looks like a bcrypt hash. Accepts both
     * the older `$2a$` and the current `$2y$` prefixes that PHP emits.
     */
    public static function isBcrypt(string $stored): bool
    {
        return str_starts_with($stored, '$2y$')
            || str_starts_with($stored, '$2a$');
    }

    /**
     * Used by the rehash command: convert a non-bcrypt stored value
     * into a bcrypt hash, preserving the original input.
     */
    public static function rehash(string $plaintext): string
    {
        return Hash::make($plaintext);
    }
}
