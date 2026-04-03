<?php

namespace App\Support;

use App\Models\Student;
use App\Models\SystemSetting;

/**
 * Consistent student profile JSON for mobile (login, profile update, /me).
 */
class StudentApiPayload
{
    /**
     * Global attendance cache invalidation (increments when admin clears weeks/sessions on the server).
     * Mobile should persist this and refetch GET /api/attendance/sync when it changes.
     *
     * @return array{attendance_data_version: int, last_attendance_reset_at: string|null}
     */
    public static function attendanceSyncMeta(): array
    {
        $s = SystemSetting::get();

        return [
            'attendance_data_version' => (int) ($s->attendance_data_version ?? 0),
            'last_attendance_reset_at' => $s->last_attendance_reset_at?->toIso8601String(),
        ];
    }

    /**
     * Full user object: name, student_id, profile_picture URL, updated_at, etc.
     */
    public static function forUser(Student $student): array
    {
        $student->loadMissing(['schoolClass.semester']);
        $class = $student->schoolClass;
        $dept = $student->department ?? $class?->department;
        $faculty = $dept?->faculty ?? $class?->faculty;

        $photoUrl = $student->profileImageUrl();
        $name = $student->getDisplayName();
        if ($name === '') {
            $name = (string) ($student->index_number ?? '');
        }

        // Course reps are off-system; only class reps are supported for API roles.
        $student->loadMissing(['classReps']);
        $isClassRep = $student->isClassRep();
        $repRoles = $isClassRep ? $student->apiRepRoleRows() : [];

        return array_merge([
            'id' => $student->id,
            'name' => $name,
            'student_id' => $student->index_number,
            'index_number' => $student->index_number,
            'first_name' => $student->first_name,
            'middle_name' => $student->middle_name,
            'last_name' => $student->last_name,
            'profile_picture' => $photoUrl,
            'profile_image' => $photoUrl,
            'profile_image_url' => $photoUrl,
            'class_name' => $class?->name ?? null,
            'faculty' => $faculty?->name ?? null,
            'department' => $dept?->name ?? null,
            'level' => $class?->level ?? null,
            'semester' => $class?->semester?->display_label ?? null,
            'phone_number' => $student->phone_number,
            'updated_at' => $student->updated_at?->toIso8601String(),
            'weekly_timetable' => $student->weeklyTimetableSummary(),
            'is_class_rep' => $isClassRep,
            'primary_role' => $isClassRep ? 'class_rep' : 'student',
            'rep_roles' => $repRoles,
        ], self::attendanceSyncMeta());
    }
}
