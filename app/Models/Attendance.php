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

        // Bump the cache version on every write so dashboard counts /
        // weekly aggregations cached via CacheVersions get a fresh key
        // the next time they're read. Cheap (two Cache::forever calls)
        // and deterministic — no need for surgical Cache::forget loops.
        //
        // We bump two namespaces:
        //   - 'attendance' (global)              → rep / lecturer aggregations
        //   - 'attendance:student:{id}' (scoped) → that student's own tiles
        // so a single mark only invalidates ONE student's dashboard
        // cache, not everyone else's.
        $bump = function (Attendance $attendance): void {
            try {
                \App\Support\CacheVersions::bump('attendance');
                $studentId = (int) ($attendance->student_id ?? 0);
                if ($studentId > 0) {
                    \App\Support\CacheVersions::bump('attendance:student:'.$studentId);
                }
            } catch (\Throwable $e) {
                // Cache backend down — readers still get correct data
                // from the DB; nothing more to do here.
            }
        };
        static::saved($bump);
        static::deleted($bump);

        // Strip optional columns before insert/update if the running database
        // hasn't been migrated yet. Keeps older deploys from blowing up with
        // "unknown column 'user_agent'" the moment we deploy this change.
        static::saving(function (Attendance $attendance): void {
            if (! \App\Support\SchemaFeatures::hasAttendancesUserAgent()
                && array_key_exists('user_agent', $attendance->getAttributes())) {
                unset($attendance->attributes['user_agent']);
            }
            if (! \App\Support\SchemaFeatures::hasAttendancesManualMark()) {
                foreach (['marked_manually_by_id', 'manual_reason', 'marked_manually_at'] as $col) {
                    if (array_key_exists($col, $attendance->getAttributes())) {
                        unset($attendance->attributes[$col]);
                    }
                }
            }
            if (! \App\Support\SchemaFeatures::hasAttendancesDeviceFingerprint()) {
                foreach (['device_fingerprint', 'client_meta'] as $col) {
                    if (array_key_exists($col, $attendance->getAttributes())) {
                        unset($attendance->attributes[$col]);
                    }
                }
            }
            if (! \App\Support\SchemaFeatures::hasAttendancesLecturerManualMark()
                && array_key_exists('marked_manually_by_lecturer_id', $attendance->getAttributes())) {
                unset($attendance->attributes['marked_manually_by_lecturer_id']);
            }
            // Risk columns added by 2026_06_09_041300; strip on stale deploys
            // so writes from OnlineAttendanceController + AttendanceRiskService
            // don't fail on databases that haven't migrated yet.
            if (! \App\Support\SchemaFeatures::hasAttendancesRiskColumns()) {
                foreach (['risk_score', 'risk_level', 'risk_reasons'] as $col) {
                    if (array_key_exists($col, $attendance->getAttributes())) {
                        unset($attendance->attributes[$col]);
                    }
                }
            }
            // distance_from_anchor added by 2026_06_09_050000 (attendance
            // map redesign). Strip the value if the column hasn't been
            // migrated yet — the mark itself must still go through.
            if (! \App\Support\SchemaFeatures::hasAttendancesDistanceFromAnchor()
                && array_key_exists('distance_from_anchor', $attendance->getAttributes())) {
                unset($attendance->attributes['distance_from_anchor']);
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
        'attendance_uuid',
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
        'distance_from_anchor',
        'qr_code',
        'device_ip',
        'device_id',
        'user_agent',
        'marked_manually_by_id',
        'manual_reason',
        'marked_manually_at',
        'marked_manually_by_lecturer_id',
        'device_fingerprint',
        'client_meta',
        'risk_score',
        'risk_level',
        'risk_reasons',
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
        'marked_manually_at' => 'datetime',
        'client_meta' => 'array',
        'risk_reasons' => 'array',
    ];

    /**
     * True when this row was inserted by a class rep on a student's behalf
     * via the manual-mark flow (rather than the student themselves).
     */
    public function isManuallyMarked(): bool
    {
        return ! empty($this->marked_manually_by_id);
    }

    /**
     * True when a lecturer entered this mark via the online-class
     * roll-call flow.
     */
    public function isManuallyMarkedByLecturer(): bool
    {
        return ! empty($this->marked_manually_by_lecturer_id);
    }

    public function markedManuallyBy(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'marked_manually_by_id');
    }

    public function markedManuallyByLecturer(): BelongsTo
    {
        return $this->belongsTo(Lecturer::class, 'marked_manually_by_lecturer_id');
    }

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
        if (! \Illuminate\Support\Facades\Schema::hasTable('attendance_weeks')
            || ! \Illuminate\Support\Facades\Schema::hasColumn('attendance_weeks', 'cancelled_at')) {
            return $query;
        }

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

