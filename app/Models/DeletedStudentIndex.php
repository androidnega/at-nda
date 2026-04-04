<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only log when a student row is deleted (for mobile cache invalidation).
 */
class DeletedStudentIndex extends Model
{
    public $timestamps = false;

    protected $table = 'deleted_student_indexes';

    protected $fillable = ['index_number', 'deleted_at'];

    protected $casts = [
        'deleted_at' => 'datetime',
    ];

    public static function tableReady(): bool
    {
        return Schema::hasTable((new static)->getTable());
    }

    public static function latestForIndex(string $normalizedIndex): ?self
    {
        if (! static::tableReady()) {
            return null;
        }

        return static::query()
            ->where('index_number', $normalizedIndex)
            ->orderByDesc('deleted_at')
            ->first();
    }
}
