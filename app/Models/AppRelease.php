<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * @property int    $id
 * @property string $platform
 * @property string $version_name
 * @property int    $version_code
 * @property string $apk_path
 * @property int|null $apk_size_bytes
 * @property string|null $apk_sha256
 * @property string|null $release_notes
 * @property bool $is_published
 * @property bool $is_required
 * @property int|null $min_supported_version_code
 * @property int|null $released_by_admin_id
 * @property \Illuminate\Support\Carbon|null $released_at
 */
class AppRelease extends Model
{
    public const PLATFORM_ANDROID = 'android';

    public const ANDROID_DISK = 'public';
    public const ANDROID_DIR = 'apps/android';

    protected $fillable = [
        'platform',
        'version_name',
        'version_code',
        'apk_path',
        'apk_size_bytes',
        'apk_sha256',
        'release_notes',
        'is_published',
        'is_required',
        'min_supported_version_code',
        'released_by_admin_id',
        'released_at',
    ];

    protected $casts = [
        'version_code' => 'integer',
        'apk_size_bytes' => 'integer',
        'is_published' => 'boolean',
        'is_required' => 'boolean',
        'min_supported_version_code' => 'integer',
        'released_by_admin_id' => 'integer',
        'released_at' => 'datetime',
    ];

    public static function disk(): \Illuminate\Contracts\Filesystem\Filesystem
    {
        return Storage::disk(self::ANDROID_DISK);
    }

    /**
     * Latest published release for a given platform, or null when
     * the admin hasn't published one yet.
     */
    public static function latestPublishedFor(string $platform): ?self
    {
        return static::query()
            ->where('platform', $platform)
            ->where('is_published', true)
            ->orderByDesc('version_code')
            ->first();
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeForPlatform(Builder $query, string $platform): Builder
    {
        return $query->where('platform', $platform);
    }

    public function isAndroid(): bool
    {
        return $this->platform === self::PLATFORM_ANDROID;
    }

    /**
     * Does the APK still exist on disk? Releases whose file got
     * deleted (e.g. someone cleared storage) are silently skipped
     * by the public download/API endpoints so users don't end up
     * with a broken 404 link.
     */
    public function fileExists(): bool
    {
        return $this->apk_path !== '' && self::disk()->exists($this->apk_path);
    }

    /**
     * Public URL the mobile app + the web download page link to.
     * We route through a controller so we can keep download analytics,
     * enforce per-file headers, and serve from storage rather than
     * exposing the raw asset path.
     */
    public function downloadUrl(): string
    {
        return url("/download/android/{$this->version_code}.apk");
    }

    public function humanSize(): string
    {
        $bytes = (int) ($this->apk_size_bytes ?? 0);
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
