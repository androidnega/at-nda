<?php

namespace App\Models;

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

    public function logoUrl(): ?string
    {
        if (! $this->logo_path) {
            return null;
        }

        return route('media.universities.logo', ['university' => $this->id]) . '?v=' . $this->updated_at?->timestamp;
    }
}
