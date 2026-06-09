<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Browser/device telemetry captured at the moment a student marks an
 * online attendance. Strictly informational — the row is created AFTER
 * attendance is persisted, and is consulted only by
 * {@see \App\Services\AttendanceRiskService} to derive a non-blocking
 * risk score.
 *
 * fingerprint_hash is the FingerprintJS visitor id (32-char hex), so it
 * is stable across reloads of the same browser profile but changes across
 * incognito sessions / different browsers — intentional, per the spec.
 */
class AttendanceDeviceLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'student_id',
        'session_id',
        'fingerprint_hash',
        'ip_address',
        'user_agent',
        'platform',
        'browser',
        'operating_system',
        'screen_resolution',
        'timezone',
        'language',
        'device_memory',
        'cpu_cores',
        'touch_support',
        'created_at',
    ];

    protected $casts = [
        'created_at'    => 'datetime',
        'device_memory' => 'integer',
        'cpu_cores'     => 'integer',
        'touch_support' => 'boolean',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(AttendanceSession::class, 'session_id');
    }
}
