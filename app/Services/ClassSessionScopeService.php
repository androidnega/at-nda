<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\ClassRep;
use App\Models\Course;
use App\Support\AttendanceSessionClassScope;

/**
 * Shared rules when opening an attendance session: one active session per class scope
 * and auto-marking class representatives.
 */
class ClassSessionScopeService
{
    /**
     * Deactivate active sessions for a class (optionally limited to one course).
     */
    public static function deactivateActiveSessionsForClass(?int $classId, ?int $courseId = null): void
    {
        if (! $classId) {
            return;
        }

        $query = AttendanceSession::query()->where('is_active', true);

        if ($courseId !== null && $courseId > 0) {
            $query->where('course_id', $courseId);
        }

        AttendanceSessionClassScope::applyForClass($query, (int) $classId);

        $query->update(['is_active' => false]);
    }

    /**
     * Insert present attendance for class reps in the opening class only (idempotent).
     */
    public static function autoMarkClassRepsForSession(AttendanceSession $session, Course $course, ?int $classId = null): void
    {
        $classId = $classId ?? (int) ($session->class_id ?? 0);
        if ($classId <= 0) {
            $session->loadMissing('attendanceWeek');
            $classId = (int) ($session->attendanceWeek?->class_id ?? 0);
        }
        if ($classId <= 0) {
            return;
        }

        $repIds = ClassRep::query()
            ->where('class_id', $classId)
            ->pluck('student_id')
            ->unique()
            ->values();

        if ($repIds->isEmpty()) {
            return;
        }

        foreach ($repIds as $repId) {
            Attendance::firstOrCreate(
                [
                    'student_id' => (int) $repId,
                    'attendance_session_id' => $session->id,
                ],
                [
                    'course_id' => $course->id,
                    'attendance_week_id' => $session->attendance_week_id,
                    'attendance_time' => now(),
                    'status' => 'present',
                    'synced' => true,
                ]
            );
        }
    }
}
