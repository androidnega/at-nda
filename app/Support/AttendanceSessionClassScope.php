<?php

namespace App\Support;

use App\Models\Attendance;
use App\Models\AttendanceSession;
use Illuminate\Database\Eloquent\Builder;

/**
 * Tie live sessions and attendance marks to a single class cohort when courses are shared.
 */
final class AttendanceSessionClassScope
{
    public static function hasSessionClassId(): bool
    {
        return SchemaFeatures::hasAttendanceSessionsClassId();
    }

    /**
     * @param  Builder<AttendanceSession>  $query
     * @return Builder<AttendanceSession>
     */
    public static function applyForClass(Builder $query, int $classId): Builder
    {
        if ($classId <= 0) {
            return $query->whereRaw('1 = 0');
        }

        if (self::hasSessionClassId()) {
            return $query->where('attendance_sessions.class_id', $classId);
        }

        if (SchemaFeatures::hasAttendanceWeeksClassId()) {
            return $query->whereHas(
                'attendanceWeek',
                fn (Builder $w) => $w->where('class_id', $classId)
            );
        }

        return $query->whereHas('course', fn (Builder $c) => $c->where('class_id', $classId));
    }

    /**
     * @param  Builder<AttendanceSession>  $query
     * @param  list<int>  $classIds
     * @return Builder<AttendanceSession>
     */
    public static function applyForClasses(Builder $query, array $classIds): Builder
    {
        $ids = collect($classIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            return $query->whereRaw('1 = 0');
        }

        if (self::hasSessionClassId()) {
            return $query->whereIn('attendance_sessions.class_id', $ids);
        }

        if (SchemaFeatures::hasAttendanceWeeksClassId()) {
            return $query->whereHas(
                'attendanceWeek',
                fn (Builder $w) => $w->whereIn('class_id', $ids)
            );
        }

        return $query->whereHas('course', fn (Builder $c) => $c->whereIn('class_id', $ids));
    }

    public static function sessionBelongsToClass(AttendanceSession $session, int $classId): bool
    {
        if ($classId <= 0) {
            return false;
        }

        if (self::hasSessionClassId() && $session->class_id !== null) {
            return (int) $session->class_id === $classId;
        }

        if (SchemaFeatures::hasAttendanceWeeksClassId()) {
            $session->loadMissing('attendanceWeek');
            if ($session->attendanceWeek?->class_id !== null) {
                return (int) $session->attendanceWeek->class_id === $classId;
            }
        }

        $session->loadMissing('course');

        return $session->course !== null && (int) $session->course->class_id === $classId;
    }

    /**
     * Rep dashboard counts: only marks from sessions opened for the rep's class(es).
     *
     * @param  Builder<Attendance>  $query
     * @param  list<int>  $classIds
     * @return Builder<Attendance>
     */
    public static function scopeAttendanceMarksForClasses(Builder $query, array $classIds): Builder
    {
        $ids = collect($classIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $q) use ($ids) {
            $q->whereHas('attendanceSession', function (Builder $s) use ($ids) {
                self::applyForClasses($s, $ids);
            })->orWhere(function (Builder $q2) use ($ids) {
                $q2->whereNull('attendance_session_id')
                    ->whereHas('student', fn (Builder $st) => $st->whereIn('class_id', $ids));
            });
        });
    }
}
