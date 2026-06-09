<?php

namespace App\Support;

use App\Models\Course;
use App\Models\Lecturer;
use App\Models\SchoolClass;
use Illuminate\Http\Request;

final class LecturerAccess
{
    /**
     * Routes a lecturer session may use (admins are unrestricted).
     *
     * @return list<string>
     */
    public static function allowedRouteNames(): array
    {
        return [
            'dashboard.dashboard',
            'dashboard.students.index',
            'dashboard.students.show',
            'dashboard.students.store',
            'dashboard.students.import',
            'dashboard.students.reset-password',
            'dashboard.classes.show',
            'dashboard.classes.students.store',
            'dashboard.classes.students.import',
            'dashboard.my-classes.index',
            'dashboard.pdf.export',
            'dashboard.teaching.attendance.index',
            'dashboard.teaching.attendance.course',
            'dashboard.teaching.attendance.course.pdf',
            'dashboard.teaching.attendance.course.export-json',
            'dashboard.teaching.attendance.course.import-json',
            'lecturer.courses.week.cancel',
            'lecturer.courses.week.uncancel',
        ];
    }

    public static function routeAllowedForLecturer(?string $routeName): bool
    {
        if ($routeName === null || $routeName === '') {
            return false;
        }

        return in_array($routeName, self::allowedRouteNames(), true);
    }

    public static function lecturerFromSession(Request $request): ?Lecturer
    {
        $id = $request->session()->get('lecturer_id');
        if (! $id || $request->session()->has('admin_id')) {
            return null;
        }

        return Lecturer::find($id);
    }

    public static function canAccessClass(Lecturer $lecturer, SchoolClass|int $class): bool
    {
        $classId = $class instanceof SchoolClass ? (int) $class->id : (int) $class;

        return $lecturer->assignedClassIds()->contains($classId);
    }

    public static function canManageCourse(Lecturer $lecturer, Course $course): bool
    {
        return $lecturer->managesCourse($course);
    }
}
