<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentActiveSession extends Model
{
    protected $fillable = [
        'student_id',
        'session_id',
        'device_fingerprint',
        'ip',
        'user_agent',
        'is_active',
        'last_active_at',
        'revoked_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_active_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
