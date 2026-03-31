<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceWeek extends Model
{
    protected $fillable = ['course_id', 'week_number', 'week_date'];

    protected $casts = [
        'week_date' => 'date',
    ];

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
