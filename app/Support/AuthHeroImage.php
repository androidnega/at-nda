<?php

namespace App\Support;

use App\Models\SystemSetting;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

final class AuthHeroImage
{
    public const DEFAULT_PUBLIC_PATH = 'images/auth/lecturer-laptop-attendance-system-login.jpg';

    public const CUSTOM_PUBLIC_PATH = 'images/auth/login-hero-custom.jpg';

    private const MAX_BYTES = 500_000;

    private const MAX_WIDTH = 1280;

    public static function ensureColumn(): bool
    {
        if (! Schema::hasTable('system_settings')) {
            return false;
        }

        if (Schema::hasColumn('system_settings', 'auth_hero_image_path')) {
            return true;
        }

        try {
            Schema::table('system_settings', function (Blueprint $table): void {
                $table->string('auth_hero_image_path', 512)->nullable();
            });

            return Schema::hasColumn('system_settings', 'auth_hero_image_path');
        } catch (\Throwable $e) {
            report($e);

            return false;
        }
    }

    public static function hasColumn(): bool
    {
        return Schema::hasColumn('system_settings', 'auth_hero_image_path');
    }

    /**
     * Path or URL passed to auth-hero-panel (not yet resolved through asset()).
     */
    public static function pathForViews(): string
    {
        $env = env('AUTH_HERO_IMAGE');
        if (is_string($env) && $env !== '') {
            return $env;
        }

        if (static::hasColumn()) {
            $custom = SystemSetting::get()->auth_hero_image_path;
            if (is_string($custom) && $custom !== '' && static::publicFileExists($custom)) {
                return $custom;
            }
        }

        $config = config('app.auth_hero_image');

        return is_string($config) && $config !== '' ? $config : self::DEFAULT_PUBLIC_PATH;
    }

    public static function previewUrl(): string
    {
        $path = self::pathForViews();
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return asset(ltrim($path, '/')).'?v='.self::cacheVersion($path);
    }

    public static function isUsingCustomUpload(): bool
    {
        if (! static::hasColumn()) {
            return false;
        }

        $custom = SystemSetting::get()->auth_hero_image_path;

        return is_string($custom) && $custom !== '' && static::publicFileExists($custom);
    }

    /**
     * @return array{ok: bool, message?: string}
     */
    public static function storeUpload(UploadedFile $file): array
    {
        if (! static::ensureColumn()) {
            return ['ok' => false, 'message' => 'Database is missing auth_hero_image_path. Run migrations.'];
        }

        $absolute = static::compressToPublicPath($file, self::CUSTOM_PUBLIC_PATH);
        if ($absolute === null) {
            return ['ok' => false, 'message' => 'Could not process image. Use JPEG, PNG, or WebP under 8 MB.'];
        }

        $settings = SystemSetting::get();
        $settings->auth_hero_image_path = self::CUSTOM_PUBLIC_PATH;
        $settings->save();

        return ['ok' => true];
    }

    public static function removeCustom(): void
    {
        if (! static::hasColumn()) {
            return;
        }

        $customPath = public_path(self::CUSTOM_PUBLIC_PATH);
        if (is_file($customPath)) {
            @unlink($customPath);
        }

        $settings = SystemSetting::get();
        if ($settings->auth_hero_image_path === self::CUSTOM_PUBLIC_PATH) {
            $settings->auth_hero_image_path = null;
            $settings->save();
        }
    }

    public static function publicFileExists(string $relativePath): bool
    {
        return is_file(public_path(ltrim($relativePath, '/')));
    }

    private static function cacheVersion(string $path): int
    {
        $full = public_path(ltrim($path, '/'));
        if (is_file($full)) {
            return (int) filemtime($full);
        }

        return time();
    }

    private static function compressToPublicPath(UploadedFile $file, string $relativePath): ?string
    {
        $contents = @file_get_contents($file->getRealPath() ?: '');
        if ($contents === false || $contents === '') {
            return null;
        }

        $source = @imagecreatefromstring($contents);
        if ($source === false) {
            return null;
        }

        $width = imagesx($source);
        $height = imagesy($source);
        if ($width < 1 || $height < 1) {
            imagedestroy($source);

            return null;
        }

        $targetW = $width;
        $targetH = $height;
        if ($width > self::MAX_WIDTH) {
            $targetW = self::MAX_WIDTH;
            $targetH = (int) max(1, round($height * (self::MAX_WIDTH / $width)));
        }

        $canvas = $targetW !== $width
            ? imagescale($source, $targetW, $targetH)
            : $source;

        if ($canvas === false) {
            imagedestroy($source);

            return null;
        }

        if ($canvas !== $source) {
            imagedestroy($source);
        }

        $absolute = public_path($relativePath);
        File::ensureDirectoryExists(dirname($absolute));
        $quality = 88;
        $saved = false;

        while ($quality >= 52) {
            $saved = imagejpeg($canvas, $absolute, $quality);
            if (! $saved) {
                break;
            }
            if (is_file($absolute) && filesize($absolute) <= self::MAX_BYTES) {
                break;
            }
            $quality -= 6;
        }

        imagedestroy($canvas);

        if (! $saved || ! is_file($absolute)) {
            return null;
        }

        return $absolute;
    }
}
