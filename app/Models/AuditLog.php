<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    protected $fillable = [
        'actor_id',
        'actor_role',
        'actor_name',
        'class_id',
        'course_id',
        'attendance_session_id',
        'action',
        'subject_type',
        'subject_id',
        'ip',
        'user_agent',
        'device_fingerprint',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function attendanceSession(): BelongsTo
    {
        return $this->belongsTo(AttendanceSession::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }
}
