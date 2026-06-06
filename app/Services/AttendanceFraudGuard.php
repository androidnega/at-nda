<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\Student;
use App\Support\DeviceFingerprint;
use App\Support\SchemaFeatures;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Stops a single device from being used to mark attendance for two
 * different students inside the same live session, or inside the same
 * (course, week) bucket. The check rides on the persistent
 * `atenda_dfp` cookie so it survives IP changes (mobile data → Wi-Fi
 * etc.), unlike the legacy `device_ip` lock.
 */
class AttendanceFraudGuard
{
    /**
     * Build the metadata payload we attach to every new attendance row.
     * Reads the device fingerprint cookie (creating one when absent) and
     * sanitises the client_meta JSON the form posted.
     *
     * @return array{
     *     device_fingerprint: ?string,
     *     client_meta: ?array,
     *     device_ip: string,
     *     user_agent: string
     * }
     */
    public static function captureFromRequest(Request $request): array
    {
        $fingerprint = null;
        if (SchemaFeatures::hasAttendancesDeviceFingerprint()) {
            try {
                $fingerprint = DeviceFingerprint::ensure($request);
            } catch (\Throwable $e) {
                Log::warning('AttendanceFraudGuard: fingerprint capture failed: '.$e->getMessage());
            }
        }

        $clientMeta = self::sanitiseClientMeta($request->input('client_meta'));

        return [
            'device_fingerprint' => $fingerprint,
            'client_meta' => $clientMeta,
            'device_ip' => (string) $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 480),
        ];
    }

    /**
     * Return null when the request should be allowed; otherwise an array
     * describing the block so the caller can return an HTTP error and
     * we can audit-log the attempt.
     *
     * @return array{
     *     reason: string,
     *     message: string,
     *     evidence: array
     * }|null
     */
    public static function detectCollision(
        Student $student,
        AttendanceSession $session,
        Request $request
    ): ?array {
        if (! SchemaFeatures::hasAttendancesDeviceFingerprint()) {
            return null;
        }

        $fingerprint = DeviceFingerprint::ensure($request);
        if (! $fingerprint) {
            return null;
        }

        // Same session, different student already marked from this exact
        // device. This catches "I just marked, let me mark for my friend
        // on the same phone".
        $sameSessionHit = Attendance::query()
            ->where('attendance_session_id', $session->id)
            ->where('device_fingerprint', $fingerprint)
            ->where('student_id', '!=', $student->id)
            ->orderByDesc('id')
            ->first();
        if ($sameSessionHit) {
            return [
                'reason' => 'fraud_same_session_dual_mark',
                'message' => 'This device already recorded attendance for another student in this session. Each device can only mark for one student per class.',
                'evidence' => [
                    'session_id' => (int) $session->id,
                    'previous_attendance_id' => (int) $sameSessionHit->id,
                    'previous_student_id' => (int) $sameSessionHit->student_id,
                    'fingerprint_short' => substr($fingerprint, 0, 10),
                ],
            ];
        }

        // Same (course, attendance_week_id), different student. Catches
        // the "open a second session for the same week and try again"
        // variant.
        $weekId = $session->attendance_week_id;
        if ($weekId) {
            $sameWeekHit = Attendance::query()
                ->where('course_id', $session->course_id)
                ->where('attendance_week_id', $weekId)
                ->where('device_fingerprint', $fingerprint)
                ->where('student_id', '!=', $student->id)
                ->orderByDesc('id')
                ->first();
            if ($sameWeekHit) {
                return [
                    'reason' => 'fraud_same_week_dual_mark',
                    'message' => 'This device already marked attendance for another student in this week\'s class. Pass the device only to its real owner.',
                    'evidence' => [
                        'course_id' => (int) $session->course_id,
                        'attendance_week_id' => (int) $weekId,
                        'previous_attendance_id' => (int) $sameWeekHit->id,
                        'previous_student_id' => (int) $sameWeekHit->student_id,
                        'fingerprint_short' => substr($fingerprint, 0, 10),
                    ],
                ];
            }
        }

        return null;
    }

    /**
     * Whittle the JSON the browser posts down to a known set of keys with
     * conservative size limits. Anything we don't recognise is dropped so
     * a malicious client can't bloat the row by posting megabytes of
     * arbitrary data.
     */
    public static function sanitiseClientMeta(mixed $raw): ?array
    {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : null;
        }
        if (! is_array($raw) || empty($raw)) {
            return null;
        }

        $out = [];
        $allowed = [
            'platform' => 32,
            'screen' => 32,
            'tz' => 64,
            'lang' => 16,
            'cores' => 4,
            'memory' => 6,
            'pixel_ratio' => 8,
            'touch' => 1,
            'app' => 16,
            'app_version' => 24,
        ];
        foreach ($allowed as $key => $maxLen) {
            if (! array_key_exists($key, $raw)) {
                continue;
            }
            $value = $raw[$key];
            if (is_bool($value)) {
                $out[$key] = $value;
                continue;
            }
            if (is_int($value) || is_float($value)) {
                $out[$key] = $value;
                continue;
            }
            if (is_string($value)) {
                $out[$key] = mb_substr(trim($value), 0, $maxLen);
            }
        }

        return $out === [] ? null : $out;
    }
}
