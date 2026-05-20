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
}
