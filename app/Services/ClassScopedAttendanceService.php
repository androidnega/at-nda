<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\AttendanceWeek;
use App\Models\Course;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Support\AttendanceSessionClassScope;
use App\Support\SchemaFeatures;
use Illuminate\Support\Collection;

/**
 * Attendance roll-ups for a single class cohort (admin class detail).
 */
class ClassScopedAttendanceService
{
    /**
     * @return Collection<int, array{
     *     course: Course,
     *     mark_count: int,
     *     held_weeks: int,
     *     enrolled: int,
     * }>
     */
    public function coursesOverview(SchoolClass $schoolClass): Collection
    {
        $classId = (int) $schoolClass->id;
        $enrolled = (int) $schoolClass->students()->count();

        return Course::query()
            ->forManagedClasses([$classId])
            ->orderBy('course_name')
            ->get()
            ->map(function (Course $course) use ($classId, $enrolled): array {
                $weekIds = $this->weeksForClass($course, $classId)->pluck('id')->all();
                $marksQuery = Attendance::query()
                    ->where('course_id', $course->id)
                    ->activeWeeksOnly()
                    ->countedAsPresent();
                if ($weekIds !== []) {
                    $marksQuery->whereIn('attendance_week_id', $weekIds);
                }
                AttendanceSessionClassScope::scopeAttendanceMarksForClasses($marksQuery, [$classId]);

                return [
                    'course' => $course,
                    'mark_count' => (int) $marksQuery->count(),
                    'held_weeks' => count($weekIds),
                    'enrolled' => $enrolled,
                ];
            });
    }

    /**
     * @return array{
     *     attendanceWeeks: Collection<int, AttendanceWeek>,
     *     weeklyAttendees: Collection<int, array<string, mixed>>,
     *     enrolledCount: int,
     *     enrolledStudents: Collection<int, Student>,
     * }
     */
    public function courseDetail(SchoolClass $schoolClass, Course $course): array
    {
        $classId = (int) $schoolClass->id;
        if (! $course->isAssignedToClass($classId)) {
            abort(404);
        }

        $students = $schoolClass->students()
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
        $enrolledCount = $students->count();

        $attendanceWeeks = $this->weeksForClass($course, $classId);
        $weekIds = $attendanceWeeks->pluck('id')->all();

        $marksByWeek = $weekIds === []
            ? collect()
            : (function () use ($course, $weekIds, $classId) {
                $marksQuery = Attendance::query()
                    ->where('course_id', $course->id)
                    ->whereIn('attendance_week_id', $weekIds)
                    ->activeWeeksOnly();
                AttendanceSessionClassScope::scopeAttendanceMarksForClasses($marksQuery, [$classId]);

                return $marksQuery
                    ->get(['student_id', 'attendance_week_id', 'attendance_time', 'status'])
                    ->groupBy('attendance_week_id');
            })();

        $weeklyAttendees = $attendanceWeeks->map(function (AttendanceWeek $week) use ($marksByWeek, $students): array {
            $marks = $marksByWeek->get((int) $week->id, collect());
            $presentMarks = $marks->filter(fn ($m) => Attendance::countsAsPresent($m->status));
            $presentIds = $presentMarks->pluck('student_id')->unique()->map(fn ($id) => (int) $id);
            $presentSet = $presentIds->flip();
            $latestByStudent = $presentMarks->groupBy(fn ($m) => (int) $m->student_id)
                ->map(fn ($rows) => $rows->sortByDesc('attendance_time')->first());

            $present = $students->filter(fn (Student $s) => $presentSet->has((int) $s->id))
                ->values()
                ->map(fn (Student $s) => [
                    'student' => $s,
                    'time' => optional($latestByStudent[(int) $s->id] ?? null)->attendance_time,
                ]);

            $absent = $students->reject(fn (Student $s) => $presentSet->has((int) $s->id))->values();

            return [
                'week' => $week,
                'present' => $present,
                'absent' => $absent,
                'present_count' => $present->count(),
                'absent_count' => $absent->count(),
                'present_ids' => $presentSet,
            ];
        });

        return [
            'attendanceWeeks' => $attendanceWeeks,
            'weeklyAttendees' => $weeklyAttendees,
            'enrolledCount' => $enrolledCount,
            'enrolledStudents' => $students,
        ];
    }

    /**
     * @return Collection<int, AttendanceWeek>
     */
    private function weeksForClass(Course $course, int $classId): Collection
    {
        $weeksQuery = $course->attendanceWeeks()->orderBy('week_number');
        if (SchemaFeatures::hasAttendanceWeeksClassId()) {
            $weeksQuery->where(function ($q) use ($classId) {
                $q->where('class_id', $classId)->orWhereNull('class_id');
            });
        }

        $allWeeks = $weeksQuery->get();
        if ($allWeeks->isEmpty()) {
            return $allWeeks;
        }

        $usedWeekIds = \App\Models\AttendanceSession::query()
            ->where('course_id', $course->id)
            ->whereIn('attendance_week_id', $allWeeks->pluck('id'))
            ->tap(fn ($q) => AttendanceSessionClassScope::applyForClass($q, $classId))
            ->pluck('attendance_week_id')
            ->map(fn ($v) => (int) $v)
            ->unique()
            ->flip();

        return $allWeeks->filter(function (AttendanceWeek $w) use ($usedWeekIds) {
            if ($w->isCancelled()) {
                return true;
            }

            return $usedWeekIds->has((int) $w->id);
        })->values();
    }
}
