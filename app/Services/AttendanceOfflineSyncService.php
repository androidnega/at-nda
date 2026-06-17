<?php

namespace App\Services;

use App\Events\SessionLiveEvent;
use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\Course;
use App\Models\Student;
use App\Support\AttendanceLocation;
use App\Support\SessionFloorAnchor;
use App\Support\SchemaFeatures;
use App\Support\SecureQrToken;
use Carbon\Carbon;

/**
 * Batch sync of offline attendance records. Two consumers:
 *
 *  - `POST /api/attendance/sync` (Flutter outbox) — sends up to 50 rows,
 *    each tagged with a client `attendance_uuid` for idempotency. The
 *    server is expected to short-circuit duplicates, capture late marks,
 *    and return a per-record verdict so the mobile outbox can transition
 *    each row independently.
 *  - Legacy web offline form — never sends `attendance_uuid`. Falls back
 *    to (student_id, session_id) dedup.
 *
 * The result shape:
 *   ['synced' => N, 'failed' => M, 'results' => [
 *      [
 *        'attendance_uuid' => '…' | null,  // echoed when supplied
 *        'index_number' => '…',            // helpful for logs / UI
 *        'status' => 'synced' | 'already' | 'late' | 'rejected',
 *        'reason' => '…',                  // permanent failure code, optional
 *        'message' => '…',                 // user-facing
 *        'attendance_id' => int | null,    // when a row was created
 *      ],
 *      …
 *   ]]
 *
 * `synced` is incremented for both `synced` and `already`. `failed`
 * tracks `rejected` rows (permanent failures the client should treat as
 * Rejected, not Failed).
 */
class AttendanceOfflineSyncService
{
    /**
     * @param  list<array<string, mixed>>  $records
     * @return array{synced: int, failed: int, results: list<array<string, mixed>>}
     */
    public static function process(array $records): array
    {
        $synced = 0;
        $failed = 0;
        $results = [];

        /** @var array<int, AttendanceSession> */
        $sessionsToNotify = [];

        $req = function_exists('request') ? request() : null;
        $hasUuidColumn = SchemaFeatures::hasAttendanceUuid();

        foreach ($records as $record) {
            $attendanceUuid = isset($record['attendance_uuid']) ? trim((string) $record['attendance_uuid']) : '';
            $indexNumber = isset($record['index_number']) ? (string) $record['index_number'] : '';

            $verdict = self::processOne($record, $req, $hasUuidColumn, $sessionsToNotify);

            if ($verdict['status'] === 'synced' || $verdict['status'] === 'already') {
                $synced++;
            } else {
                $failed++;
            }

            $results[] = [
                'attendance_uuid' => $attendanceUuid !== '' ? $attendanceUuid : null,
                'index_number' => $indexNumber,
                'status' => $verdict['status'],
                'reason' => $verdict['reason'] ?? null,
                'message' => $verdict['message'] ?? null,
                'attendance_id' => $verdict['attendance_id'] ?? null,
            ];
        }

        foreach ($sessionsToNotify as $sess) {
            $fresh = $sess->fresh(['course']);
            $presentCount = Attendance::where('attendance_session_id', $fresh->id)
                ->where('status', 'present')
                ->count();
            try {
                event(new SessionLiveEvent($fresh, 'attendance_marked', ['present_count' => $presentCount]));
            } catch (\Throwable $e) {
                \Log::warning('SessionLiveEvent dispatch failed (offline sync): '.$e->getMessage(), ['session_id' => $fresh->id]);
            }
        }

        return ['synced' => $synced, 'failed' => $failed, 'results' => $results];
    }

    /**
     * Run a single record through the same validation gates as the
     * live mark endpoint. Returns a small verdict the outer loop turns
     * into the per-record response.
     *
     * @param  array<string, mixed>  $record
     * @param  array<int, AttendanceSession>  $sessionsToNotify  populated by reference
     * @return array{status: string, reason?: string, message?: string, attendance_id?: int}
     */
    private static function processOne(
        array $record,
        ?\Illuminate\Http\Request $req,
        bool $hasUuidColumn,
        array &$sessionsToNotify
    ): array {
        $attendanceUuid = isset($record['attendance_uuid']) ? trim((string) $record['attendance_uuid']) : '';

        // 1. Idempotency short-circuit — same uuid already seen.
        if ($hasUuidColumn && $attendanceUuid !== '') {
            $existing = Attendance::query()
                ->where('attendance_uuid', $attendanceUuid)
                ->first();
            if ($existing !== null) {
                return [
                    'status' => 'already',
                    'message' => 'Attendance already recorded.',
                    'attendance_id' => (int) $existing->id,
                ];
            }
        }

        // 2. Student lookup.
        $student = Student::findByIndex($record['index_number'] ?? '');
        if (! $student) {
            return ['status' => 'rejected', 'reason' => 'student_not_found', 'message' => 'Student not found.'];
        }
        if (! $student->profile_image) {
            return ['status' => 'rejected', 'reason' => 'student_no_profile_image', 'message' => 'Student profile is incomplete.'];
        }

        $course = Course::find($record['course_id'] ?? null);
        if (! $course) {
            return ['status' => 'rejected', 'reason' => 'course_not_found', 'message' => 'Course not found.'];
        }

        $isRep = $student->isClassRepForCourse((int) $course->id);
        if ($isRep) {
            return ['status' => 'rejected', 'reason' => 'rep_auto_marked', 'message' => 'Class reps are auto-marked.'];
        }

        // 3. Session resolution.
        $session = AttendanceSession::resolveForMarking(
            $course,
            isset($record['session_token']) ? (string) $record['session_token'] : null,
            null,
            $isRep,
            $student->class_id ? (int) $student->class_id : null,
        );
        if (! $session) {
            return ['status' => 'rejected', 'reason' => 'session_not_found', 'message' => 'No session found for this course.'];
        }

        // 4. Late captures — session ended.
        if (! $session->isValid()) {
            return self::captureLate(
                $req,
                $student,
                $session,
                $course,
                $record,
                AttendanceLateCaptureService::REASON_SESSION_EXPIRED,
                $attendanceUuid
            );
        }

        // 5. Window check (only when not a supplemental rep mark).
        $supplementalRepMark = $isRep && ! $session->isValid();
        $attendanceTime = Carbon::parse($record['attendance_time']);
        $windowMinutes = $course->attendance_window_minutes ?? 60;
        if (! $supplementalRepMark && $attendanceTime->diffInMinutes(now()) > $windowMinutes) {
            return self::captureLate(
                $req,
                $student,
                $session,
                $course,
                $record,
                AttendanceLateCaptureService::REASON_OUTSIDE_WINDOW,
                $attendanceUuid,
                $attendanceTime
            );
        }

        // 6. Geofence (location / hybrid).
        $markLat = null;
        $markLng = null;
        $markDistance = null;

        if (! $supplementalRepMark && in_array($session->mode, ['location', 'hybrid'], true)) {
            if (! $session->hasLocation()) {
                return ['status' => 'rejected', 'reason' => 'session_no_anchor', 'message' => 'Session has no GPS anchor.'];
            }
            $lat = $record['latitude'] ?? null;
            $lng = $record['longitude'] ?? null;
            if ($lat !== null && $lng !== null && $lat !== '' && $lng !== '' && is_numeric($lat) && is_numeric($lng)) {
                $distance = AttendanceLocation::distanceMeters(
                    (float) $session->location_lat,
                    (float) $session->location_lng,
                    (float) $lat,
                    (float) $lng
                );
                if (! AttendanceLocation::passesGeofenceCheck(
                    $distance,
                    $session->allowedGeofenceRadiusMeters($course),
                    null,
                    [],
                    SessionFloorAnchor::floorMatches($session, []),
                )) {
                    return ['status' => 'rejected', 'reason' => 'out_of_range', 'message' => 'Out of attendance range.'];
                }
                $markLat = (float) $lat;
                $markLng = (float) $lng;
                $markDistance = AttendanceLocation::storableMeters($distance);
            }
        }

        // 7. Wi-Fi check.
        if (! $supplementalRepMark && $session->mode === 'wifi') {
            $expected = trim((string) ($session->allowed_wifi_ssid ?? ''));
            if ($expected === '') {
                return ['status' => 'rejected', 'reason' => 'session_no_wifi', 'message' => 'Session has no expected Wi-Fi.'];
            }
            $got = trim((string) ($record['wifi_ssid'] ?? ''));
            if ($got !== '' && strcasecmp($got, $expected) !== 0) {
                return ['status' => 'rejected', 'reason' => 'wifi_mismatch', 'message' => 'Wi-Fi network does not match.'];
            }
        }

        // 8. QR proof.
        if (($session->mode === 'qr' || $session->mode === 'hybrid')
            && ! $student->isClassRepForCourse($course->id)) {
            $tok = isset($record['session_token']) ? trim((string) $record['session_token']) : '';
            if ($tok === '' && isset($record['qr_code'])) {
                $tok = trim((string) $record['qr_code']);
            }
            if ($tok === '' || ! SecureQrToken::isValidSubmission($tok, $session)) {
                return ['status' => 'rejected', 'reason' => 'invalid_qr', 'message' => 'Invalid or expired QR.'];
            }
        }

        // 9. Existing-row guard.
        $existing = Attendance::where('student_id', $student->id)
            ->where('course_id', $course->id)
            ->where('attendance_session_id', $session->id)
            ->first();
        if ($existing !== null) {
            // Late-arriving duplicate from another channel. We backfill
            // the uuid if the row hasn't been tagged yet, so a future
            // replay of the same uuid hits the idempotency short-circuit.
            if ($hasUuidColumn && $attendanceUuid !== '' && empty($existing->attendance_uuid)) {
                try {
                    $existing->forceFill(['attendance_uuid' => $attendanceUuid])->save();
                } catch (\Throwable $e) {
                    // ignore — uniqueness collision means another row owns this uuid
                }
            }
            return [
                'status' => 'already',
                'message' => 'Attendance already recorded.',
                'attendance_id' => (int) $existing->id,
            ];
        }

        // 10. Persist.
        $payload = [
            'student_id' => $student->id,
            'course_id' => $course->id,
            'attendance_session_id' => $session->id,
            'attendance_week_id' => $session->attendance_week_id,
            'attendance_time' => $attendanceTime,
            'status' => 'present',
            'synced' => true,
            'lat' => $markLat,
            'lng' => $markLng,
            'distance_from_anchor' => $markDistance,
        ];
        $ip = $req?->ip();
        $ua = $req?->userAgent();
        if (! empty($ip)) {
            $payload['device_ip'] = (string) $ip;
        }
        $payload['user_agent'] = mb_substr((string) ($ua ?: 'offline-sync'), 0, 480);
        if (! empty($record['device_id'])) {
            $payload['device_id'] = mb_substr((string) $record['device_id'], 0, 128);
        }
        if ($hasUuidColumn && $attendanceUuid !== '') {
            $payload['attendance_uuid'] = $attendanceUuid;
        }

        try {
            $created = Attendance::create($payload);
        } catch (\Throwable $e) {
            // Most likely a unique-index race on attendance_uuid — treat
            // as idempotent success.
            if ($hasUuidColumn && $attendanceUuid !== '') {
                $existingByUuid = Attendance::where('attendance_uuid', $attendanceUuid)->first();
                if ($existingByUuid !== null) {
                    return [
                        'status' => 'already',
                        'message' => 'Attendance already recorded.',
                        'attendance_id' => (int) $existingByUuid->id,
                    ];
                }
            }
            \Log::warning('AttendanceOfflineSyncService.create_failed', [
                'error' => $e->getMessage(),
                'uuid' => $attendanceUuid,
            ]);
            return ['status' => 'rejected', 'reason' => 'persist_failed', 'message' => 'Server could not save attendance.'];
        }

        $sessionsToNotify[$session->id] = $session;

        return [
            'status' => 'synced',
            'message' => 'Attendance recorded successfully.',
            'attendance_id' => (int) $created->id,
        ];
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private static function captureLate(
        ?\Illuminate\Http\Request $req,
        Student $student,
        AttendanceSession $session,
        Course $course,
        array $record,
        string $reason,
        string $attendanceUuid,
        ?\DateTimeInterface $capturedAt = null
    ): array {
        if (! SchemaFeatures::hasAttendanceLateUnrecorded()) {
            return [
                'status' => 'rejected',
                'reason' => $reason,
                'message' => AttendanceLateCaptureService::messageForReason($reason),
            ];
        }
        // Reuse the single-row capture so behaviour matches the live
        // markAttendance endpoint exactly.
        $jsonResponse = AttendanceLateCaptureService::captureFor(
            $req ?? request(),
            $student,
            $session,
            $course,
            $reason,
            $record,
            $attendanceUuid !== '' ? $attendanceUuid : null,
            $capturedAt
        );

        $body = $jsonResponse->getData(true);
        return [
            'status' => 'late',
            'reason' => $reason,
            'message' => is_array($body) && isset($body['message'])
                ? (string) $body['message']
                : AttendanceLateCaptureService::messageForReason($reason),
            'attendance_id' => null,
        ];
    }
}
