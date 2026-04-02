<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseRep extends Model
{
    protected $fillable = ['student_id', 'course_id', 'role'];

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

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
