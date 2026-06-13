<?php

namespace App\Console\Commands;

use App\Models\AppRelease;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Register an APK as a published release without going through the
 * web upload form. Useful when:
 *   - The APK is larger than PHP's upload_max_filesize on the host.
 *   - You scp'd the file straight to the server and just want to
 *     wire it up.
 *
 * Usage:
 *   php artisan app-releases:register /full/path/to/at-enda-v1.0.1-arm64.apk \
 *       --version-name=1.0.1 --version-code=2 \
 *       --notes="Manual marking + bug fixes" --publish
 *
 * The file is copied (not moved) into the `public` disk under
 * apps/android/<platform>-<versionCode>.apk so the existing
 * download routes serve it without any further changes.
 */
class AppReleasesRegisterCommand extends Command
{
    protected $signature = 'app-releases:register
        {apk : Absolute path to the APK already on the server}
        {--platform=android : Target platform (only android is supported today)}
        {--version-name= : Semver-ish label, e.g. 1.0.1}
        {--version-code= : Monotonically increasing integer}
        {--notes= : Optional release notes shown on /download}
        {--min-supported= : Optional minimum version_code clients must run}
        {--publish : Publish immediately so /download serves it}
        {--required : Mark the update as required for older installs}
        {--released-by= : Optional admin id to record as the publisher}';

    protected $description = 'Register an APK already on disk as an AppRelease row.';

    public function handle(): int
    {
        $src = (string) $this->argument('apk');
        if (! is_file($src) || ! is_readable($src)) {
            $this->error("APK not found or unreadable: {$src}");

            return self::FAILURE;
        }

        $platform = strtolower((string) $this->option('platform'));
        if ($platform !== AppRelease::PLATFORM_ANDROID) {
            $this->error("Only the 'android' platform is supported right now.");

            return self::FAILURE;
        }

        $versionName = trim((string) $this->option('version-name'));
        $versionCode = (int) $this->option('version-code');
        if ($versionName === '' || $versionCode < 1) {
            $this->error('Both --version-name and --version-code are required.');

            return self::FAILURE;
        }
        if (! preg_match('/^[0-9A-Za-z.\-_]+$/', $versionName)) {
            $this->error('Version name may only contain letters, digits, dots, hyphens, and underscores.');

            return self::FAILURE;
        }

        $existing = AppRelease::query()
            ->where('platform', $platform)
            ->where('version_code', $versionCode)
            ->first();
        if ($existing) {
            $this->error("Version code {$versionCode} is already registered for {$platform} (id {$existing->id}). Pick a higher --version-code or delete the old row first.");

            return self::FAILURE;
        }

        $disk = AppRelease::disk();
        $disk->makeDirectory(AppRelease::ANDROID_DIR);

        $relativePath = AppRelease::ANDROID_DIR.'/'.sprintf('%s-%d.apk', $platform, $versionCode);
        $destAbs = $disk->path($relativePath);

        if (! @copy($src, $destAbs)) {
            $this->error("Failed to copy APK into storage at {$destAbs}.");

            return self::FAILURE;
        }
        @chmod($destAbs, 0644);

        $size = @filesize($destAbs) ?: null;
        $sha = hash_file('sha256', $destAbs) ?: null;

        $release = AppRelease::create([
            'platform' => $platform,
            'version_name' => $versionName,
            'version_code' => $versionCode,
            'apk_path' => $relativePath,
            'apk_size_bytes' => $size,
            'apk_sha256' => $sha,
            'release_notes' => $this->option('notes') ?: null,
            'is_published' => (bool) $this->option('publish'),
            'is_required' => (bool) $this->option('required'),
            'min_supported_version_code' => $this->option('min-supported') !== null
                ? (int) $this->option('min-supported')
                : null,
            'released_by_admin_id' => $this->option('released-by') !== null
                ? (int) $this->option('released-by')
                : null,
            'released_at' => now(),
        ]);

        $this->info("Registered v{$release->version_name} (#{$release->version_code}) — {$this->humanSize($size)}.");
        $this->line('SHA-256: '.$sha);
        $this->line('Stored : '.$relativePath);
        $this->line('Public : '.url('/download'));
        if (! $release->is_published) {
            $this->warn('Not published yet — add --publish or flip the toggle in admin.');
        }

        return self::SUCCESS;
    }

    private function humanSize(?int $bytes): string
    {
        $bytes = (int) ($bytes ?? 0);
        if ($bytes <= 0) {
            return '—';
        }
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return sprintf('%.1f %s', $bytes, $units[$i]);
    }
}
