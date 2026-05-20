<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-class timetable slot. Each class owns its own row and edits do not affect
 * any other class that shares the same course.
 */
class ClassTimetable extends Model
{
    protected $fillable = [
        'class_id',
        'course_id',
        'day_of_week',
        'start_time',
        'end_time',
        'lecturer_id',
        'venue_id',
        'venue',
        'created_by_student_id',
    ];

    public const DAYS = [
        'Monday',
        'Tuesday',
        'Wednesday',
        'Thursday',
        'Friday',
        'Saturday',
        'Sunday',
    ];

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function lecturer(): BelongsTo
    {
        return $this->belongsTo(Lecturer::class);
    }

    public function venueRelation(): BelongsTo
    {
        return $this->belongsTo(Venue::class, 'venue_id');
    }

    /**
     * @param  Builder<ClassTimetable>  $query
     */
    public function scopeOrderedForWeek(Builder $query): Builder
    {
        return $query
            ->orderByRaw("CASE day_of_week WHEN 'Monday' THEN 1 WHEN 'Tuesday' THEN 2 WHEN 'Wednesday' THEN 3 WHEN 'Thursday' THEN 4 WHEN 'Friday' THEN 5 WHEN 'Saturday' THEN 6 WHEN 'Sunday' THEN 7 ELSE 99 END")
            ->orderBy('start_time');
    }

    public function dayKey(): string
    {
        return ucfirst(strtolower(trim((string) $this->day_of_week)));
    }

    public function resolvedLecturerName(): string
    {
        if ($this->relationLoaded('lecturer') && $this->lecturer) {
            $name = trim((string) $this->lecturer->name);
            if ($name !== '') {
                return $name;
            }
        }

        if ($this->lecturer_id) {
            $lecturer = Lecturer::query()->find($this->lecturer_id);
            if ($lecturer) {
                return trim((string) $lecturer->name);
            }
        }

        return '';
    }

    public function resolvedVenueName(): string
    {
        if ($this->relationLoaded('venueRelation') && $this->venueRelation) {
            $name = trim((string) $this->venueRelation->name);
            if ($name !== '') {
                return $name;
            }
        }

        return trim((string) ($this->venue ?? ''));
    }

    public function isWithinClassTime(?\Carbon\Carbon $date = null): bool
    {
        $now = $date ?? now();
        if (strcasecmp(trim((string) $this->day_of_week), $now->format('l')) !== 0) {
            return false;
        }
        try {
            $start = \Carbon\Carbon::parse($this->start_time)->format('H:i:s');
            $end = \Carbon\Carbon::parse($this->end_time)->format('H:i:s');
        } catch (\Throwable $e) {
            return false;
        }
        $current = $now->format('H:i:s');

        return $current >= $start && $current <= $end;
    }

    public function computeSessionExpiresAt(int $durationMinutes): \Carbon\Carbon
    {
        $candidate = now()->addMinutes(max(1, $durationMinutes));
        if (strcasecmp(trim((string) $this->day_of_week), now()->format('l')) !== 0) {
            return $candidate;
        }
        try {
            $slotEnd = now()->copy()->setTimeFromTimeString(
                \Carbon\Carbon::parse($this->end_time)->format('H:i:s')
            );
        } catch (\Throwable $e) {
            return $candidate;
        }
        if ($slotEnd->isPast()) {
            return $candidate;
        }

        return $candidate->lessThanOrEqualTo($slotEnd) ? $candidate : $slotEnd;
    }
}
