<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceWeek extends Model
{
    protected $fillable = [
        'course_id',
        'class_id',
        'week_number',
        'week_date',
        'cancelled_at',
        'cancelled_by',
        'cancellation_note',
        'is_online',
        'online_platform',
        'online_note',
    ];

    protected $casts = [
        'week_date' => 'date',
        'cancelled_at' => 'datetime',
        'is_online' => 'boolean',
    ];

    protected static function booted(): void
    {
        // Strip optional online-class columns on deploys whose database has
        // not yet picked up the 2026_06_08_080000 migration so create() /
        // update() never errors with "unknown column 'is_online'".
        static::saving(function (self $week): void {
            if (! \App\Support\SchemaFeatures::hasAttendanceWeeksOnlineFlag()) {
                foreach (['is_online', 'online_platform', 'online_note'] as $col) {
                    if (array_key_exists($col, $week->getAttributes())) {
                        unset($week->attributes[$col]);
                    }
                }
            }
        });
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    public function isOnline(): bool
    {
        return (bool) ($this->is_online ?? false);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(AttendanceSession::class, 'attendance_week_id');
    }
}
