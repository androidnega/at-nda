<?php

namespace App\Support;

use App\Models\AttendanceSession;
use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * Signed QR payload: base64({ "data": { session_id, expires_at }, "sig": hmac_sha256 }).
 */
class SecureQrToken
{
    public static function secret(): ?string
    {
        $s = config('qr.secret');

        return is_string($s) && $s !== '' ? $s : null;
    }

    /**
     * Build token to store on attendance_sessions.qr_token (after session has an id).
     */
    public static function encode(AttendanceSession $session): string
    {
        $secret = self::secret();
        if (! $secret) {
            return Str::random(12);
        }

        $issuedAt = now()->timestamp;
        $ttlSeconds = (int) config('qr.ttl_seconds', 0);
        if ($ttlSeconds > 0) {
            $expiresAt = $issuedAt + $ttlSeconds;
        } else {
            $ttlMin = max(1, (int) config('qr.ttl_minutes', 2));
            $expiresAt = $issuedAt + ($ttlMin * 60);
        }
        $end = $session->end_time ?? $session->expires_at;
        if ($end) {
            $expiresAt = min($expiresAt, $end->timestamp);
        }

        $data = [
            'session_id' => $session->id,
            'expires_at' => $expiresAt,
            // Issued timestamp to support offline validation and screenshot expiry guidance.
            'timestamp' => $issuedAt,
        ];
        if (! empty($session->session_code)) {
            $data['session_code'] = (string) $session->session_code;
        }
        $payload = json_encode($data, JSON_UNESCAPED_SLASHES);
        $sig = hash_hmac('sha256', $payload, $secret);

        return base64_encode(json_encode(['data' => $data, 'sig' => $sig], JSON_UNESCAPED_SLASHES));
    }

    /**
     * @return array{data: array{session_id: int, expires_at: int}}|null
     */
    public static function parseAndVerify(string $token): ?array
    {
        $secret = self::secret();
        if (! $secret) {
            return null;
        }

        $token = trim($token);
        if ($token === '') {
            return null;
        }

        $raw = base64_decode($token, true);
        if ($raw === false) {
            return null;
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded) || ! isset($decoded['data'], $decoded['sig'])) {
            return null;
        }

        $data = $decoded['data'];
        if (! is_array($data) || ! isset($data['session_id'], $data['expires_at'])) {
            return null;
        }

        $payload = json_encode($data, JSON_UNESCAPED_SLASHES);
        $expectedSig = hash_hmac('sha256', $payload, $secret);
        if (! hash_equals($expectedSig, (string) $decoded['sig'])) {
            return null;
        }

        return ['data' => [
            'session_id' => (int) $data['session_id'],
            'expires_at' => (int) $data['expires_at'],
        ]];
    }

    /**
     * True if the scanned/synced string is valid for this session (signature + expiry + session id).
     * When QR_SECRET is unset, compares to plain qr_token only.
     */
    public static function isValidSubmission(string $submitted, AttendanceSession $session): bool
    {
        $submitted = trim($submitted);
        if ($submitted === '') {
            return false;
        }

        if (! self::secret()) {
            return hash_equals((string) ($session->qr_token ?? ''), $submitted);
        }

        $parsed = self::parseAndVerify($submitted);
        if ($parsed === null) {
            return hash_equals((string) ($session->qr_token ?? ''), $submitted);
        }

        if ($parsed['data']['session_id'] !== (int) $session->id) {
            return false;
        }

        if ($parsed['data']['expires_at'] < Carbon::now()->timestamp) {
            return false;
        }

        return true;
    }

    // ---- Rotating short code (manual entry) ----------------------------
    //
    // Reps used to have to read out a long static session_code (e.g.
    // "DTM202-7173"). That worked but anyone with a screenshot of the
    // QR display had a permanent way in. The rotating code below is a
    // 6-char alphanumeric value derived from (session_id, current
    // window, QR_SECRET) and changes every ROTATION_WINDOW_SECONDS so
    // a student who can't scan but can read can still get in within
    // the live window, and a leaked code becomes useless in <30s.

    /** Seconds per rotating-code window. Keep small for snappy rotation. */
    public const ROTATION_WINDOW_SECONDS = 8;

    /** How many *past* windows we still accept as valid. Gives the
     *  student a small grace window if the rep just rotated while they
     *  were typing. 2 windows ≈ 16 seconds of validity after rotation.
     */
    private const ROTATION_GRACE_WINDOWS = 2;

    /**
     * Deterministic short code for the rotating manual-entry display.
     * The same (session, window, secret) always yields the same code so
     * the server can verify without persisting anything.
     *
     * Falls back to the session row's static session_code (uppercased,
     * truncated) when QR_SECRET isn't configured so dev / legacy
     * installs still get a sensible display.
     */
    public static function rotatingCode(AttendanceSession $session, int $windowOffset = 0): string
    {
        $secret = self::secret();
        if (! $secret) {
            $static = strtoupper(preg_replace('/[^A-Z0-9]/i', '', (string) ($session->session_code ?? '')) ?? '');

            return $static !== '' ? substr($static, 0, 6) : '------';
        }

        $window = intdiv(Carbon::now()->timestamp, max(1, self::ROTATION_WINDOW_SECONDS)) + $windowOffset;
        $payload = (int) $session->id.'|'.$window;
        $hash = hash_hmac('sha256', $payload, $secret);
        // Convert hex → uppercase base36-ish: drop letters that look like
        // digits / each other (0/O, 1/I/L) so reps can read it aloud
        // without phonetic ambiguity.
        $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
        $code = '';
        for ($i = 0; $i < 6; $i++) {
            $byte = hexdec(substr($hash, $i * 2, 2));
            $code .= $alphabet[$byte % strlen($alphabet)];
        }

        return $code;
    }

    /** Seconds until the current rotating-code window ends (always 1..ROTATION_WINDOW_SECONDS). */
    public static function rotatingCodeSecondsRemaining(): int
    {
        $win = max(1, self::ROTATION_WINDOW_SECONDS);
        $elapsed = Carbon::now()->timestamp % $win;

        return $win - $elapsed;
    }

    /**
     * True if `$submitted` matches any of the last few rotating codes for
     * this session. The grace windows mean a code that JUST rotated is
     * still accepted, so a student typing in good faith doesn't get
     * stuck.
     */
    public static function isValidRotatingCode(string $submitted, AttendanceSession $session): bool
    {
        $submitted = strtoupper(trim($submitted));
        if ($submitted === '' || strlen($submitted) !== 6) {
            return false;
        }

        for ($offset = 0; $offset >= -self::ROTATION_GRACE_WINDOWS; $offset--) {
            if (hash_equals(self::rotatingCode($session, $offset), $submitted)) {
                return true;
            }
        }

        return false;
    }
}
