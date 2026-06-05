<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    protected static function booted(): void
    {
        static::deleting(function (Attendance $attendance) {
            AttendanceDeletion::create([
                'attendance_id' => $attendance->id,
                'student_id' => $attendance->student_id,
                'deleted_at' => now(),
            ]);
        });

        // Strip optional columns before insert/update if the running database
        // hasn't been migrated yet. Keeps older deploys from blowing up with
        // "unknown column 'user_agent'" the moment we deploy this change.
        static::saving(function (Attendance $attendance): void {
            if (! \App\Support\SchemaFeatures::hasAttendancesUserAgent()
                && array_key_exists('user_agent', $attendance->getAttributes())) {
                unset($attendance->attributes['user_agent']);
            }
        });
    }

    /**
     * Best-effort short label for the device used to mark this row. Falls
     * back to the raw user-agent so reps always see *something* useful.
     */
    public function deviceLabel(): string
    {
        $ua = (string) ($this->user_agent ?? '');
        if ($ua === '') {
            return '—';
        }

        // Heuristic device fingerprint – good enough for "Android / iPhone / Mac".
        $patterns = [
            '/iPhone|iPod/' => 'iPhone',
            '/iPad/' => 'iPad',
            '/Android/' => 'Android phone',
            '/Windows Phone/' => 'Windows Phone',
            '/Macintosh|Mac OS X/' => 'Mac',
            '/Windows NT/' => 'Windows PC',
            '/Linux/' => 'Linux',
            '/CrOS/' => 'Chromebook',
        ];
        foreach ($patterns as $regex => $label) {
            if (preg_match($regex, $ua) === 1) {
                return $label;
            }
        }

        return mb_substr($ua, 0, 60);
    }

    protected $fillable = [
        'student_id',
        'course_id',
        'attendance_session_id',
        'attendance_week_id',
        'attendance_time',
        'status',
        'check_in_time',
        'check_out_time',
        'time_spent_seconds',
        'synced',
        'lat',
        'lng',
        'qr_code',
        'device_ip',
        'device_id',
        'user_agent',
    ];

    public function attendanceWeek(): BelongsTo
    {
        return $this->belongsTo(AttendanceWeek::class);
    }

    protected $casts = [
        'attendance_time' => 'datetime',
        'check_in_time' => 'datetime',
        'check_out_time' => 'datetime',
        'synced' => 'boolean',
        'lat' => 'decimal:7',
        'lng' => 'decimal:7',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function attendanceSession(): BelongsTo
    {
        return $this->belongsTo(AttendanceSession::class);
    }

    public static function countsAsPresent(?string $status): bool
    {
        return in_array($status, ['present', 'late'], true);
    }

    /**
     * @param  Builder<Attendance>  $query
     * @return Builder<Attendance>
     */
    public function scopeCountedAsPresent(Builder $query): Builder
    {
        return $query->whereIn('status', ['present', 'late']);
    }

    /**
     * Hide attendance rows whose backing week has been cancelled or
     * deleted (e.g. via an admin reset). A row should only contribute
     * to user-facing counts when it points at an *active* attendance
     * week — anything else is a relic of a reset/cancellation and must
     * not inflate dashboards.
     *
     * Implementation notes:
     * - When attendance_week_id is NULL we keep the row (some legacy
     *   imports / offline syncs may not carry one yet).
     * - Otherwise we require the referenced attendance_weeks row to
     *   exist and not be cancelled. A correlated subquery is cheap and
     *   plays nicely with the existing whereIn filters used elsewhere.
     *
     * @param  Builder<Attendance>  $query
     * @return Builder<Attendance>
     */
    public function scopeActiveWeeksOnly(Builder $query): Builder
    {
        return $query->where(function (Builder $q): void {
            $q->whereNull('attendances.attendance_week_id')
                ->orWhereExists(function ($sub) {
                    $sub->select(\DB::raw(1))
                        ->from('attendance_weeks')
                        ->whereColumn('attendance_weeks.id', 'attendances.attendance_week_id')
                        ->whereNull('attendance_weeks.cancelled_at');
                });
        });
    }
}

