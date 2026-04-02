<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Faculty extends Model
{
    public function university(): BelongsTo
    {
        return $this->belongsTo(University::class);
    }

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }
}
