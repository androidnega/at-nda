<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single rolling attendance code for one online attendance session.
 *
 * Lifecycle: minted by {@see \App\Services\OnlineCodeService::currentFor()}
 * at lazy-rotation time. Each row covers a [starts_at, expires_at) window;
 * older rows are retained for a short grace period (see
 * config('attendance.online_code_grace_seconds')) then become inert.
 *
 * Reads happen on every rep dashboard poll AND every student submission —
 * both should hit the (session_id, expires_at) index defined in the
 * 2026_06_09_041100_create_online_session_codes_table migration.
 */
class OnlineSessionCode extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'session_id',
        'code',
        'starts_at',
        'expires_at',
        'created_at',
    ];

    protected $casts = [
        'starts_at'  => 'datetime',
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(AttendanceSession::class, 'session_id');
    }

    /** True when (now() < expires_at). Ignores starts_at — caller already filtered. */
    public function isLive(?Carbon $now = null): bool
    {
        $now ??= now();

        return $this->expires_at !== null && $this->expires_at->greaterThan($now);
    }

    /** Seconds until this code rotates out (negative if already expired). */
    public function secondsLeft(?Carbon $now = null): int
    {
        $now ??= now();
        if ($this->expires_at === null) {
            return 0;
        }

        return (int) ($this->expires_at->getTimestamp() - $now->getTimestamp());
    }
}
