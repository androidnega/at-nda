<?php

namespace App\Support;

use App\Models\University;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

final class UniversityLogoStorage
{
    public static function ensureColumn(): bool
    {
        if (! Schema::hasTable('universities')) {
            return false;
        }

        if (Schema::hasColumn('universities', 'logo_path')) {
            return true;
        }

        try {
            Schema::table('universities', function (Blueprint $table): void {
                $table->string('logo_path')->nullable();
            });

            return Schema::hasColumn('universities', 'logo_path');
        } catch (\Throwable $e) {
            report($e);

            return false;
        }
    }

    public static function store(University $university, UploadedFile $file): bool
    {
        if (! self::ensureColumn()) {
            return false;
        }

        if ($university->logo_path) {
            Storage::disk('public')->delete($university->logo_path);
        }

        $university->logo_path = $file->store('school-logos', 'public');
        $university->save();

        return self::exists($university);
    }

    public static function remove(University $university): void
    {
        if (! self::ensureColumn() || ! $university->logo_path) {
            return;
        }

        Storage::disk('public')->delete($university->logo_path);
        $university->logo_path = null;
        $university->save();
    }

    public static function exists(University $university): bool
    {
        $path = self::normalizePath($university->logo_path);

        return $path !== '' && Storage::disk('public')->exists($path);
    }

    /**
     * Inline data URI for admin preview (does not depend on /storage symlink or routes).
     */
    public static function previewDataUri(University $university): ?string
    {
        $path = self::normalizePath($university->logo_path);
        if ($path === '' || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        $contents = Storage::disk('public')->get($path);
        if ($contents === '' || $contents === false) {
            return null;
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            default => Storage::disk('public')->mimeType($path) ?: 'image/png',
        };

        return 'data:'.$mime.';base64,'.base64_encode($contents);
    }

    /**
     * Public URL — always via Laravel media route (reliable on shared hosting).
     */
    public static function publicUrl(University $university): ?string
    {
        if (! self::exists($university)) {
            return null;
        }

        $version = $university->updated_at?->timestamp ?? time();

        return url(route('media.universities.logo', ['university' => $university->id], false)).'?v='.$version;
    }

    /**
     * Drop DB path when the file is missing (prevents broken img tags).
     */
    public static function purgeMissingFile(University $university): void
    {
        if (! self::ensureColumn()) {
            return;
        }

        $path = self::normalizePath($university->logo_path);
        if ($path === '') {
            return;
        }

        if (! Storage::disk('public')->exists($path)) {
            $university->forceFill(['logo_path' => null])->save();
        }
    }

    public static function normalizePath(?string $path): string
    {
        $path = trim((string) $path);
        if ($path === '') {
            return '';
        }

        // Legacy bad values: full filesystem paths or leading slashes.
        if (str_contains($path, 'school-logos/')) {
            $path = substr($path, (int) strrpos($path, 'school-logos/'));
        }

        return ltrim($path, '/');
    }
}
