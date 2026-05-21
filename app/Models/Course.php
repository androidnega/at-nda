<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Student;
use App\Support\SchemaFeatures;

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

    protected static function booted(): void
    {
        static::saving(function (Course $course): void {
            if ($course->lecturer_id) {
                $lecturer = Lecturer::query()->find($course->lecturer_id);
                if ($lecturer) {
                    $name = trim((string) $lecturer->name);
                    if ($name !== '') {
                        $course->lecturer_name = $name;
                    }
                }
            }
        });
    }

    /**
     * Lecturer name for PDFs, exports, and timetables (course link, then class assignment).
     *
     * The live Lecturer.name wins over the cached `lecturer_name` column so
     * that renaming a lecturer immediately propagates to PDFs and exports —
     * the cache is only consulted when no Lecturer relation can be resolved.
     */
    public function resolvedLecturerName(): string
    {
        $lecturer = $this->relationLoaded('lecturer') ? $this->lecturer : null;
        if (! $lecturer && $this->lecturer_id) {
            $lecturer = $this->lecturer()->first();
        }
        if ($lecturer) {
            $name = trim((string) $lecturer->name);
            if ($name !== '') {
                return $name;
            }
        }

        $fromColumn = trim((string) ($this->lecturer_name ?? ''));
        if ($fromColumn !== '') {
            return $fromColumn;
        }

        $byCourseLink = Lecturer::query()
            ->whereHas('courses', fn (Builder $q) => $q->where('courses.id', $this->id))
            ->orderBy('name')
            ->first();
        if ($byCourseLink) {
            $name = trim((string) $byCourseLink->name);
            if ($name !== '') {
                return $name;
            }
        }

        $classIds = $this->assignedClassIds();
        if ($classIds !== [] && SchemaFeatures::hasClassLecturerPivot()) {
            $byClass = Lecturer::query()
                ->whereHas('schoolClasses', fn (Builder $q) => $q->whereIn('classes.id', $classIds))
                ->orderBy('name')
                ->first();
            if ($byClass) {
                $name = trim((string) $byClass->name);
                if ($name !== '') {
                    return $name;
                }
            }
        }

        return '';
    }

    public function resolvedLecturerDisplay(): string
    {
        $name = $this->resolvedLecturerName();

        return $name !== '' ? $name : 'Not assigned';
    }

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
        if (SchemaFeatures::hasCourseClassPivot()) {
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

    public function assignedClassesLabel(): string
    {
        if ($this->relationLoaded('schoolClasses') && $this->schoolClasses->isNotEmpty()) {
            return $this->schoolClasses->pluck('name')->unique()->join(', ');
        }

        return $this->schoolClass?->name ?? '';
    }

    public function syncAssignedClasses(array $classIds): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $classIds))));
        if ($ids === []) {
            return;
        }
        if (SchemaFeatures::hasCourseClassPivot()) {
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
        return $query->forManagedClasses([$classId]);
    }

    /**
     * Courses linked to any of the given classes (legacy class_id or course_class pivot).
     *
     * @param  Builder<Course>  $query
     * @param  iterable<int, int|string>  $classIds
     */
    public function scopeForManagedClasses(Builder $query, iterable $classIds): Builder
    {
        $ids = collect($classIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $q) use ($ids): void {
            $q->whereIn('courses.class_id', $ids);
            if (SchemaFeatures::hasCourseClassPivot()) {
                $q->orWhereHas('schoolClasses', fn (Builder $sq) => $sq->whereIn('classes.id', $ids));
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

    public function classTimetables(): HasMany
    {
        return $this->hasMany(ClassTimetable::class, 'course_id');
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

    /**
     * True when this course has a runnable schedule for the given class. Prefers
     * the per-class `class_timetables` entry; falls back to the legacy columns
     * so historical data still opens attendance.
     */
    public function hasScheduleForClass(?int $classId): bool
    {
        return \App\Support\ClassTimetableAccess::resolveScheduleSnapshot($this, $classId) !== null;
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
    public function computeSessionExpiresAt(int $durationMinutes, ?int $classId = null): \Carbon\Carbon
    {
        $candidate = now()->addMinutes(max(1, $durationMinutes));
        $snapshot = \App\Support\ClassTimetableAccess::resolveScheduleSnapshot($this, $classId);
        if ($snapshot === null) {
            return $candidate;
        }
        $todayName = now()->format('l');
        if (strcasecmp(trim((string) $snapshot['day_of_week']), $todayName) !== 0) {
            return $candidate;
        }
        try {
            $slotEnd = now()->copy()->setTimeFromTimeString(
                \Carbon\Carbon::parse($snapshot['end_time'])->format('H:i:s')
            );
        } catch (\Throwable $e) {
            return $candidate;
        }
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
     * Highest week label used for this course across all classes (admin sequencing view).
     */
    public function maxAttendanceWeekNumber(): int
    {
        return (int) ($this->attendanceWeeks()->max('week_number') ?? 0);
    }

    /**
     * Highest week label this class has reached for this course. Each class runs
     * its own week counter so two classes sharing the same course (e.g. Software
     * Engineering) do not inherit each other's numbering.
     */
    public function maxAttendanceWeekNumberForClass(int $classId): int
    {
        if ($classId <= 0) {
            return 0;
        }

        $query = $this->attendanceWeeks();
        if (\App\Support\SchemaFeatures::hasAttendanceWeeksClassId()) {
            $query->where('class_id', $classId);
        }

        return (int) ($query->max('week_number') ?? 0);
    }

    /**
     * Reuse today's week row if it exists; otherwise create one using
     * `max(week_number) + 1` *scoped to the given class* (or all classes when
     * `$classId` is null, which preserves legacy admin-only flows).
     *
     * @param  int|null  $overrideWeekNumber  When provided (1–500) it overrides
     *                                        both the seed and the auto-increment
     *                                        so the rep can pin the session to
     *                                        a specific week label (e.g. resuming
     *                                        after a break).
     */
    public function createOrGetAttendanceWeekForToday(?int $classId = null, ?int $overrideWeekNumber = null): AttendanceWeek
    {
        $today = now()->toDateString();
        $classAware = $classId !== null && $classId > 0 && \App\Support\SchemaFeatures::hasAttendanceWeeksClassId();

        $existingQuery = $this->attendanceWeeks()->where('week_date', $today);
        if ($classAware) {
            $existingQuery->where('class_id', $classId);
        }
        $existing = $existingQuery->first();
        if ($existing) {
            if ($overrideWeekNumber !== null && (int) $existing->week_number !== $overrideWeekNumber) {
                $existing->update(['week_number' => $overrideWeekNumber]);
            }
            return $existing;
        }

        if ($overrideWeekNumber !== null) {
            $num = $overrideWeekNumber;
        } else {
            $maxQuery = $this->attendanceWeeks();
            if ($classAware) {
                $maxQuery->where('class_id', $classId);
            }
            $maxWeek = (int) ($maxQuery->max('week_number') ?? 0);

            // The course-wide next_week_number seed only applies when the rep has
            // never attended for this (course, class) pair, so a brand new cohort
            // always begins at week 1 instead of inheriting another class's number.
            if ($maxWeek === 0 && $this->next_week_number !== null && ! $classAware) {
                $num = (int) $this->next_week_number;
                $this->update(['next_week_number' => $num + 1]);
            } elseif ($maxWeek === 0) {
                $num = 1;
            } else {
                $num = $maxWeek + 1;
            }
        }

        $attributes = [
            'course_id' => $this->id,
            'week_number' => $num,
            'week_date' => $today,
        ];
        if ($classAware) {
            $attributes['class_id'] = $classId;
        }

        return AttendanceWeek::create($attributes);
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
