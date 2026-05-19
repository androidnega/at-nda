<?php

namespace App\Support;

use App\Models\AttendanceSession;
use App\Models\Student;
use Illuminate\Support\Collection;

/**
 * Block student/class-rep web sign-out while a class session is still in progress.
 */
class StudentSignOutLock
{
    public static function activeSessionsForStudent(Student $student): Collection
    {
        if (! $student->class_id) {
            return collect();
        }

        AttendanceSession::deactivateExpiredSessions();

        return AttendanceSession::query()
            ->with(['course', 'attendanceWeek'])
            ->whereHas('course', fn ($q) => $q->forManagedClasses([$student->class_id]))
            ->activeWithinTimeWindow()
            ->where(function ($q) {
                $q->whereNull('attendance_week_id')
                    ->orWhereHas('attendanceWeek', fn ($w) => $w->whereNull('cancelled_at'));
            })
            ->orderBy('start_time')
            ->get();
    }

    public static function isSignOutBlocked(Student $student): bool
    {
        return static::activeSessionsForStudent($student)->isNotEmpty();
    }

    public static function blockMessage(Student $student): ?string
    {
        $sessions = static::activeSessionsForStudent($student);
        if ($sessions->isEmpty()) {
            return null;
        }

        $names = $sessions
            ->map(fn (AttendanceSession $s) => $s->course?->course_name)
            ->filter()
            ->unique()
            ->values();

        if ($names->count() === 1) {
            return 'Sign out is unavailable while '.$names->first().' is in session. You can log out after the class ends.';
        }

        return 'Sign out is unavailable while a class is in session ('.$names->take(3)->implode(', ').'). You can log out after class ends.';
    }
}
