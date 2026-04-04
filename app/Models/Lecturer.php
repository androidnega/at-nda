<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Sanctum\HasApiTokens;

/**
 * Teaching staff directory entry (name + optional class). Mobile API login via email/username + password.
 * Courses link via lecturer_id; venue is set per course.
 */
class Lecturer extends Model
{
    use HasApiTokens;

    protected $fillable = ['name', 'class_id', 'email', 'username', 'password', 'must_change_password'];

    protected $hidden = ['password'];

    protected $casts = [
        'must_change_password' => 'boolean',
    ];

    public function schoolClass(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }
}
