<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Support\SecureQrToken;
use Illuminate\Support\Str;

class AttendanceSession extends Model
{
    protected $fillable = [
        'course_id',
        'class_id',
        'session_index',
        'attendance_week_id',
        'mode',
        'attendance_mode',
        'is_active',
        'checkout_enabled',
        'session_token',
        'qr_token',
        'expires_at',
        'location_lat',
        'location_lng',
        'gps_accuracy',
        'attendance_range_m',
        'lecturer_id',
        'venue_id',
        'start_time',
        'end_time',
        'expected_end_time',
        'allowed_wifi_ssid',
        'lecturer_status',
        'session_code',
        'meeting_platform',
        'meeting_link',
    ];


    public function attendanceWeek(): BelongsTo
    {
        return $this->belongsTo(AttendanceWeek::class);
    }

    protected $casts = [
        'is_active' => 'boolean',
        'expires_at' => 'datetime',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'expected_end_time' => 'datetime',
        'checkout_enabled' => 'boolean',
        'location_lat' => 'decimal:7',
        'location_lng' => 'decimal:7',
        'gps_accuracy' => 'float',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    /**
     * Snapshot from course when session opens (API can show lecturer without joining courses).
     */
    public function lecturer(): BelongsTo
    {
        return $this->belongsTo(Lecturer::class);
    }

    /**
     * Snapshot venue from course when session opens.
     */
    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    /**
     * Reopen the existing session for this (course, class, week, date)
     * tuple if one already exists, or create a new one. Never produces a
     * duplicate session for the same logical class meeting, so previously
     * marked students stay marked when a rep closes + reopens.
     *
     * Returns [$session, $wasReopened]. Caller is responsible for
     * auto-marking reps, broadcasting, etc.
     *
     * @param  array<string,mixed>  $attrs
     * @return array{0: self, 1: bool}
     */
    public static function openOrReopenForClass(
        int $courseId,
        ?int $classId,
        int $attendanceWeekId,
        array $attrs
    ): array {
        // Pick the most recent session whose week + class + course match
        // *and* whose start_time falls on today's date. Two sessions in the
        // same week but on different real days are still distinct meetings.
        $today = now()->toDateString();

        $query = self::query()
            ->where('course_id', $courseId)
            ->where('attendance_week_id', $attendanceWeekId)
            ->whereDate('start_time', $today)
            ->orderByDesc('id');

        if ($classId !== null && $classId > 0 && \App\Support\SchemaFeatures::hasAttendanceSessionsClassId()) {
            $query->where('class_id', $classId);
        }

        $existing = $query->first();

        // Strip immutable attributes the caller might pass.
        $attrs['is_active'] = true;
        $attrs['course_id'] = $courseId;
        if ($classId !== null && \App\Support\SchemaFeatures::hasAttendanceSessionsClassId()) {
            $attrs['class_id'] = $classId;
        }
        $attrs['attendance_week_id'] = $attendanceWeekId;

        if ($existing) {
            $update = $attrs;
            // Preserve the original session_token so QR codes & links the
            // students bookmarked keep working. Same for session_index.
            unset($update['session_token'], $update['session_index'], $update['start_time']);
            // Refresh expiry / lecturer status / venue / mode for the new
            // window the rep just chose.
            $existing->update($update);

            return [$existing->fresh(), true];
        }

        $attrs['session_index'] = $attrs['session_index'] ?? self::nextIndexForCourse($courseId);

        return [self::create($attrs), false];
    }

    /**
     * Human-readable join code (e.g. CSC101-4821) for manual entry and QR payload.
     */
    public static function generateUniqueSessionCodeForCourse(Course $course): string
    {
        $raw = preg_replace('/[^A-Za-z0-9]/', '', (string) ($course->course_code ?? ''));
        $prefix = strtoupper(strlen($raw) >= 2 ? substr($raw, 0, 14) : ('C'.$course->id));
        for ($attempt = 0; $attempt < 40; $attempt++) {
            $code = $prefix.'-'.str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            if (! static::query()->where('session_code', $code)->exists()) {
                return $code;
            }
        }

        return $prefix.'-'.strtoupper(Str::random(4));
    }

    public function isExpired(): bool
    {
        $end = $this->end_time ?? $this->expires_at;

        return $end !== null && $end->isPast();
    }

    public function isValid(): bool
    {
        return $this->is_active && !$this->isExpired();
    }

    /**
     * Whether a class rep may still record attendance for this session after it has stopped being
     * "live" (ended or deactivated), within a configurable lookback from session start.
     */
    public function canBeMarkedByClassRep(): bool
    {
        if ($this->isValid()) {
            return true;
        }

        $days = (int) config('app.attendance_rep_supplemental_days', 14);
        $start = $this->start_time ?? $this->created_at;
        if ($start === null) {
            return false;
        }

        return $start->greaterThanOrEqualTo(now()->subDays($days));
    }

    /**
     * Resolve the session used for marking: current active session, or token/id match.
     * Class reps may mark on a recently ended session (see {@see canBeMarkedByClassRep}).
     */
    public static function resolveForMarking(
        Course $course,
        ?string $sessionToken,
        ?int $sessionId,
        bool $isClassRep,
        ?int $studentClassId = null,
    ): ?self {
        $session = null;

        if ($sessionId !== null && $sessionId > 0) {
            $session = static::query()->with(['course', 'attendanceWeek'])->find($sessionId);
            if (! $session || (int) $session->course_id !== (int) $course->id) {
                return null;
            }
        } elseif ($sessionToken !== null && trim($sessionToken) !== '') {
            $session = static::findByQrOrSessionToken(trim($sessionToken));
            if (! $session || (int) $session->course_id !== (int) $course->id) {
                return null;
            }
        } else {
            $session = $course->activeSessionForClass($studentClassId);
        }

        if (! $session) {
            return null;
        }

        if ($studentClassId !== null && $studentClassId > 0
            && ! \App\Support\AttendanceSessionClassScope::sessionBelongsToClass($session, $studentClassId)) {
            return null;
        }

        if ($session->isValid()) {
            return $session;
        }

        if ($isClassRep && $session->canBeMarkedByClassRep()) {
            return $session;
        }

        return null;
    }

    /**
     * Active session rows within the current time window.
     * Prefers start_time/end_time; falls back to created_at/expires_at for legacy rows.
     */
    /**
     * Session has a definite end in the past (end_time or expires_at).
     */
    public function scopeEnded($query)
    {
        return $query->whereRaw(
            'COALESCE(end_time, expires_at) IS NOT NULL AND COALESCE(end_time, expires_at) < ?',
            [Carbon::now()]
        );
    }

    public function scopeActiveWithinTimeWindow($query)
    {
        $now = Carbon::now();

        return $query
            ->where('is_active', true)
            ->whereRaw('COALESCE(start_time, created_at) <= ?', [$now])
            ->where(function ($q) use ($now) {
                $q->where(function ($q2) use ($now) {
                    $q2->whereNotNull('end_time')->where('end_time', '>=', $now);
                })->orWhere(function ($q2) use ($now) {
                    $q2->whereNull('end_time')->where(function ($q3) use ($now) {
                        $q3->whereNull('expires_at')->orWhere('expires_at', '>=', $now);
                    });
                });
            });
    }

    /**
     * Mark expired sessions inactive (end_time or expires_at in the past).
     */
    public static function deactivateExpiredSessions(): void
    {
        $now = Carbon::now();

        static::query()
            ->where('is_active', true)
            ->where(function ($q) use ($now) {
                $q->where(function ($q2) use ($now) {
                    $q2->whereNotNull('end_time')->where('end_time', '<', $now);
                })->orWhere(function ($q2) use ($now) {
                    $q2->whereNull('end_time')->whereNotNull('expires_at')->where('expires_at', '<', $now);
                });
            })
            ->update(['is_active' => false]);
    }

    /**
     * Session has anchor coordinates; radius may come from session, course, or app default.
     */
    public function hasLocation(): bool
    {
        if (empty($this->location_lat) || empty($this->location_lng)) {
            return false;
        }

        return $this->effectiveAttendanceRangeMeters() > 0;
    }

    /**
     * Geofence radius for checks and API: session override, then course, then config default.
     */
    public function effectiveAttendanceRangeMeters(?Course $course = null): int
    {
        $course ??= $this->course;

        return (int) ($this->attendance_range_m ?? $course?->attendance_range_m ?? config('app.default_attendance_range_m', 200));
    }

    /**
     * Radius used for server + mobile “in range” checks: max(nominal, floor) + GPS buffer.
     * Wider than “nominal” radius to reduce false rejects from GPS drift.
     */
    public function allowedGeofenceRadiusMeters(?Course $course = null): int
    {
        $nominal = $this->effectiveAttendanceRangeMeters($course);
        $floor = (int) config('app.min_geofence_check_m', 150);
        $buffer = (int) config('app.geofence_gps_buffer_m', 50);

        return (int) max($nominal, $floor) + $buffer;
    }

    /** Geofence / anchor point applies (location-only or hybrid). */
    public function requiresLocation(): bool
    {
        return in_array($this->mode, ['location', 'hybrid'], true);
    }

    /** Students who are not class reps must prove they scanned the live QR (QR-only or hybrid). */
    public function requiresQrProof(): bool
    {
        return in_array($this->mode, ['qr', 'hybrid'], true);
    }

    /** Attendance by matching campus/class Wi‑Fi SSID (no GPS / QR). */
    public function isWifiMode(): bool
    {
        return $this->mode === 'wifi';
    }

    public function isCheckInCheckoutMode(): bool
    {
        return $this->attendance_mode === 'checkin_checkout';
    }

    /**
     * True when attendance uses the Wi‑Fi anchor mode (SSID is set on the session by the rep).
     */
    public function requiresWifiSsidProof(): bool
    {
        return $this->isWifiMode();
    }

    /**
     * Next ordinal for this course (1 right after admin clears sessions for this course).
     */
    public static function nextIndexForCourse(int $courseId): int
    {
        return (int) (static::query()->where('course_id', $courseId)->max('session_index') ?? 0) + 1;
    }

    /**
     * JSON embedded in QR images. Static for the lifetime of the session (token set once at creation).
     */
    public function getQrPayload(): array
    {
        return [
            'session_id' => $this->id,
            'token' => $this->qr_token,
            'course_id' => $this->course_id,
        ];
    }

    /**
     * Resolve session from scanned/synced value: DB match, or signed payload (session_id + HMAC).
     */
    public static function findByQrOrSessionToken(?string $token): ?self
    {
        if ($token === null || trim($token) === '') {
            return null;
        }

        $token = trim($token);

        $session = static::query()
            ->where(function ($q) use ($token) {
                $q->where('qr_token', $token)
                    ->orWhere('session_token', $token);
            })
            ->first();

        if ($session) {
            return $session;
        }

        $parsed = SecureQrToken::parseAndVerify($token);
        if ($parsed === null) {
            return null;
        }

        $session = static::find($parsed['data']['session_id']);
        if (! $session) {
            return null;
        }

        return SecureQrToken::isValidSubmission($token, $session) ? $session : null;
    }

    /**
     * Active session for course from optional qr/session token, or current active session when token is empty.
     */
    public static function forCourseFromToken(Course $course, ?string $sessionToken): ?self
    {
        if ($sessionToken === null || trim($sessionToken) === '') {
            return $course->activeSession();
        }

        $session = static::findByQrOrSessionToken(trim($sessionToken));
        if (! $session || (int) $session->course_id !== (int) $course->id) {
            return null;
        }

        return $session->isValid() ? $session : null;
    }

    /**
     * Resolve by token alone (Flutter scan without course_id); only valid active sessions.
     */
    public static function findActiveGloballyByQrOrSessionToken(?string $token): ?self
    {
        if ($token === null || trim($token) === '') {
            return null;
        }

        $session = static::with('course')->findByQrOrSessionToken(trim($token));

        return $session?->isValid() ? $session : null;
    }

    /**
     * When QR_SECRET is set: refresh stored token if missing, invalid, or past inner expires_at.
     * Call before returning qr_token in APIs so students always get a usable token when polling.
     */
    public function ensureSignedQrTokenFresh(): void
    {
        if (! SecureQrToken::secret()) {
            return;
        }

        if (empty($this->qr_token)) {
            $this->qr_token = SecureQrToken::encode($this);
            $this->save();

            return;
        }

        $parsed = SecureQrToken::parseAndVerify($this->qr_token);
        $expired = $parsed === null
            || $parsed['data']['expires_at'] < \Carbon\Carbon::now()->timestamp;

        if ($expired) {
            $this->qr_token = SecureQrToken::encode($this);
            $this->save();
        }
    }

    /**
     * New signed token (e.g. after rep updates anchor). Only when QR_SECRET is set.
     */
    public function regenerateSignedQrToken(): void
    {
        if (! SecureQrToken::secret()) {
            return;
        }

        $this->qr_token = SecureQrToken::encode($this);
        $this->save();
    }

    protected static function booted(): void
    {
        // Drop the optional class_id attribute on older deploys before the
        // 2026_05_21_160000_add_class_id_to_attendance_sessions_table
        // migration has run, so create()/update() never fail with
        // "unknown column 'class_id'".
        static::saving(function (self $session): void {
            if (! \App\Support\SchemaFeatures::hasAttendanceSessionsClassId()
                && array_key_exists('class_id', $session->getAttributes())) {
                unset($session->attributes['class_id']);
            }
            // Same trick for the online-meeting columns introduced by
            // 2026_06_09_041000_add_meeting_fields_to_attendance_sessions.
            // Skip the strip when at least one column exists.
            if (! \App\Support\SchemaFeatures::hasAttendanceSessionsMeetingFields()) {
                foreach (['meeting_platform', 'meeting_link'] as $col) {
                    if (array_key_exists($col, $session->getAttributes())) {
                        unset($session->attributes[$col]);
                    }
                }
            }
            // Defensive: the deprecated online_submode column is dormant on
            // the database but the removal of the feature means nothing
            // should be writing to it anymore. Silently drop any value to
            // keep accidental callers from re-introducing the field.
            if (array_key_exists('online_submode', $session->getAttributes())) {
                unset($session->attributes['online_submode']);
            }
        });

        // Bust the short live-sessions cache whenever a session row changes
        // so reps / students see open / close events within ~one poll cycle.
        static::saved(fn () => \App\Support\LiveAttendanceCache::bump());
        static::deleted(fn () => \App\Support\LiveAttendanceCache::bump());

        static::creating(function (AttendanceSession $session) {
            if (empty($session->session_token)) {
                $session->session_token = Str::random(32);
            }
            $course = $session->course_id ? Course::find($session->course_id) : null;
            if ($course) {
                $session->lecturer_id ??= $course->lecturer_id;
                $session->venue_id ??= $course->venue_id;
                if (empty($session->session_code)) {
                    $session->session_code = static::generateUniqueSessionCodeForCourse($course);
                }
            }
            // Signed QR needs session id → generated in `created` when QR_SECRET is set.
            if (empty($session->qr_token) && ! SecureQrToken::secret()) {
                $session->qr_token = Str::random(12);
            }
            if ($session->start_time === null) {
                $session->start_time = now();
            }
            if ($session->end_time === null && $session->expires_at !== null) {
                $session->end_time = $session->expires_at;
            }
        });

        static::created(function (AttendanceSession $session) {
            if (! SecureQrToken::secret()) {
                return;
            }

            $session->refresh();
            $session->qr_token = SecureQrToken::encode($session);
            $session->saveQuietly(); // avoid re-firing created
        });
    }
}
