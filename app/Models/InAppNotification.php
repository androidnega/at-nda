<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InAppNotification extends Model
{
    protected $fillable = [
        'student_id',
        'course_id',
        'kind',
        'title',
        'body',
        'starts_at',
        'delivery_key',
        'read_at',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'read_at' => 'datetime',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}

