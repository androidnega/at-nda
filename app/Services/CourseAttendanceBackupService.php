<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\Course;
use App\Models\Student;
use Illuminate\Support\Facades\DB;

final class CourseAttendanceBackupService
{
    /**
     * @return array<string, mixed>
     */
    public function buildExportPayload(Course $course): array
    {
        $records = Attendance::query()
            ->where('course_id', $course->id)
            ->with([
                'student:id,index_number',
                'attendanceSession:id,session_index,course_id',
                'attendanceWeek:id,week_number',
            ])
            ->orderBy('id')
            ->get()
            ->map(function (Attendance $a) {
                return [
                    'id' => $a->id,
                    'student_id' => $a->student_id,
                    'index_number' => $a->student?->index_number,
                    'course_id' => $a->course_id,
                    'attendance_session_id' => $a->attendance_session_id,
                    'attendance_week_id' => $a->attendance_week_id,
                    'session_index' => $a->attendanceSession?->session_index,
                    'week_number' => $a->attendanceWeek?->week_number,
                    'attendance_time' => $a->attendance_time?->toIso8601String(),
                    'status' => $a->status,
                    'synced' => (bool) $a->synced,
                    'lat' => $a->lat !== null ? (float) $a->lat : null,
                    'lng' => $a->lng !== null ? (float) $a->lng : null,
                ];
            });

        return [
            'format' => 'at-nda-attendance-backup',
            'version' => 1,
            'exported_at' => now()->toIso8601String(),
            'course_id' => $course->id,
            'course_code' => $course->course_code,
            'course_name' => $course->course_name,
            'records' => $records,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{imported: int, skipped: int}
     */
    public function importFromPayload(Course $course, array $data): array
    {
        if (($data['format'] ?? '') !== 'at-nda-attendance-backup') {
            throw new \InvalidArgumentException('Invalid backup file (expected at-nda-attendance-backup JSON).');
        }

        if ((int) ($data['course_id'] ?? 0) !== (int) $course->id) {
            throw new \InvalidArgumentException('This backup is for a different course.');
        }

        $rows = $data['records'] ?? null;
        if (! is_array($rows) || $rows === []) {
            throw new \InvalidArgumentException('No records in file.');
        }

        $imported = 0;
        $skipped = 0;

        DB::transaction(function () use ($course, $rows, &$imported, &$skipped) {
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    $skipped++;

                    continue;
                }

                $studentId = isset($row['student_id']) ? (int) $row['student_id'] : 0;
                if ($studentId <= 0 && ! empty($row['index_number'])) {
                    $byIndex = Student::findByIndex((string) $row['index_number']);
                    $studentId = $byIndex?->id ?? 0;
                }

                $sessionId = isset($row['attendance_session_id']) ? (int) $row['attendance_session_id'] : 0;
                if ($studentId <= 0 || $sessionId <= 0) {
                    $skipped++;

                    continue;
                }

                $session = AttendanceSession::query()->find($sessionId);
                if (! $session || (int) $session->course_id !== (int) $course->id) {
                    $skipped++;

                    continue;
                }

                $student = Student::query()->find($studentId);
                if (! $student || ! $course->isAssignedToClass((int) $student->class_id)) {
                    $skipped++;

                    continue;
                }

                $weekId = isset($row['attendance_week_id']) ? (int) $row['attendance_week_id'] : (int) $session->attendance_week_id;
                if ($weekId <= 0) {
                    $skipped++;

                    continue;
                }

                Attendance::updateOrCreate(
                    [
                        'student_id' => $student->id,
                        'attendance_session_id' => $session->id,
                    ],
                    [
                        'course_id' => $course->id,
                        'attendance_week_id' => $weekId,
                        'attendance_time' => $row['attendance_time'] ?? now(),
                        'status' => $row['status'] ?? 'present',
                        'synced' => (bool) ($row['synced'] ?? true),
                        'lat' => $row['lat'] ?? null,
                        'lng' => $row['lng'] ?? null,
                    ]
                );
                $imported++;
            }
        });

        return ['imported' => $imported, 'skipped' => $skipped];
    }
}
