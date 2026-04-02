<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassRep extends Model
{
    protected $fillable = ['student_id', 'class_id', 'role'];

    public const ROLE_REP = 'rep';
    public const ROLE_ASSIST = 'assist';

    public function isMainRep(): bool
    {
        return $this->role === self::ROLE_REP;
    }

    public function isAssistRep(): bool
    {
        return $this->role === self::ROLE_ASSIST;
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }
}
