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

    private static ?bool $classesSemesterWeeks = null;

    private static ?bool $classesSemesterDates = null;

    private static ?bool $coursesQualification = null;

    private static ?bool $attendancesUserAgent = null;

    private static ?bool $studentsEmail = null;

    private static ?bool $mailSettings = null;

    private static ?bool $passwordResetCodes = null;

    private static ?bool $attendancesManualMark = null;

    private static ?bool $systemSettingsAllowRepDeletion = null;

    private static ?bool $auditLogs = null;

    private static ?bool $studentActiveSessions = null;

    private static ?bool $redisSettings = null;

    private static ?bool $attendancesDeviceFingerprint = null;

    private static ?bool $attendancesLecturerManualMark = null;

    private static ?bool $attendanceSessionsMeetingFields = null;

    private static ?bool $attendancesRiskColumns = null;

    private static ?bool $onlineSessionCodes = null;

    private static ?bool $attendanceDeviceLogs = null;

    private static ?bool $attendancesDistanceFromAnchor = null;

    private static ?bool $attendanceSessionSummaries = null;

    private static ?bool $attendanceUuid = null;

    private static ?bool $attendanceLateUnrecorded = null;

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

    public static function hasClassesSemesterWeeks(): bool
    {
        return self::$classesSemesterWeeks ??= (
            Schema::hasTable('classes') && Schema::hasColumn('classes', 'semester_weeks')
        );
    }

    /**
     * Whether the calendar-based "where in the semester are we?"
     * columns are present (start date, end date, manual override).
     * All three were introduced in the same migration so we test
     * the cheapest one as a proxy.
     */
    public static function hasClassesSemesterDates(): bool
    {
        return self::$classesSemesterDates ??= (
            Schema::hasTable('classes')
            && Schema::hasColumn('classes', 'semester_start_date')
            && Schema::hasColumn('classes', 'current_week_override')
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

    public static function hasAttendancesManualMark(): bool
    {
        return self::$attendancesManualMark ??= (
            Schema::hasTable('attendances') && Schema::hasColumn('attendances', 'marked_manually_by_id')
        );
    }

    public static function hasAllowRepDeletionSetting(): bool
    {
        return self::$systemSettingsAllowRepDeletion ??= (
            Schema::hasTable('system_settings') && Schema::hasColumn('system_settings', 'allow_rep_attendance_deletion')
        );
    }

    public static function hasAuditLogs(): bool
    {
        return self::$auditLogs ??= Schema::hasTable('audit_logs');
    }

    public static function hasStudentActiveSessions(): bool
    {
        return self::$studentActiveSessions ??= Schema::hasTable('student_active_sessions');
    }

    public static function hasRedisSettings(): bool
    {
        return self::$redisSettings ??= (
            Schema::hasTable('system_settings')
            && Schema::hasColumn('system_settings', 'cache_driver')
            && Schema::hasColumn('system_settings', 'redis_host')
        );
    }

    public static function hasAttendancesDeviceFingerprint(): bool
    {
        return self::$attendancesDeviceFingerprint ??= (
            Schema::hasTable('attendances')
            && Schema::hasColumn('attendances', 'device_fingerprint')
        );
    }

    public static function hasAttendancesLecturerManualMark(): bool
    {
        return self::$attendancesLecturerManualMark ??= (
            Schema::hasTable('attendances')
            && Schema::hasColumn('attendances', 'marked_manually_by_lecturer_id')
        );
    }

    public static function hasAttendanceSessionsMeetingFields(): bool
    {
        return self::$attendanceSessionsMeetingFields ??= (
            Schema::hasTable('attendance_sessions')
            && Schema::hasColumn('attendance_sessions', 'meeting_platform')
        );
    }

    public static function hasAttendancesRiskColumns(): bool
    {
        return self::$attendancesRiskColumns ??= (
            Schema::hasTable('attendances')
            && Schema::hasColumn('attendances', 'risk_score')
        );
    }

    public static function hasOnlineSessionCodes(): bool
    {
        return self::$onlineSessionCodes ??= Schema::hasTable('online_session_codes');
    }

    public static function hasAttendanceDeviceLogs(): bool
    {
        return self::$attendanceDeviceLogs ??= Schema::hasTable('attendance_device_logs');
    }

    public static function hasAttendancesDistanceFromAnchor(): bool
    {
        return self::$attendancesDistanceFromAnchor ??= (
            Schema::hasTable('attendances')
            && Schema::hasColumn('attendances', 'distance_from_anchor')
        );
    }

    public static function hasAttendanceSessionSummaries(): bool
    {
        return self::$attendanceSessionSummaries ??= Schema::hasTable('attendance_session_summaries');
    }

    public static function hasAttendanceUuid(): bool
    {
        return self::$attendanceUuid ??= (
            Schema::hasTable('attendances')
            && Schema::hasColumn('attendances', 'attendance_uuid')
        );
    }

    public static function hasAttendanceLateUnrecorded(): bool
    {
        return self::$attendanceLateUnrecorded ??= Schema::hasTable('attendance_late_unrecorded');
    }
}
