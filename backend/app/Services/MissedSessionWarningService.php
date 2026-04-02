<?php

namespace App\Services;

use App\Models\AttendanceSession;
use App\Models\Course;
use App\Models\Student;

/**
 * Ended sessions (per class-linked courses) where the student has no attendance → "missed".
 */
class MissedSessionWarningService
{
    /**
     * Courses where the student has at least $minimumMissed ended sessions without attendance.
     *
     * @return array{warnings: list<array{course_id: int, course_name: string, course_code: string, missed_count: int}>, warnings_map: array<string, int>}
     */
    public static function buildPayload(
        Student $student,
        ?int $minimumMissed = null,
        ?int $lookbackDays = null
    ): array {
        $minimumMissed = $minimumMissed ?? (int) config('attendance.missed_warning_min_sessions', 2);
        if ($minimumMissed < 1) {
            $minimumMissed = 2;
        }

        if ($lookbackDays === null && config('attendance.missed_warning_lookback_days') !== null) {
            $lookbackDays = (int) config('attendance.missed_warning_lookback_days');
        }

        if (! $student->class_id) {
            return [
                'warnings' => [],
                'warnings_map' => [],
            ];
        }

        $query = AttendanceSession::query()
            ->whereHas('course', fn ($q) => $q->where('class_id', $student->class_id))
            ->ended()
            ->whereDoesntHave('attendances', fn ($q) => $q->where('student_id', $student->id));

        if ($lookbackDays !== null && $lookbackDays > 0) {
            $query->whereRaw('COALESCE(attendance_sessions.end_time, attendance_sessions.expires_at) >= ?', [
                now()->subDays($lookbackDays),
            ]);
        }

        $rows = $query
            ->selectRaw('attendance_sessions.course_id, COUNT(*) as missed_count')
            ->groupBy('attendance_sessions.course_id')
            ->havingRaw('COUNT(*) >= ?', [$minimumMissed])
            ->get();

        $warnings = [];
        $warningsMap = [];

        foreach ($rows as $row) {
            $courseId = (int) $row->course_id;
            $count = (int) $row->missed_count;
            $course = Course::query()->select(['id', 'course_name', 'course_code'])->find($courseId);

            $warnings[] = [
                'course_id' => $courseId,
                'course_name' => $course->course_name ?? '',
                'course_code' => $course->course_code ?? '',
                'missed_count' => $count,
            ];
            $warningsMap[(string) $courseId] = $count;
        }

        return [
            'warnings' => $warnings,
            'warnings_map' => $warningsMap,
        ];
    }
}
