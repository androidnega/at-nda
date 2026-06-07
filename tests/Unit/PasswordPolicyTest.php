<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\PasswordPolicy;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Phase 1 / audit C-06 regression suite for the centralised password
 * predicate (App\Support\PasswordPolicy).
 *
 * These tests fail fast if any future PR re-enables a plaintext
 * password compare outside the documented one-shot rehash window,
 * or weakens the bcrypt-shape detection.
 *
 * No DB required — every test exercises pure config + Hash facade
 * behaviour. Safe to run as a unit test (no RefreshDatabase trait).
 */
class PasswordPolicyTest extends TestCase
{
    public function test_returns_false_when_stored_is_null_or_empty(): void
    {
        $this->assertFalse(PasswordPolicy::matches('whatever', null));
        $this->assertFalse(PasswordPolicy::matches('whatever', ''));
    }

    public function test_returns_true_for_valid_bcrypt_match(): void
    {
        $hash = Hash::make('correct-horse-battery-staple');
        $this->assertTrue(PasswordPolicy::matches('correct-horse-battery-staple', $hash));
    }

    public function test_returns_false_for_wrong_bcrypt_input(): void
    {
        $hash = Hash::make('correct-horse-battery-staple');
        $this->assertFalse(PasswordPolicy::matches('wrong', $hash));
    }

    public function test_rejects_plaintext_when_legacy_flag_is_off(): void
    {
        config(['auth.allow_plaintext_legacy_passwords' => false]);
        $this->assertFalse(PasswordPolicy::matches('plaintext', 'plaintext'));
    }

    public function test_accepts_plaintext_when_legacy_flag_is_on(): void
    {
        config(['auth.allow_plaintext_legacy_passwords' => true]);
        $this->assertTrue(PasswordPolicy::matches('plaintext', 'plaintext'));
        $this->assertFalse(PasswordPolicy::matches('plaintext', 'other'));
    }

    public function test_is_bcrypt_recognises_both_prefixes(): void
    {
        $this->assertTrue(PasswordPolicy::isBcrypt('$2y$10$abc'));
        $this->assertTrue(PasswordPolicy::isBcrypt('$2a$10$abc'));
        $this->assertFalse(PasswordPolicy::isBcrypt('plaintext'));
        $this->assertFalse(PasswordPolicy::isBcrypt(''));
    }

    public function test_rehash_produces_a_bcrypt_hash(): void
    {
        $hash = PasswordPolicy::rehash('plaintext');
        $this->assertTrue(PasswordPolicy::isBcrypt($hash));
        $this->assertTrue(Hash::check('plaintext', $hash));
    }
}
