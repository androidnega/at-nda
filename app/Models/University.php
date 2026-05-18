<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class University extends Model
{
    protected $fillable = ['name', 'location', 'logo_path'];

    public function faculties(): HasMany
    {
        return $this->hasMany(Faculty::class);
    }

    public function classes(): HasMany
    {
        return $this->hasMany(SchoolClass::class, 'university_id');
    }

    public function hasStoredLogo(): bool
    {
        if (! Schema::hasColumn('universities', 'logo_path')) {
            return false;
        }

        $path = trim((string) ($this->logo_path ?? ''));

        return $path !== '' && Storage::disk('public')->exists($path);
    }

    public function logoUrl(): ?string
    {
        if (! $this->hasStoredLogo()) {
            return null;
        }

        return route('media.universities.logo', ['university' => $this->id])
            . '?v=' . ($this->updated_at?->timestamp ?? time());
    }
}
