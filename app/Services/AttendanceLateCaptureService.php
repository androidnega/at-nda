<?php

namespace App\Services;

use App\Models\AttendanceLateUnrecorded;
use App\Models\AttendanceSession;
use App\Models\Course;
use App\Models\Student;
use App\Support\SchemaFeatures;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Persists "valid but too late" attendance submissions so a class rep or
 * lecturer can review them later. Caller must already have validated the
 * student / session / course; this service only writes the captured row.
 */
class AttendanceLateCaptureService
{
    public const REASON_SESSION_EXPIRED = 'session_expired';
    public const REASON_OUTSIDE_WINDOW = 'outside_window';

    /**
     * Capture an offline submission that arrived after the session ended
     * OR after the attendance window. Returns a 202 response with
     * `late=true` so the mobile client transitions the row to Quarantined.
     *
     * On a unique-constraint collision (same attendance_uuid replayed) we
     * still return 202 — the row is already captured and waiting for
     * approval, which the mobile outbox treats identically.
     */
    public static function captureFor(
        Request $request,
        Student $student,
        AttendanceSession $session,
        ?Course $course,
        string $reason,
        array $payload,
        ?string $attendanceUuid = null,
        ?\DateTimeInterface $capturedAt = null
    ): JsonResponse {
        if (! SchemaFeatures::hasAttendanceLateUnrecorded()) {
            // Schema not migrated yet — fall back to the legacy 422 so we
            // don't lose data silently and the mobile retry policy can
            // mark the row Rejected.
            return response()->json([
                'status' => 'error',
                'message' => self::messageForReason($reason),
                'late' => false,
            ], 422);
        }

        $clean = self::sanitisePayload($payload);

        try {
            $row = AttendanceLateUnrecorded::query()->firstOrCreate(
                $attendanceUuid !== null && $attendanceUuid !== ''
                    ? ['attendance_uuid' => $attendanceUuid]
                    : [
                        'student_id' => $student->id,
                        'attendance_session_id' => $session->id,
                        // Use the captured_at to distinguish two late
                        // submissions for the same (student, session)
                        // when the client did not supply a uuid.
                        'sync_attempted_at' => now()->toDateTimeString(),
                    ],
                [
                    'attendance_uuid' => $attendanceUuid !== '' ? $attendanceUuid : null,
                    'student_id' => $student->id,
                    'attendance_session_id' => $session->id,
                    'course_id' => $course?->id ?? $session->course_id,
                    'attendance_week_id' => $session->attendance_week_id,
                    'reason' => $reason,
                    'payload' => $clean,
                    'captured_at' => $capturedAt,
                    'sync_attempted_at' => now(),
                    'decision' => AttendanceLateUnrecorded::DECISION_PENDING,
                ]
            );

            return response()->json([
                'status' => 'late',
                'late' => true,
                'message' => self::messageForReason($reason),
                'attendance_uuid' => $attendanceUuid !== '' ? $attendanceUuid : null,
                'late_id' => (int) $row->id,
                'decision' => $row->decision,
                'reason' => $reason,
            ], 202);
        } catch (\Throwable $e) {
            Log::warning('AttendanceLateCaptureService.write_failed', [
                'error' => $e->getMessage(),
                'student_id' => $student->id,
                'session_id' => $session->id,
                'reason' => $reason,
            ]);

            return response()->json([
                'status' => 'error',
                'message' => self::messageForReason($reason),
                'late' => false,
            ], 422);
        }
    }

    public static function messageForReason(string $reason): string
    {
        return match ($reason) {
            self::REASON_SESSION_EXPIRED => 'Session has ended. Awaiting lecturer approval.',
            self::REASON_OUTSIDE_WINDOW => 'Outside the attendance window. Awaiting lecturer approval.',
            default => 'Outside attendance window. Awaiting lecturer approval.',
        };
    }

    /**
     * Keys that may legitimately appear in a late-capture payload. The
     * sanitiser is an explicit allow-list — anything not named here
     * gets dropped, including future biometric / sensor blobs we have
     * not yet anticipated. Defensive privacy choice.
     */
    private const ALLOWED_KEYS = [
        'index_number', 'course_id', 'session_id', 'week_id',
        'lat', 'lng', 'latitude', 'longitude',
        'accuracy', 'horizontal_accuracy',
        'qr_code', 'session_token', 'session_code',
        'qr_sig', 'qr_t',
        'wifi_ssid', 'timestamp',
        'device_id', 'device_ip',
        'client_meta', 'attendance_uuid',
    ];

    /**
     * Biometric / sensor blobs that must NEVER reach the late-capture
     * table, even by accident via `client_meta` or a future client
     * change. POST_IMPLEMENTATION_ARCHITECTURE_AUDIT §Sec-4 / P2.6.
     */
    private const BIOMETRIC_KEYS = [
        'face_descriptor', 'face_embedding', 'face_vector', 'face_template',
        'embedding', 'descriptor', 'biometric', 'biometric_vector',
        'fingerprint_vector', 'voice_print', 'iris_template',
    ];

    /**
     * Drop volatile / oversized keys before persisting. Caps the JSON at
     * a sensible size so a malicious client can't blow up the table.
     *
     * Privacy contract:
     *  - allow-list only: keys outside ALLOWED_KEYS are dropped silently.
     *  - biometric vectors (BIOMETRIC_KEYS) are stripped at every depth
     *    so a payload sneaking `face_descriptor` inside `client_meta`
     *    still gets cleaned.
     *
     * The cap of 64 array entries / 256 char string per nested scalar
     * is a defensive ceiling against malicious payloads.
     *
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private static function sanitisePayload(array $raw): array
    {
        $out = [];
        foreach (self::ALLOWED_KEYS as $key) {
            if (! array_key_exists($key, $raw)) {
                continue;
            }
            if (in_array($key, self::BIOMETRIC_KEYS, true)) {
                // Top-level biometric — never persist.
                continue;
            }
            $v = $raw[$key];
            if (is_string($v)) {
                $out[$key] = mb_substr($v, 0, 512);
            } elseif (is_array($v)) {
                $out[$key] = self::sanitiseNested($v);
            } else {
                $out[$key] = $v;
            }
        }

        return $out;
    }

    /**
     * Recursive scrub used by sanitisePayload for `client_meta` and any
     * other allow-listed array key. Drops biometric keys at every
     * depth; caps width and string lengths.
     *
     * @param  array<string|int, mixed>  $arr
     * @return array<string|int, mixed>
     */
    private static function sanitiseNested(array $arr): array
    {
        $out = [];
        $count = 0;
        foreach ($arr as $k => $v) {
            if ($count >= 64) {
                break;
            }
            // Drop any biometric blob regardless of nesting depth.
            $lower = is_string($k) ? strtolower($k) : '';
            if ($lower !== '' && in_array($lower, self::BIOMETRIC_KEYS, true)) {
                continue;
            }
            if (is_string($v)) {
                $out[$k] = mb_substr($v, 0, 256);
            } elseif (is_array($v)) {
                $out[$k] = self::sanitiseNested($v);
            } elseif (is_scalar($v)) {
                $out[$k] = $v;
            }
            // non-scalars (objects, resources) silently dropped
            $count++;
        }

        return $out;
    }
}
