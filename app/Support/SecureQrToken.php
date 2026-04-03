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
}
