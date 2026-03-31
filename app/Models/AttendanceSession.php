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
        'session_index',
        'attendance_week_id',
        'mode',
        'is_active',
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
        'allowed_wifi_ssid',
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
        'location_lat' => 'decimal:7',
        'location_lng' => 'decimal:7',
        'gps_accuracy' => 'float',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
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

    /** Non–course reps must prove they scanned the live QR (QR-only or hybrid). */
    public function requiresQrProof(): bool
    {
        return in_array($this->mode, ['qr', 'hybrid'], true);
    }

    /** Attendance by matching campus/class Wi‑Fi SSID (no GPS / QR). */
    public function isWifiMode(): bool
    {
        return $this->mode === 'wifi';
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
        static::creating(function (AttendanceSession $session) {
            if (empty($session->session_token)) {
                $session->session_token = Str::random(32);
            }
            $course = $session->course_id ? Course::find($session->course_id) : null;
            if ($course) {
                $session->lecturer_id ??= $course->lecturer_id;
                $session->venue_id ??= $course->venue_id;
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
