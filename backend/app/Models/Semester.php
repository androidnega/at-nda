<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Semester extends Model
{
    protected $fillable = ['year_label', 'term', 'label'];

    protected $casts = [
        'term' => 'integer',
    ];

    public function schoolClasses(): HasMany
    {
        return $this->hasMany(SchoolClass::class, 'semester_id');
    }

    public function getDisplayLabelAttribute(): string
    {
        if (filled($this->label)) {
            return (string) $this->label;
        }

        return $this->year_label.' · Semester '.$this->term;
    }
}
