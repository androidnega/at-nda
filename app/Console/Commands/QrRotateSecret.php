<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AttendanceSession;
use App\Support\SecureQrToken;
use Illuminate\Console\Command;

/**
 * php artisan qr:rotate-secret [--secret=...] [--dry-run]
 *
 * Generates a fresh 32-byte HMAC secret (or accepts one via --secret)
 * and re-encodes the qr_token on every active session so signed
 * tokens become valid immediately. Prints the new secret so the
 * operator can save it to .env. The new secret only takes effect for
 * subsequent HTTP requests AFTER the operator updates .env and runs
 * `php artisan config:clear` (or `config:cache`).
 *
 * Deployed Flutter clients hold a COMPILE-TIME copy of QR_SECRET via
 * --dart-define and verify the HMAC offline before submitting. Until
 * a rebuilt APK with the new value ships, those clients reject newly
 * signed QR codes client-side ("Invalid QR (tampered or wrong
 * secret)"). The 6-char rotating manual code and the static session
 * code remain functional because they are validated server-side only.
 */
class QrRotateSecret extends Command
{
    protected $signature = 'qr:rotate-secret
        {--secret= : Use this value instead of generating a random one}
        {--dry-run : Show what would change without writing}';

    protected $description = 'Generate or rotate the HMAC secret used for signed QR tokens, and re-encode every active session.';

    public function handle(): int
    {
        $provided = (string) ($this->option('secret') ?? '');
        $dryRun = (bool) $this->option('dry-run');

        $secret = $provided !== ''
            ? $provided
            : bin2hex(random_bytes(32));

        if (strlen($secret) < 32) {
            $this->error('Refusing to use a secret shorter than 32 chars. Provide a stronger one or omit --secret.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('New QR_SECRET (write this to your .env):');
        $this->line('  QR_SECRET='.$secret);
        $this->newLine();

        // Surface the single hardest backward-compatibility break BEFORE
        // any work happens, so the operator sees it whether they ran with
        // --dry-run or for real. The Flutter client bakes QR_SECRET in at
        // build time via --dart-define and verifies the HMAC offline; an
        // APK signed against the old secret will reject the freshly
        // re-encoded QRs at the camera step with "Invalid QR (tampered or
        // wrong secret)" and never call /api/attendance.
        $this->warn('COMPATIBILITY WARNING');
        $this->line('  Deployed Flutter builds that bundled the previous QR_SECRET');
        $this->line('  (compile-time --dart-define=QR_SECRET=...) will REJECT newly');
        $this->line('  signed QR codes client-side with "Invalid QR (tampered or wrong');
        $this->line('  secret)" until a rebuilt APK with the new value is shipped.');
        $this->line('  Students on the old build can still mark attendance via the');
        $this->line('  6-char rotating manual code or the static session_code printed');
        $this->line('  beneath the QR — both validate server-side only.');
        $this->newLine();

        if ($dryRun) {
            $count = AttendanceSession::query()->where('is_active', true)->count();
            $this->info(sprintf('[DRY RUN] Would re-encode %d active sessions after .env is updated.', $count));

            return self::SUCCESS;
        }

        // Temporarily inject the new secret into the running config so
        // SecureQrToken::encode() uses it during this command. The
        // operator must still copy it into .env for subsequent
        // requests (PHP-FPM workers won't see it otherwise).
        config(['qr.secret' => $secret]);

        $touched = 0;
        AttendanceSession::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->chunkById(100, function ($sessions) use (&$touched): void {
                foreach ($sessions as $session) {
                    try {
                        $session->qr_token = SecureQrToken::encode($session);
                        $session->saveQuietly();
                        $touched++;
                    } catch (\Throwable $e) {
                        $this->warn(sprintf('Session %d failed: %s', $session->id, $e->getMessage()));
                    }
                }
            });

        $this->info(sprintf('Re-encoded %d active sessions.', $touched));
        $this->warn('Remember to:');
        $this->line('  1. Save QR_SECRET to .env');
        $this->line('  2. php artisan config:cache');
        $this->line('  3. Verify with: tail -F storage/logs/laravel-*.log | grep QR-DEBUG');
        $this->line('  4. Rebuild & re-distribute the Flutter app with the new');
        $this->line('     --dart-define=QR_SECRET so deployed installs accept newly');
        $this->line('     signed QRs (see COMPATIBILITY WARNING above).');

        return self::SUCCESS;
    }
}
