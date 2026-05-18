<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Student;

class Course extends Model
{
    protected $fillable = [
        'course_name',
        'course_code',
        'class_id',
        'lecturer_id',
        'venue_id',
        'day_of_week',
        'start_time',
        'end_time',
        'credit_hours',
        'venue',
        'lecturer_name',
        'location_lat',
        'location_lng',
        'attendance_range_m',
        'attendance_window_minutes',
        'next_week_number',
    ];

    protected $casts = [
        'location_lat' => 'decimal:7',
        'location_lng' => 'decimal:7',
        'credit_hours' => 'integer',
    ];

    public function schoolClass(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function schoolClasses(): BelongsToMany
    {
        return $this->belongsToMany(SchoolClass::class, 'course_class', 'course_id', 'class_id')
            ->withTimestamps();
    }

    /**
     * @return list<int>
     */
    public function assignedClassIds(): array
    {
        $ids = [];
        if (\Illuminate\Support\Facades\Schema::hasTable('course_class')) {
            $ids = $this->schoolClasses()->pluck('classes.id')->map(fn ($id) => (int) $id)->all();
        }
        if ($this->class_id && ! in_array((int) $this->class_id, $ids, true)) {
            $ids[] = (int) $this->class_id;
        }

        return array_values(array_unique($ids));
    }

    public function isAssignedToClass(int $classId): bool
    {
        return in_array($classId, $this->assignedClassIds(), true);
    }

    public function overlapsClassIds(iterable $classIds): bool
    {
        $mine = $this->assignedClassIds();
        foreach ($classIds as $id) {
            if (in_array((int) $id, $mine, true)) {
                return true;
            }
        }

        return false;
    }

    public function studentMayAttend(Student $student): bool
    {
        return $student->class_id && $this->isAssignedToClass((int) $student->class_id);
    }

    public function syncAssignedClasses(array $classIds): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $classIds))));
        if ($ids === []) {
            return;
        }
        if (\Illuminate\Support\Facades\Schema::hasTable('course_class')) {
            $this->schoolClasses()->sync($ids);
        }
        $this->class_id = $ids[0];
        $this->save();
    }

    /**
     * @param  Builder<Course>  $query
     */
    public function scopeForClass(Builder $query, int $classId): Builder
    {
        return $query->where(function (Builder $q) use ($classId): void {
            $q->where('class_id', $classId);
            if (\Illuminate\Support\Facades\Schema::hasTable('course_class')) {
                $q->orWhereHas('schoolClasses', fn (Builder $sq) => $sq->where('classes.id', $classId));
            }
        });
    }

    public function studentsQuery(): Builder
    {
        $classIds = $this->assignedClassIds();
        $query = Student::query();
        if ($classIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('class_id', $classIds);
    }

    public function lecturer(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Lecturer::class);
    }

    public function venueRelation(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Venue::class, 'venue_id');
    }

    public function attendanceWeeks(): HasMany
    {
        return $this->hasMany(AttendanceWeek::class, 'course_id');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function attendanceSessions(): HasMany
    {
        return $this->hasMany(AttendanceSession::class);
    }

    public function activeSession(): ?AttendanceSession
    {
        return $this->activeSessions()->first();
    }

    /**
     * All attendance sessions for this course that are currently active (time window + is_active).
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, AttendanceSession>
     */
    public function activeSessions(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->attendanceSessions()
            ->with(['attendanceWeek', 'course.lecturer', 'course.venueRelation', 'lecturer', 'venue'])
            ->activeWithinTimeWindow()
            ->latest('id')
            ->get();
    }

    public function hasSchedule(): bool
    {
        return !empty($this->day_of_week) && !empty($this->start_time) && !empty($this->end_time);
    }

    public function isWithinClassTime(?\Carbon\Carbon $date = null): bool
    {
        if (!$this->hasSchedule()) {
            return false;
        }
        $now = $date ?? now();
        if ($this->day_of_week !== $now->format('l')) {
            return false;
        }
        $start = \Carbon\Carbon::parse($this->start_time)->format('H:i:s');
        $end = \Carbon\Carbon::parse($this->end_time)->format('H:i:s');
        $current = $now->format('H:i:s');
        return $current >= $start && $current <= $end;
    }

    /**
     * Reps may open attendance anytime. On the course's scheduled weekday, expiry is capped
     * at the timetable end time so the live session does not run past the official slot.
     * On other days (or after that day's slot has ended), the full requested duration applies.
     */
    public function computeSessionExpiresAt(int $durationMinutes): \Carbon\Carbon
    {
        $candidate = now()->addMinutes(max(1, $durationMinutes));
        if (!$this->hasSchedule()) {
            return $candidate;
        }
        $todayName = now()->format('l');
        if (strcasecmp(trim((string) $this->day_of_week), $todayName) !== 0) {
            return $candidate;
        }
        $slotEnd = now()->copy()->setTimeFromTimeString(
            \Carbon\Carbon::parse($this->end_time)->format('H:i:s')
        );
        if ($slotEnd->isPast()) {
            return $candidate;
        }

        return $candidate->lessThanOrEqualTo($slotEnd) ? $candidate : $slotEnd;
    }

    public function getScheduleLabel(): string
    {
        if (!$this->hasSchedule()) {
            return 'No schedule set';
        }
        $start = \Carbon\Carbon::parse($this->start_time)->format('H:i');
        $end = \Carbon\Carbon::parse($this->end_time)->format('H:i');
        $venueDisplay = $this->venueRelation?->name ?? $this->venue;
        return $this->day_of_week . ' ' . $start . '–' . $end . ($venueDisplay ? ' · ' . $venueDisplay : '');
    }

    /**
     * Highest week label used for this course (for admin / sequencing).
     */
    public function maxAttendanceWeekNumber(): int
    {
        return (int) ($this->attendanceWeeks()->max('week_number') ?? 0);
    }

    /**
     * Reuse today's week row if it exists; otherwise create one using max(week)+1 or admin "next week" seed.
     */
    public function createOrGetAttendanceWeekForToday(): AttendanceWeek
    {
        $today = now()->toDateString();
        $existing = $this->attendanceWeeks()->where('week_date', $today)->first();
        if ($existing) {
            return $existing;
        }

        $maxWeek = (int) ($this->attendanceWeeks()->max('week_number') ?? 0);
        if ($this->next_week_number !== null) {
            $num = max((int) $this->next_week_number, $maxWeek + 1);
            $this->update(['next_week_number' => $num + 1]);
        } else {
            $num = $maxWeek + 1;
        }

        return AttendanceWeek::create([
            'course_id' => $this->id,
            'week_number' => $num,
            'week_date' => $today,
        ]);
    }

    /**
     * Whether the course has a saved default anchor for location / hybrid sessions.
     */
    public function hasDefaultSessionLocation(): bool
    {
        return $this->location_lat !== null
            && $this->location_lng !== null
            && $this->attendance_range_m !== null
            && (int) $this->attendance_range_m > 0;
    }
}
