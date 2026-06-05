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

    private static ?bool $attendancesUserAgent = null;

    private static ?bool $studentsEmail = null;

    private static ?bool $mailSettings = null;

    private static ?bool $passwordResetCodes = null;

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

    public static function hasAttendancesUserAgent(): bool
    {
        return self::$attendancesUserAgent ??= (
            Schema::hasTable('attendances') && Schema::hasColumn('attendances', 'user_agent')
        );
    }

    public static function hasStudentsEmail(): bool
    {
        return self::$studentsEmail ??= (
            Schema::hasTable('students') && Schema::hasColumn('students', 'email')
        );
    }

    public static function hasMailSettings(): bool
    {
        return self::$mailSettings ??= (
            Schema::hasTable('system_settings')
            && Schema::hasColumn('system_settings', 'mail_host')
            && Schema::hasColumn('system_settings', 'mail_password_encrypted')
        );
    }

    public static function hasPasswordResetCodes(): bool
    {
        return self::$passwordResetCodes ??= Schema::hasTable('password_reset_codes');
    }
}
