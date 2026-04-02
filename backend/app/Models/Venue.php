<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Venue extends Model
{
    protected $fillable = ['name', 'code', 'building', 'capacity'];

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class, 'venue_id');
    }
}
