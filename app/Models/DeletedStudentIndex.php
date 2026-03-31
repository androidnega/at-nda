<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Append-only log when a student row is deleted (for mobile cache invalidation).
 */
class DeletedStudentIndex extends Model
{
    public $timestamps = false;

    protected $fillable = ['index_number', 'deleted_at'];

    protected $casts = [
        'deleted_at' => 'datetime',
    ];
}
