<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Submission that arrived AFTER the session closed or past the
 * attendance window. The class rep / lecturer reviews it from the
 * dashboard and either approves (writes an Attendance row) or denies.
 */
class AttendanceLateUnrecorded extends Model
{
    protected $table = 'attendance_late_unrecorded';

    protected $fillable = [
        'attendance_uuid',
        'student_id',
        'attendance_session_id',
        'course_id',
        'attendance_week_id',
        'reason',
        'payload',
        'captured_at',
        'sync_attempted_at',
        'decision',
        'decided_at',
        'decided_by_user_id',
        'decision_notes',
        'resulting_attendance_id',
    ];

    protected $casts = [
        'payload' => 'array',
        'captured_at' => 'datetime',
        'sync_attempted_at' => 'datetime',
        'decided_at' => 'datetime',
    ];

    public const DECISION_PENDING = 'pending';
    public const DECISION_APPROVED = 'approved';
    public const DECISION_DENIED = 'denied';

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(AttendanceSession::class, 'attendance_session_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
