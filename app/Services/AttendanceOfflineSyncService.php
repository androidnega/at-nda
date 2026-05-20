<?php

namespace App\Services;

use App\Events\SessionLiveEvent;
use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\Course;
use App\Models\Student;
use App\Support\SecureQrToken;
use Carbon\Carbon;

/**
 * Shared offline sync validation for web + API (session, window, geofence, QR proof, duplicates).
 */
class AttendanceOfflineSyncService
{
    /**
     * @param  list<array<string, mixed>>  $records
     * @return array{synced: int, failed: int}
     */
    public static function process(array $records): array
    {
        $synced = 0;
        $failed = 0;
        /** @var array<int, AttendanceSession> */
        $sessionsToNotify = [];

        foreach ($records as $record) {
            $student = Student::findByIndex($record['index_number'] ?? '');
            if (! $student) {
                $failed++;

                continue;
            }

            if (! $student->profile_image) {
                $failed++;

                continue;
            }

            $course = Course::find($record['course_id'] ?? null);
            if (! $course) {
                $failed++;

                continue;
            }

            $isRep = $student->isClassRepForCourse((int) $course->id);
            if ($isRep) {
                $failed++;

                continue;
            }
            $session = AttendanceSession::resolveForMarking(
                $course,
                isset($record['session_token']) ? (string) $record['session_token'] : null,
                null,
                $isRep
            );
            if (! $session) {
                $failed++;

                continue;
            }

            $supplementalRepMark = $isRep && ! $session->isValid();

            $attendanceTime = Carbon::parse($record['attendance_time']);
            $windowMinutes = $course->attendance_window_minutes ?? 60;
            if (! $supplementalRepMark && $attendanceTime->diffInMinutes(now()) > $windowMinutes) {
                $failed++;

                continue;
            }

            if (! $supplementalRepMark && in_array($session->mode, ['location', 'hybrid'], true)) {
                if (! $session->hasLocation()) {
                    $failed++;

                    continue;
                }
                $lat = $record['latitude'] ?? null;
                $lng = $record['longitude'] ?? null;
                if ($lat !== null && $lng !== null && $lat !== '' && $lng !== '') {
                    $distance = self::haversineMeters(
                        (float) $session->location_lat,
                        (float) $session->location_lng,
                        (float) $lat,
                        (float) $lng
                    );
                    if ($distance > $session->allowedGeofenceRadiusMeters($course)) {
                        $failed++;

                        continue;
                    }
                }
            }

            if (! $supplementalRepMark && $session->mode === 'wifi') {
                $expected = trim((string) ($session->allowed_wifi_ssid ?? ''));
                if ($expected === '') {
                    $failed++;

                    continue;
                }
                $got = trim((string) ($record['wifi_ssid'] ?? ''));
                if ($got !== '' && strcasecmp($got, $expected) !== 0) {
                    $failed++;

                    continue;
                }
            }

            if (($session->mode === 'qr' || $session->mode === 'hybrid') && ! $student->isClassRepForCourse($course->id)) {
                $tok = isset($record['session_token']) ? trim((string) $record['session_token']) : '';
                if ($tok === '' || ! SecureQrToken::isValidSubmission($tok, $session)) {
                    $failed++;

                    continue;
                }
            }

            $existing = Attendance::where('student_id', $student->id)
                ->where('course_id', $course->id)
                ->where('attendance_session_id', $session->id)
                ->exists();

            if (! $existing) {
                Attendance::create([
                    'student_id' => $student->id,
                    'course_id' => $course->id,
                    'attendance_session_id' => $session->id,
                    'attendance_week_id' => $session->attendance_week_id,
                    'attendance_time' => $attendanceTime,
                    'status' => 'present',
                    'synced' => true,
                ]);
                $synced++;
                $sessionsToNotify[$session->id] = $session;
            }
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

        return ['synced' => $synced, 'failed' => $failed];
    }

    private static function haversineMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $R = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLng / 2) * sin($dLng / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $R * $c;
    }
}
