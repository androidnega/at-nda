<?php

namespace App\Support;

use App\Models\ClassRep;
use App\Models\Course;
use App\Models\Student;
use App\Services\ClassSessionScopeService;

final class RepCourseAccess
{
    public static function canAccessCourse(Student $rep, Course $course): bool
    {
        return $course->overlapsClassIds($rep->repManagedClassIds());
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

    public static function deactivateSessionsForCourse(Course $course): void
    {
        foreach ($course->assignedClassIds() as $classId) {
            ClassSessionScopeService::deactivateActiveSessionsForClass($classId);
        }
    }
}
