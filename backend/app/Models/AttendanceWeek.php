<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceWeek extends Model
{
    protected $fillable = [
        'course_id',
        'week_number',
        'week_date',
        'cancelled_at',
        'cancelled_by',
        'cancellation_note',
    ];

    protected $casts = [
        'week_date' => 'date',
        'cancelled_at' => 'datetime',
    ];

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(AttendanceSession::class, 'attendance_week_id');
    }
}
