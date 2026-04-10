<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoggedWhatsappMessage extends Model
{
    protected $fillable = [
        'student_id',
        'index_number',
        'device_id',
        'client_record_id',
        'source_app',
        'sender_hint',
        'body_preview',
        'occurred_at',
        'consent_version',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
