<?php

namespace App\Support;

use App\Models\Course;
use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;

/**
 * Courses visible to a student account (enrolled class only; strict pivot when used).
 */
final class StudentCourseAccess
{
    /**
     * @return Builder<Course>
     */
    public static function coursesQueryForClass(int $classId): Builder
    {
        $query = Course::query();
        if ($classId <= 0) {
            return $query->whereRaw('1 = 0');
        }

        if (SchemaFeatures::hasCourseClassPivot()) {
            return $query->whereHas(
                'schoolClasses',
                fn (Builder $sq) => $sq->where('classes.id', $classId)
            );
        }

        return $query->where('courses.class_id', $classId);
    }

    /**
     * @return Builder<Course>
     */
    public static function coursesQueryForStudent(Student $student): Builder
    {
        return static::coursesQueryForClass((int) ($student->class_id ?? 0));
    }
}
