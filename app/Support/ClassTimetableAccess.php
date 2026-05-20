<?php

namespace App\Support;

use App\Models\ClassTimetable;
use App\Models\Course;
use App\Models\SchoolClass;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

/**
 * Helpers for reading per-class timetable entries with a safe fallback to the
 * legacy course-level schedule columns (so dashboards never go blank during the
 * transition).
 */
final class ClassTimetableAccess
{
    /**
     * @return EloquentCollection<int, ClassTimetable>
     */
    public static function entriesForClass(int $classId): EloquentCollection
    {
        if ($classId <= 0 || ! SchemaFeatures::hasClassTimetables()) {
            return new EloquentCollection;
        }

        return self::queryForClass($classId)
            ->with(['course', 'lecturer', 'venueRelation'])
            ->orderedForWeek()
            ->get();
    }

    /**
     * @return Builder<ClassTimetable>
     */
    public static function queryForClass(int $classId): Builder
    {
        return ClassTimetable::query()->where('class_id', $classId);
    }

    public static function entryForCourseInClass(int $classId, int $courseId): ?ClassTimetable
    {
        if (! SchemaFeatures::hasClassTimetables() || $classId <= 0 || $courseId <= 0) {
            return null;
        }

        return ClassTimetable::query()
            ->where('class_id', $classId)
            ->where('course_id', $courseId)
            ->orderedForWeek()
            ->with(['lecturer', 'venueRelation'])
            ->first();
    }

    /**
     * Snapshot {day_of_week, start_time, end_time, lecturer_id, venue_id, venue}
     * for the session-opening flow. Returns null when neither the per-class entry
     * nor the legacy course columns provide a valid slot.
     *
     * @return array{day_of_week:string, start_time:string, end_time:string, lecturer_id:?int, venue_id:?int, venue:?string}|null
     */
    public static function resolveScheduleSnapshot(Course $course, ?int $classId): ?array
    {
        if ($classId !== null && $classId > 0) {
            $entry = self::entryForCourseInClass($classId, (int) $course->id);
            if ($entry) {
                return [
                    'day_of_week' => (string) $entry->day_of_week,
                    'start_time' => (string) $entry->start_time,
                    'end_time' => (string) $entry->end_time,
                    'lecturer_id' => $entry->lecturer_id ? (int) $entry->lecturer_id : null,
                    'venue_id' => $entry->venue_id ? (int) $entry->venue_id : null,
                    'venue' => $entry->venue,
                ];
            }
        }

        if (! empty($course->day_of_week) && ! empty($course->start_time) && ! empty($course->end_time)) {
            return [
                'day_of_week' => (string) $course->day_of_week,
                'start_time' => (string) $course->start_time,
                'end_time' => (string) $course->end_time,
                'lecturer_id' => $course->lecturer_id ? (int) $course->lecturer_id : null,
                'venue_id' => $course->venue_id ? (int) $course->venue_id : null,
                'venue' => $course->venue,
            ];
        }

        return null;
    }

    /**
     * True when the class has at least one entry. Used by the dashboard to
     * decide whether to read from per-class rows or fall back to course rows
     * for that one class.
     */
    public static function classHasEntries(int $classId): bool
    {
        if (! SchemaFeatures::hasClassTimetables() || $classId <= 0) {
            return false;
        }

        return ClassTimetable::query()->where('class_id', $classId)->exists();
    }

    /**
     * Courses available to assign to a class's timetable: every course already
     * linked to that class via the course_class pivot or legacy class_id column.
     *
     * @return EloquentCollection<int, Course>
     */
    public static function coursesAssignableToClass(SchoolClass $class): EloquentCollection
    {
        return Course::query()
            ->forManagedClasses([(int) $class->id])
            ->with(['lecturer', 'venueRelation', 'schoolClasses'])
            ->orderBy('course_name')
            ->get();
    }
}
