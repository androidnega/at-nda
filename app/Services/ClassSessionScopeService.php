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
     * Sessions that had nobody marked are deleted outright so the student
     * history doesn't list a "missed class" for each open/close cycle.
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

        // Pull the rows so we can split "had attendance" from "empty" and
        // act on each set differently. The set is small (at most a handful
        // of active sessions per class at any moment).
        $sessions = (clone $query)->get();
        if ($sessions->isEmpty()) {
            return;
        }

        $emptyIds = [];
        $keepIds = [];
        foreach ($sessions as $s) {
            $hasAttendance = Attendance::query()
                ->where('attendance_session_id', $s->id)
                ->exists();
            if ($hasAttendance) {
                $keepIds[] = (int) $s->id;
            } else {
                $emptyIds[] = (int) $s->id;
            }
        }

        if ($emptyIds !== []) {
            AttendanceSession::query()->whereIn('id', $emptyIds)->delete();
        }
        if ($keepIds !== []) {
            AttendanceSession::query()
                ->whereIn('id', $keepIds)
                ->update(['is_active' => false]);
        }

        \App\Support\LiveAttendanceCache::bump();
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
