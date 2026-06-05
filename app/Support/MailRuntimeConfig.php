<?php

namespace App\Support;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Mail;

/**
 * Applies the admin-configured SMTP credentials stored in `system_settings`
 * onto Laravel's mail config at runtime, so super-admins can change SMTP
 * settings from the dashboard without touching .env.
 *
 * Behaviour:
 * - If the optional mail columns are missing OR mail_enabled is false, the
 *   bundled .env / config defaults remain untouched.
 * - When enabled, we swap the default mailer to SMTP and patch
 *   host/port/username/password/encryption + From address.
 */
final class MailRuntimeConfig
{
    private static bool $applied = false;

    public static function applyOnce(): bool
    {
        if (self::$applied) {
            return true;
        }
        self::$applied = true;

        if (! SchemaFeatures::hasMailSettings()) {
            return false;
        }

        try {
            $settings = SystemSetting::get();
        } catch (\Throwable $e) {
            return false;
        }

        if (! ($settings->mail_enabled ?? false)) {
            return false;
        }

        $host = trim((string) ($settings->mail_host ?? ''));
        $port = (int) ($settings->mail_port ?? 0);
        if ($host === '' || $port <= 0) {
            return false;
        }

        $encryption = strtolower((string) ($settings->mail_encryption ?? ''));
        if (! in_array($encryption, ['tls', 'ssl', 'starttls', ''], true)) {
            $encryption = '';
        }

        $fromAddress = trim((string) ($settings->mail_from_address ?? ''));
        $fromName = trim((string) ($settings->mail_from_name ?? ''));
        if ($fromAddress === '') {
            $fromAddress = (string) config('mail.from.address');
        }
        if ($fromName === '') {
            $fromName = (string) (config('mail.from.name') ?: config('app.name'));
        }

        $password = '';
        try {
            $password = (string) ($settings->mail_password_encrypted ?? '');
        } catch (\Throwable $e) {
            // Bad APP_KEY / corrupted ciphertext — keep going without a password.
            $password = '';
        }

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.transport' => 'smtp',
            'mail.mailers.smtp.host' => $host,
            'mail.mailers.smtp.port' => $port,
            'mail.mailers.smtp.username' => $settings->mail_username ?: null,
            'mail.mailers.smtp.password' => $password !== '' ? $password : null,
            'mail.mailers.smtp.encryption' => $encryption !== '' ? $encryption : null,
            'mail.mailers.smtp.scheme' => $encryption === 'ssl' ? 'smtps' : null,
            'mail.from.address' => $fromAddress,
            'mail.from.name' => $fromName,
        ]);

        // Mail manager already booted? Force it to re-read.
        try {
            Mail::purge('smtp');
        } catch (\Throwable $e) {
            // older Laravel versions or already-purged mailer — safe to ignore.
        }

        return true;
    }

    /**
     * Same as applyOnce() but rebuilds the mailer even if we already applied.
     * Useful right after the admin saves new SMTP credentials.
     */
    public static function reapply(): bool
    {
        self::$applied = false;

        return self::applyOnce();
    }

    public static function isConfigured(): bool
    {
        if (! SchemaFeatures::hasMailSettings()) {
            return false;
        }

        try {
            $s = SystemSetting::get();
        } catch (\Throwable $e) {
            return false;
        }

        return (bool) ($s->mail_enabled ?? false)
            && trim((string) ($s->mail_host ?? '')) !== ''
            && (int) ($s->mail_port ?? 0) > 0;
    }
}
