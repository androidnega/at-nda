<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SchoolClass extends Model
{
    use HasFactory;

    protected $table = 'classes';

    protected $fillable = ['name', 'code', 'university_id', 'faculty_id', 'department_id', 'level', 'qualification', 'semester_id', 'semester_weeks', 'semester_start_date', 'semester_end_date', 'current_week_override', 'logo_path'];

    protected $casts = [
        'level' => 'integer',
        'semester_weeks' => 'integer',
        'semester_start_date' => 'date',
        'semester_end_date' => 'date',
        'current_week_override' => 'integer',
    ];

    /** Sensible defaults / hard bounds for the semester length. */
    public const DEFAULT_SEMESTER_WEEKS = 12;
    public const MIN_SEMESTER_WEEKS = 1;
    public const MAX_SEMESTER_WEEKS = 30;

    /** Levels used in forms and progression (100 → 200 → …). */
    public const LEVELS = [100, 200, 300, 400];

    /**
     * Programme qualification a class belongs to. Courses are gated by this
     * so a "DEGREE" course never appears in the timetable of an HND class.
     * Lecturers are shared across qualifications — only courses split.
     */
    public const QUALIFICATIONS = ['hnd', 'diploma', 'degree'];

    /** Display labels for each qualification. */
    public const QUALIFICATION_LABELS = [
        'hnd' => 'HND',
        'diploma' => 'Diploma',
        'degree' => 'Degree',
    ];

    /** Returns the canonical qualification, falling back to "degree". */
    public function resolvedQualification(): string
    {
        $value = strtolower(trim((string) ($this->qualification ?? '')));
        if ($value === '' || ! in_array($value, self::QUALIFICATIONS, true)) {
            return 'degree';
        }

        return $value;
    }

    public function qualificationLabel(): string
    {
        $key = $this->resolvedQualification();

        return self::QUALIFICATION_LABELS[$key] ?? 'Degree';
    }

    public function university(): BelongsTo
    {
        return $this->belongsTo(University::class);
    }

    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class);
    }

    public function lecturers(): BelongsToMany
    {
        return $this->belongsToMany(Lecturer::class, 'class_lecturer', 'class_id', 'lecturer_id')
            ->withTimestamps();
    }

    public function sharedCourses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'course_class', 'class_id', 'course_id')
            ->withTimestamps();
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class, 'class_id');
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class, 'class_id');
    }

    public function classTimetables(): HasMany
    {
        return $this->hasMany(ClassTimetable::class, 'class_id');
    }

    public function logoUrl(): ?string
    {
        $university = $this->resolveUniversity();
        if ($university) {
            $schoolLogo = $university->logoUrl();
            if ($schoolLogo) {
                return $schoolLogo;
            }
        }

        $path = trim((string) ($this->logo_path ?? ''));
        if ($path === '' || ! \Illuminate\Support\Facades\Storage::disk('public')->exists($path)) {
            return null;
        }

        return route('media.classes.logo', ['schoolClass' => $this->id]) . '?v=' . ($this->updated_at?->timestamp ?? time());
    }

    public function resolveUniversity(): ?University
    {
        if ($this->relationLoaded('university') && $this->university) {
            return $this->university;
        }
        if ($this->university_id) {
            return $this->university ?? University::query()->find($this->university_id);
        }

        return $this->faculty?->university;
    }

    public function getDisplayNameAttribute(): string
    {
        $parts = array_filter([
            $this->name,
            $this->level ? 'Level '.$this->level : null,
            $this->semester?->display_label,
        ]);
        return implode(' · ', $parts);
    }

    /** True when semester or level is missing / invalid (legacy rows). */
    public function needsAcademicMetadataReview(): bool
    {
        if ($this->semester_id === null) {
            return true;
        }
        $level = $this->level;

        return $level === null || ! in_array((int) $level, self::LEVELS, true);
    }

    /**
     * Number of weeks in this class's semester, used as the
     * denominator on the student dashboard course cards so every
     * course reads against the same total (e.g. "3/12 wks" across
     * the board instead of mixed "1/1 wks" and "1/4 wks"). Falls
     * back to the project default if unset or out of range.
     */
    public function resolvedSemesterWeeks(): int
    {
        $value = (int) ($this->semester_weeks ?? 0);
        if ($value < self::MIN_SEMESTER_WEEKS || $value > self::MAX_SEMESTER_WEEKS) {
            return self::DEFAULT_SEMESTER_WEEKS;
        }

        return $value;
    }

    /**
     * Calendar-based "what teaching week are we in?" resolution.
     * Order of precedence:
     *
     *   1. Admin override (current_week_override) — short-circuits
     *      every other source of truth. Useful when a public
     *      holiday week shifts the schedule and the date math no
     *      longer matches reality.
     *   2. Calendar math from semester_start_date — floor((today
     *      - start) / 7 days) + 1, clamped to [0, semester_weeks].
     *      Week numbering follows the academic convention: the
     *      start date itself is "week 1", not "week 0".
     *   3. null when neither is set. Callers should fall back to
     *      activity-derived signals (max attendance_weeks.week_number).
     *
     * Returning null (not 0) for "nothing configured" lets the
     * caller distinguish "admin set week 0" from "admin set
     * nothing yet".
     */
    public function computeCurrentSemesterWeek(?\DateTimeInterface $now = null): ?int
    {
        $weeks = $this->resolvedSemesterWeeks();

        $override = $this->current_week_override;
        if ($override !== null) {
            $override = (int) $override;
            if ($override < 0) {
                $override = 0;
            }
            return min($override, $weeks);
        }

        $start = $this->semester_start_date;
        if ($start === null) {
            return null;
        }

        $today = $now instanceof \Carbon\CarbonInterface
            ? $now->copy()->startOfDay()
            : \Illuminate\Support\Carbon::instance($now ?? \Illuminate\Support\Carbon::now())->startOfDay();
        $startDay = \Illuminate\Support\Carbon::instance($start)->startOfDay();

        if ($today->lt($startDay)) {
            return 0;
        }

        // diffInDays is non-negative here because we already
        // returned 0 for "before the semester starts".
        $week = (int) floor($startDay->diffInDays($today) / 7) + 1;
        if ($week < 0) {
            $week = 0;
        }

        return min($week, $weeks);
    }

    /** Next level on the ladder, or null at 400; if level invalid, suggests 100. */
    public function suggestedNextLevel(): ?int
    {
        $level = (int) ($this->level ?? 0);
        $idx = array_search($level, self::LEVELS, true);
        if ($idx === false) {
            return self::LEVELS[0];
        }
        if ($idx >= count(self::LEVELS) - 1) {
            return null;
        }

        return self::LEVELS[$idx + 1];
    }
}
