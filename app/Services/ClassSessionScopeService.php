<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\ClassRep;
use App\Models\Course;

/**
 * Shared rules when opening an attendance session: one active session per class scope
 * and auto-marking class representatives.
 */
class ClassSessionScopeService
{
    /**
     * Deactivate all active sessions for any course belonging to this class.
     */
    public static function deactivateActiveSessionsForClass(?int $classId): void
    {
        if (! $classId) {
            return;
        }

        AttendanceSession::query()
            ->where('is_active', true)
            ->whereHas('course', fn ($q) => $q->where('class_id', (int) $classId))
            ->update(['is_active' => false]);
    }

    /**
     * Insert present attendance for every class rep in this class (idempotent).
     */
    public static function autoMarkClassRepsForSession(AttendanceSession $session, Course $course): void
    {
        if (! $course->class_id) {
            return;
        }

        $repIds = ClassRep::query()
            ->where('class_id', (int) $course->class_id)
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
