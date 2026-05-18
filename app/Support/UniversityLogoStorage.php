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
        $path = trim((string) ($university->logo_path ?? ''));

        return $path !== '' && Storage::disk('public')->exists($path);
    }

    public static function publicUrl(University $university): ?string
    {
        if (! self::exists($university)) {
            return null;
        }

        $path = $university->logo_path;
        $version = $university->updated_at?->timestamp ?? time();

        if (file_exists(public_path('storage/'.$path))) {
            return asset('storage/'.$path).'?v='.$version;
        }

        return route('media.universities.logo', ['university' => $university->id]).'?v='.$version;
    }
}
