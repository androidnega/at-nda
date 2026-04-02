<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceDeletion extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'attendance_id',
        'student_id',
        'deleted_at',
    ];

    protected $casts = [
        'deleted_at' => 'datetime',
    ];
}
