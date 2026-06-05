<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;

/**
 * Cached checks for optional schema introduced after initial deploy.
 */
final class SchemaFeatures
{
    private static ?bool $classLecturerPivot = null;

    private static ?bool $courseClassPivot = null;

    private static ?bool $classTimetables = null;

    private static ?bool $attendanceWeeksClassId = null;

    private static ?bool $attendanceSessionsClassId = null;

    private static ?bool $classesQualification = null;

    private static ?bool $coursesQualification = null;

    public static function hasClassLecturerPivot(): bool
    {
        return self::$classLecturerPivot ??= Schema::hasTable('class_lecturer');
    }

    public static function hasCourseClassPivot(): bool
    {
        return self::$courseClassPivot ??= Schema::hasTable('course_class');
    }

    public static function hasClassTimetables(): bool
    {
        return self::$classTimetables ??= Schema::hasTable('class_timetables');
    }

    public static function hasAttendanceWeeksClassId(): bool
    {
        return self::$attendanceWeeksClassId ??= (
            Schema::hasTable('attendance_weeks') && Schema::hasColumn('attendance_weeks', 'class_id')
        );
    }

    public static function hasAttendanceSessionsClassId(): bool
    {
        return self::$attendanceSessionsClassId ??= (
            Schema::hasTable('attendance_sessions') && Schema::hasColumn('attendance_sessions', 'class_id')
        );
    }

    public static function hasClassesQualification(): bool
    {
        return self::$classesQualification ??= (
            Schema::hasTable('classes') && Schema::hasColumn('classes', 'qualification')
        );
    }

    public static function hasCoursesQualification(): bool
    {
        return self::$coursesQualification ??= (
            Schema::hasTable('courses') && Schema::hasColumn('courses', 'qualification')
        );
    }
}
