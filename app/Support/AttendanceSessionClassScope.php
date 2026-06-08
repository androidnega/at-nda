<?php

namespace App\Support;

use App\Models\Attendance;
use App\Models\AttendanceSession;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

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
     * Accepts either a Builder or a Relation. Eloquent relations
     * (HasMany, BelongsToMany, etc.) delegate where/whereIn/whereHas to
     * their inner query via __call, so the body works on both, but PHP's
     * strict scalar type-hint used to reject the Relation path — which
     * is exactly what Course::activeSessionsForClass() passes in. Caused
     * a hard 500 on the rep dashboard until widened (see laravel.log
     * 2026-06-06 production.ERROR: "Argument #1 ($query) must be of
     * type Builder, HasMany given").
     *
     * @param  Builder<AttendanceSession>|Relation<AttendanceSession, mixed, mixed>  $query
     * @return Builder<AttendanceSession>|Relation<AttendanceSession, mixed, mixed>
     */
    public static function applyForClass(Builder|Relation $query, int $classId): Builder|Relation
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
     * @param  Builder<AttendanceSession>|Relation<AttendanceSession, mixed, mixed>  $query
     * @param  list<int>  $classIds
     * @return Builder<AttendanceSession>|Relation<AttendanceSession, mixed, mixed>
     */
    public static function applyForClasses(Builder|Relation $query, array $classIds): Builder|Relation
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
     * Accepts either a Builder or a Relation. The rep "open student" page
     * passes `$student->attendances()->whereIn(...)->activeWeeksOnly()`
     * which is still a HasMany — Eloquent relations forward
     * where / whereHas / whereRaw through __call so the body works on
     * both, but PHP's strict scalar type-hint used to reject the Relation
     * path and bubble a hard TypeError on production
     * (rep /dashboard/students/{id} 500). Same widening precedent as
     * {@see self::applyForClass()} / {@see self::applyForClasses()}.
     *
     * @param  Builder<Attendance>|Relation<Attendance, mixed, mixed>  $query
     * @param  list<int>  $classIds
     * @return Builder<Attendance>|Relation<Attendance, mixed, mixed>
     */
    public static function scopeAttendanceMarksForClasses(Builder|Relation $query, array $classIds): Builder|Relation
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
