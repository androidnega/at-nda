<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CallLog extends Model
{
    protected $fillable = [
        'student_id',
        'index_number',
        'device_id',
        'client_record_id',
        'direction',
        'call_outcome',
        'duration_seconds',
        'peer_number',
        'occurred_at',
        'ended_at',
        'consent_version',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
