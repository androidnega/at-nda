<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-session attendance roll-up cache.
 *
 * Populated by App\Services\AttendanceSessionSummaryService::rebuild()
 * when an attendance session closes (and lazily on read for missing
 * rows). The attendance-map UI reads this table directly instead of
 * recomputing aggregates from `attendances` on every render — that
 * is the entire point of the table per the owner spec.
 *
 * Each row is ~80 bytes; 100 000 sessions ≈ 8 MB total. Safe on
 * shared cPanel hosting.
 */
class AttendanceSessionSummary extends Model
{
    protected $table = 'attendance_session_summaries';

    protected $fillable = [
        'attendance_session_id',
        'attendance_count',
        'present_count',
        'average_distance',
        'minimum_distance',
        'maximum_distance',
        'inside_count',
        'edge_count',
        'outside_count',
        'closest_student_id',
        'farthest_student_id',
        'closed_at',
        'refreshed_at',
    ];

    protected $casts = [
        'attendance_count' => 'integer',
        'present_count' => 'integer',
        'average_distance' => 'integer',
        'minimum_distance' => 'integer',
        'maximum_distance' => 'integer',
        'inside_count' => 'integer',
        'edge_count' => 'integer',
        'outside_count' => 'integer',
        'closest_student_id' => 'integer',
        'farthest_student_id' => 'integer',
        'closed_at' => 'datetime',
        'refreshed_at' => 'datetime',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(AttendanceSession::class, 'attendance_session_id');
    }

    public function closestStudent(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'closest_student_id');
    }

    public function farthestStudent(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'farthest_student_id');
    }
}
