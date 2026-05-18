<?php

namespace App\Models;

use App\Support\UniversityLogoStorage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        return UniversityLogoStorage::exists($this);
    }

    public function logoUrl(): ?string
    {
        return UniversityLogoStorage::publicUrl($this);
    }

    /** Inline image for admin lists (no /storage URL). */
    public function logoPreviewSrc(): ?string
    {
        return UniversityLogoStorage::previewDataUri($this);
    }
}
