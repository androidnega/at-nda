<?php

namespace App\Support;

use App\Models\Attendance;
use App\Models\ClassRep;
use App\Models\Course;
use App\Models\Student;
use App\Services\ClassSessionScopeService;
use Illuminate\Database\Eloquent\Builder;

final class RepCourseAccess
{
    public static function canAccessCourse(Student $rep, Course $course): bool
    {
        return $course->overlapsClassIds($rep->repManagedClassIds());
    }

    /**
     * Class IDs this rep may see for a given course (intersection of rep classes and course classes).
     *
     * @return list<int>
     */
    public static function scopedClassIdsForCourse(Student $rep, Course $course): array
    {
        $managed = $rep->repManagedClassIds()->map(fn ($id) => (int) $id)->all();
        $assigned = $course->assignedClassIds();

        return array_values(array_intersect($managed, $assigned));
    }

    /**
     * @param  Builder<Attendance>  $query
     * @return Builder<Attendance>
     */
    public static function scopeAttendanceForRep(Builder $query, Student $rep, Course $course): Builder
    {
        $classIds = self::scopedClassIdsForCourse($rep, $course);
        if ($classIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query
            ->where('course_id', $course->id)
            ->whereHas('student', fn (Builder $s) => $s->whereIn('class_id', $classIds));
    }

    public static function classRepForCourse(Student $rep, Course $course): ?ClassRep
    {
        $managed = $rep->repManagedClassIds();
        foreach ($course->assignedClassIds() as $classId) {
            if ($managed->contains($classId)) {
                return $rep->classReps()->where('class_id', $classId)->first();
            }
        }

        return null;
    }

    public static function isMainRepForCourse(Student $rep, Course $course): bool
    {
        $cr = self::classRepForCourse($rep, $course);

        return $cr?->isMainRep() ?? false;
    }

    public static function deactivateSessionsForCourse(Student $rep, Course $course): void
    {
        foreach (self::scopedClassIdsForCourse($rep, $course) as $classId) {
            ClassSessionScopeService::deactivateActiveSessionsForClass($classId);
        }
    }
}
