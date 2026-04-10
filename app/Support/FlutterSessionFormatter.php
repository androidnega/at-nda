<?php

namespace App\Support;

use App\Models\AttendanceSession;
use App\Models\Course;
use Carbon\Carbon;

/**
 * Stable session JSON for Flutter (shared by legacy /api/sessions/active and /api/v1/sessions/active).
 */
class FlutterSessionFormatter
{
    /**
     * @return array<string, mixed>
     */
    public static function format(AttendanceSession $session): array
    {
        $session->loadMissing(['lecturer', 'venue', 'course.lecturer', 'course.venueRelation', 'attendanceWeek']);
        $course = $session->course;
        if ($course) {
            $course->loadMissing(['lecturer', 'venueRelation']);
        }

        $mode = $session->mode ?? 'qr';
        if (! in_array($mode, ['qr', 'location', 'hybrid', 'wifi'], true)) {
            $mode = 'qr';
        }

        $lat = $session->location_lat !== null ? (float) $session->location_lat : null;
        $lng = $session->location_lng !== null ? (float) $session->location_lng : null;
        $range = $session->allowedGeofenceRadiusMeters($course);

        $venue = optional($session->venue)->name
            ?? optional(optional($course)->venueRelation)->name
            ?? (is_string(optional($course)->venue) ? $course->venue : null)
            ?? 'N/A';

        $lecturerName = optional($session->lecturer)->name
            ?? optional(optional($course)->lecturer)->name
            ?? optional($course)->lecturer_name
            ?? 'N/A';

        $courseName = optional($course)->course_name ?? 'N/A';
        $courseCode = optional($course)->course_code ?? 'N/A';

        $now = Carbon::now();
        $end = $session->end_time ?? $session->expires_at;
        $expectedEnd = $session->expected_end_time;

        // Rep closed early: stop advertising a future end so clients end countdown immediately.
        if (! $session->is_active) {
            if ($end !== null && $end->isFuture()) {
                $end = $now;
            }
            if ($expectedEnd !== null && $expectedEnd->isFuture()) {
                $expectedEnd = $now;
            }
        }

        if ($session->requiresQrProof()) {
            $session->ensureSignedQrTokenFresh();
        }

        return [
            'id' => $session->id,
            'session_code' => $session->session_code ? (string) $session->session_code : null,
            'session_index' => (int) ($session->session_index ?? 1),
            'week_number' => $session->attendanceWeek?->week_number,
            'week_id' => $session->attendance_week_id,
            'course_id' => $session->course_id,
            'course_name' => $courseName,
            'course_code' => $courseCode,
            'credit_hours' => (int) ($course?->credit_hours ?? 2),
            'venue' => $venue,
            'lecturer_name' => $lecturerName,
            'mode' => $mode,
            'attendance_mode' => (string) ($session->attendance_mode ?? 'instant'),
            'location_required' => $session->requiresLocation(),
            'requires_qr_proof' => $session->requiresQrProof(),
            'wifi_required' => $session->requiresWifiSsidProof(),
            'expected_wifi_ssid' => $session->allowed_wifi_ssid
                ? trim((string) $session->allowed_wifi_ssid)
                : null,
            'lat' => $lat,
            'lng' => $lng,
            'gps_accuracy' => $session->gps_accuracy !== null ? (float) $session->gps_accuracy : null,
            'range_meters' => $range,
            'qr_token' => $session->requiresQrProof() ? $session->qr_token : null,
            'end_time' => $end?->toIso8601String(),
            'expected_end_time' => $expectedEnd?->toIso8601String(),
            'is_active' => (bool) $session->is_active,
            'checkout_enabled' => (bool) ($session->checkout_enabled ?? false),
            'updated_at' => $session->updated_at?->toIso8601String(),
        ];
    }
}
