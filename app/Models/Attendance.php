<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    protected static function booted(): void
    {
        static::deleting(function (Attendance $attendance) {
            AttendanceDeletion::create([
                'attendance_id' => $attendance->id,
                'student_id' => $attendance->student_id,
                'deleted_at' => now(),
            ]);
        });
    }

    protected $fillable = [
        'student_id',
        'course_id',
        'attendance_session_id',
        'attendance_week_id',
        'attendance_time',
        'status',
        'check_in_time',
        'check_out_time',
        'time_spent_seconds',
        'synced',
        'lat',
        'lng',
        'qr_code',
        'device_ip',
        'device_id',
    ];

    public function attendanceWeek(): BelongsTo
    {
        return $this->belongsTo(AttendanceWeek::class);
    }

    protected $casts = [
        'attendance_time' => 'datetime',
        'check_in_time' => 'datetime',
        'check_out_time' => 'datetime',
        'synced' => 'boolean',
        'lat' => 'decimal:7',
        'lng' => 'decimal:7',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function attendanceSession(): BelongsTo
    {
        return $this->belongsTo(AttendanceSession::class);
    }
}
