<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentDeviceToken extends Model
{
    protected $fillable = [
        'student_id',
        'firebase_token',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
