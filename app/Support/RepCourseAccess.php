<?php

namespace App\Support;

use App\Models\Attendance;
use App\Models\ClassRep;
use App\Models\Course;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\ClassSessionScopeService;
use App\Support\SchemaFeatures;
use Illuminate\Database\Eloquent\Builder;

final class RepCourseAccess
{
    /**
     * Class IDs this course is assigned to (pivot when present; legacy class_id otherwise).
     *
     * @return list<int>
     */
    public static function courseClassIdsForAccess(Course $course): array
    {
        if (SchemaFeatures::hasCourseClassPivot()) {
            $fromPivot = $course->relationLoaded('schoolClasses')
                ? $course->schoolClasses->pluck('id')->map(fn ($id) => (int) $id)->all()
                : $course->schoolClasses()->pluck('classes.id')->map(fn ($id) => (int) $id)->all();
            if ($fromPivot !== []) {
                return array_values(array_unique($fromPivot));
            }
        }

        return $course->assignedClassIds();
    }

    public static function canAccessCourse(Student $rep, Course $course): bool
    {
        $managed = $rep->repManagedClassIds()->map(fn ($id) => (int) $id)->all();
        $assigned = self::courseClassIdsForAccess($course);

        return array_intersect($managed, $assigned) !== [];
    }

    /**
     * Courses a rep may list or open (strict: pivot classes only when pivot is used).
     *
     * @return Builder<Course>
     */
    public static function coursesQueryForRep(Student $rep): Builder
    {
        $classIds = $rep->repManagedClassIds()
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $query = Course::query();
        if ($classIds === []) {
            return $query->whereRaw('1 = 0');
        }

        if (SchemaFeatures::hasCourseClassPivot()) {
            return $query->whereHas(
                'schoolClasses',
                fn (Builder $sq) => $sq->whereIn('classes.id', $classIds)
            );
        }

        return $query->whereIn('courses.class_id', $classIds);
    }

    /**
     * Class IDs this rep may see for a given course (intersection of rep classes and course classes).
     *
     * @return list<int>
     */
    public static function scopedClassIdsForCourse(Student $rep, Course $course): array
    {
        $managed = $rep->repManagedClassIds()->map(fn ($id) => (int) $id)->all();
        $assigned = self::courseClassIdsForAccess($course);

        return array_values(array_intersect($managed, $assigned));
    }

    public static function repClassLabelForCourse(Student $rep, Course $course): string
    {
        $scoped = self::scopedClassIdsForCourse($rep, $course);
        if ($scoped === []) {
            return '—';
        }

        if ($course->relationLoaded('schoolClasses')) {
            $label = $course->schoolClasses
                ->whereIn('id', $scoped)
                ->pluck('name')
                ->unique()
                ->join(', ');

            return $label !== '' ? $label : '—';
        }

        return SchoolClass::query()
            ->whereIn('id', $scoped)
            ->orderBy('name')
            ->pluck('name')
            ->join(', ') ?: '—';
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
        foreach (self::scopedClassIdsForCourse($rep, $course) as $classId) {
            $cr = $rep->classReps()->where('class_id', $classId)->first();
            if ($cr) {
                return $cr;
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
            ClassSessionScopeService::deactivateActiveSessionsForClass($classId, (int) $course->id);
        }
    }
}
