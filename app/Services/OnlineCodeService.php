<?php

namespace App\Services;

use App\Models\AttendanceSession;
use App\Models\OnlineSessionCode;
use App\Support\SchemaFeatures;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Rolling-code lifecycle for online attendance sessions.
 *
 * Design notes:
 *
 *  - Rotation is LAZY. The first read after a code expires mints the
 *    next one — no cron worker required. The rep dashboard polling
 *    endpoint (GET .../online-code) is the most frequent driver of
 *    rotation; student submissions also trigger it as a defensive net.
 *
 *  - Generation uses random_int() on the charset from
 *    config('attendance.online_code_charset'). Collisions inside the
 *    same session are extremely unlikely (10000 possibilities for
 *    4 digits, lifetime ~2 min) but we re-roll up to 8 times if the
 *    generated code matches another code with overlapping live window
 *    on the SAME session. Cross-session collisions are fine — the
 *    student-side validator scopes lookups by session_id.
 *
 *  - A small grace window (config('attendance.online_code_grace_seconds'))
 *    keeps the previously-current code valid AFTER rotation so a student
 *    mid-submit doesn't get a spurious 422.
 *
 *  - Every method is a no-op when the online_session_codes table does not
 *    exist on the running database. Lets us deploy code before running
 *    the migration without crashing prod.
 */
class OnlineCodeService
{
    public function rotationSeconds(): int
    {
        $seconds = (int) config('attendance.online_code_rotation_seconds', 120);

        return $seconds > 0 ? $seconds : 120;
    }

    public function graceSeconds(): int
    {
        $grace = (int) config('attendance.online_code_grace_seconds', 10);

        return $grace >= 0 ? $grace : 10;
    }

    public function codeLength(): int
    {
        $len = (int) config('attendance.online_code_length', 4);

        return ($len >= 3 && $len <= 8) ? $len : 4;
    }

    public function charset(): string
    {
        $charset = (string) config('attendance.online_code_charset', '0123456789');

        return $charset !== '' ? $charset : '0123456789';
    }

    /**
     * Return the currently-valid code for $session, generating a fresh one
     * if none exists or the last one has expired beyond the grace window.
     *
     * Returns null when:
     *   - the online_session_codes table is missing on this database, or
     *   - the session itself is not active / not online.
     *
     * Callers should treat null as "no code available right now".
     */
    public function currentFor(AttendanceSession $session): ?OnlineSessionCode
    {
        if (! SchemaFeatures::hasOnlineSessionCodes()) {
            return null;
        }
        if ((string) $session->mode !== 'online') {
            return null;
        }

        $now = Carbon::now();

        // Cheap fast path: pick the newest still-live code.
        $live = OnlineSessionCode::query()
            ->where('session_id', $session->id)
            ->where('expires_at', '>', $now)
            ->orderByDesc('expires_at')
            ->first();

        if ($live !== null) {
            return $live;
        }

        return $this->mint($session, $now);
    }

    /**
     * Match a student-submitted code against the session's recent codes.
     *
     * Returns true if:
     *   - $submitted equals the current code, OR
     *   - $submitted equals a code whose expires_at is within the grace
     *     window (so a student who started typing under the previous code
     *     still succeeds).
     *
     * Case- and whitespace-insensitive.
     */
    public function validate(string $submitted, AttendanceSession $session): bool
    {
        if (! SchemaFeatures::hasOnlineSessionCodes()) {
            return false;
        }

        $normalised = strtoupper(trim($submitted));
        if ($normalised === '') {
            return false;
        }

        // Trigger lazy rotation so the current code is fresh.
        $this->currentFor($session);

        $now = Carbon::now();
        $graceCutoff = $now->copy()->subSeconds($this->graceSeconds());

        $hit = OnlineSessionCode::query()
            ->where('session_id', $session->id)
            ->where('expires_at', '>=', $graceCutoff)
            ->get(['code', 'expires_at']);

        foreach ($hit as $row) {
            if (hash_equals(strtoupper((string) $row->code), $normalised)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Mint and persist a new code for the session. Picks the next time
     * window starting at NOW (caller decides when to call this, so the
     * window doesn't get tied to wall-clock minute boundaries).
     *
     * Uses an INSERT inside a tiny transaction so a thundering herd of
     * polling requests don't double-mint. The (session_id, expires_at)
     * lookup later wins consistently for the freshest row regardless of
     * which racer got there first.
     */
    public function mint(AttendanceSession $session, ?Carbon $startAt = null): ?OnlineSessionCode
    {
        if (! SchemaFeatures::hasOnlineSessionCodes()) {
            return null;
        }

        $startAt ??= Carbon::now();
        $expiresAt = $startAt->copy()->addSeconds($this->rotationSeconds());

        // Generate, re-roll on the unlikely same-session collision.
        $code = null;
        for ($attempt = 0; $attempt < 8; $attempt++) {
            $candidate = $this->generateCode();
            $clash = OnlineSessionCode::query()
                ->where('session_id', $session->id)
                ->where('code', $candidate)
                ->where('expires_at', '>=', $startAt)
                ->exists();
            if (! $clash) {
                $code = $candidate;
                break;
            }
        }
        if ($code === null) {
            $code = $this->generateCode();
        }

        $row = null;

        DB::transaction(function () use ($session, $code, $startAt, $expiresAt, &$row): void {
            // Re-check inside the transaction: maybe a concurrent caller
            // just minted one. If so, prefer their row (avoids burning
            // two windows on one session in flap).
            $existing = OnlineSessionCode::query()
                ->where('session_id', $session->id)
                ->where('expires_at', '>', $startAt)
                ->orderByDesc('expires_at')
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                $row = $existing;

                return;
            }

            $row = OnlineSessionCode::create([
                'session_id' => $session->id,
                'code'       => $code,
                'starts_at'  => $startAt,
                'expires_at' => $expiresAt,
                'created_at' => Carbon::now(),
            ]);
        });

        return $row;
    }

    /**
     * Random short code from the configured charset. Uses random_int for
     * cryptographic randomness — fine for this throwaway use-case.
     */
    public function generateCode(): string
    {
        $charset = $this->charset();
        $length  = $this->codeLength();
        $max     = strlen($charset) - 1;
        $out     = '';
        for ($i = 0; $i < $length; $i++) {
            $out .= $charset[random_int(0, $max)];
        }

        return $out;
    }
}
