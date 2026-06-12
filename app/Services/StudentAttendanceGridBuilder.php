<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\AttendanceWeek;
use App\Models\Course;
use App\Models\Student;
use App\Support\SchemaFeatures;
use Illuminate\Support\Collection;

/**
 * Builds the week-by-week attendance grid for a single student across
 * every course in their class. Drives the mobile-app "attendance
 * table" page and the per-student PDF export.
 */
class StudentAttendanceGridBuilder
{
    /**
     * @return array{
     *     student: array<string, mixed>,
     *     courses: list<array<string, mixed>>,
     *     summary: array<string, int>,
     *     generated_at: string,
     * }
     */
    public function build(Student $student): array
    {
        $student->loadMissing(['schoolClass.faculty.university', 'schoolClass.department']);

        $courses = collect();
        $classId = (int) ($student->class_id ?? 0);
        if ($classId > 0) {
            $courses = Course::query()
                ->forManagedClasses([$classId])
                ->with(['lecturer:id,name', 'venueRelation:id,name'])
                ->orderBy('course_name')
                ->get();
        }

        $coursePayload = [];
        $totalHeld = 0;
        $totalPresent = 0;
        $totalCancelled = 0;
        foreach ($courses as $course) {
            $row = $this->buildCourseRow($student, $course, $classId);
            $coursePayload[] = $row;
            $totalHeld += (int) $row['held_count'];
            $totalPresent += (int) $row['present_count'];
            $totalCancelled += (int) $row['cancelled_count'];
        }

        $faculty = $student->effectiveFaculty();
        $department = $student->effectiveDepartment();

        return [
            'student' => [
                'id' => (int) $student->id,
                'name' => $student->getDisplayNameOrIndex(),
                'index_number' => (string) $student->index_number,
                'class_id' => $classId ?: null,
                'class_name' => $student->schoolClass?->name,
                'faculty_name' => $faculty?->name,
                'department_name' => $department?->name,
                'institution_name' => $student->schoolClass?->faculty?->university?->name,
            ],
            'courses' => $coursePayload,
            'summary' => [
                'course_count' => $courses->count(),
                'classes_held' => $totalHeld,
                'classes_attended' => $totalPresent,
                'classes_cancelled' => $totalCancelled,
                'percent' => $totalHeld > 0 ? (int) round(($totalPresent / $totalHeld) * 100) : 0,
            ],
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCourseRow(Student $student, Course $course, int $classId): array
    {
        $weeks = $this->materialWeeksForCourse($course, $classId);

        // Pull every attendance row for this student × course in one
        // query so the per-week loop below is a constant-time array
        // lookup instead of N+1 queries.
        $attendancesByWeek = Attendance::query()
            ->where('student_id', $student->id)
            ->where('course_id', $course->id)
            ->whereIn('attendance_week_id', $weeks->pluck('id')->filter()->all())
            ->get()
            ->keyBy(fn (Attendance $a) => (int) $a->attendance_week_id);

        $weekRows = [];
        $heldCount = 0;
        $presentCount = 0;
        $cancelledCount = 0;
        foreach ($weeks as $week) {
            $weekNumber = (int) $week->week_number;

            if ($week->isCancelled()) {
                $cancelledCount++;
                $weekRows[] = [
                    'week_number' => $weekNumber,
                    'status' => 'cancelled',
                    'marked_at' => null,
                ];

                continue;
            }

            $heldCount++;
            $attendance = $attendancesByWeek->get((int) $week->id);
            $isPresent = $attendance !== null && Attendance::countsAsPresent($attendance->status);
            if ($isPresent) {
                $presentCount++;
            }
            $weekRows[] = [
                'week_number' => $weekNumber,
                'status' => $isPresent ? 'present' : 'absent',
                'marked_at' => $attendance?->attendance_time?->toIso8601String(),
            ];
        }

        return [
            'course_id' => (int) $course->id,
            'course_name' => (string) $course->course_name,
            'course_code' => $course->course_code ? (string) $course->course_code : null,
            'lecturer_name' => $course->resolvedLecturerDisplay(),
            'venue' => $course->venueRelation?->name ?? ($course->venue ?: null),
            'weeks' => $weekRows,
            'held_count' => $heldCount,
            'present_count' => $presentCount,
            'cancelled_count' => $cancelledCount,
            'percent' => $heldCount > 0 ? (int) round(($presentCount / $heldCount) * 100) : 0,
        ];
    }

    /**
     * Material weeks for a course = weeks that had at least one
     * session opened (= class was held / attempted) plus any week
     * explicitly cancelled (so the reason for the gap is visible).
     * Mirrors AttendancePdfController::materialWeeksForCourse so the
     * mobile grid and the web PDF stay in lock-step.
     *
     * @return Collection<int, AttendanceWeek>
     */
    private function materialWeeksForCourse(Course $course, int $classId): Collection
    {
        $weeksQuery = $course->attendanceWeeks()->orderBy('week_number');

        if ($classId > 0 && SchemaFeatures::hasAttendanceWeeksClassId()) {
            $weeksQuery->where(function ($q) use ($classId) {
                $q->where('class_id', $classId)->orWhereNull('class_id');
            });
        }

        $allWeeks = $weeksQuery->get();
        if ($allWeeks->isEmpty()) {
            return $allWeeks;
        }

        $usedWeekIds = AttendanceSession::query()
            ->where('course_id', $course->id)
            ->whereIn('attendance_week_id', $allWeeks->pluck('id'))
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
