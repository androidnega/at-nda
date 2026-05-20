<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Support\ClassTimetableAccess;
use App\Support\RoleAccess;
use App\Support\SchemaFeatures;
use App\Support\StudentCourseAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardTimetableController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        if ($r = RoleAccess::denyStaffForStudentRoutes($request)) {
            return $r;
        }
        if ($r = RoleAccess::requireStudentSession($request)) {
            return $r;
        }

        $student = Student::find($request->session()->get('student_id'));
        if (! $student) {
            return redirect()->route('home')->with('error', 'Please sign in again.');
        }

        $classId = (int) ($student->class_id ?? 0);
        $entries = collect();

        if ($classId > 0 && SchemaFeatures::hasClassTimetables() && ClassTimetableAccess::classHasEntries($classId)) {
            $entries = ClassTimetableAccess::entriesForClass($classId);
        } elseif ($classId > 0) {
            // Legacy fallback: surface the course-level schedule until reps
            // create their own per-class entries.
            $entries = StudentCourseAccess::coursesQueryForStudent($student)
                ->with(['schoolClass', 'lecturer', 'venueRelation'])
                ->whereNotNull('day_of_week')
                ->whereNotNull('start_time')
                ->orderByRaw("CASE day_of_week WHEN 'Monday' THEN 1 WHEN 'Tuesday' THEN 2 WHEN 'Wednesday' THEN 3 WHEN 'Thursday' THEN 4 WHEN 'Friday' THEN 5 WHEN 'Saturday' THEN 6 WHEN 'Sunday' THEN 7 ELSE 99 END")
                ->orderBy('start_time')
                ->get()
                ->map(fn ($course) => $this->courseToTimetableEntry($course));
        }

        $dayOrder = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $byDay = $entries->groupBy(fn ($e) => ucfirst(strtolower(trim((string) $e->day_of_week))));
        $orderedDays = collect($dayOrder)->filter(fn (string $d) => $byDay->has($d))->values()->all();
        $weekProgress = $student->weeklyTimetableSummary();

        return view('dashboard.timetable', [
            'entries' => $entries,
            'byDay' => $byDay,
            'orderedDays' => $orderedDays,
            'weekProgress' => $weekProgress,
            'layout' => $student->isRep() ? 'layouts.classrep' : 'layouts.student',
            'student' => $student,
            'canManage' => $student->isRep(),
        ]);
    }

    /**
     * Wrap a legacy Course row in an object that quacks like a ClassTimetable
     * so the view code stays uniform during the transition.
     */
    private function courseToTimetableEntry($course): object
    {
        return (object) [
            'id' => $course->id,
            'course' => $course,
            'course_id' => $course->id,
            'class_id' => $course->class_id,
            'day_of_week' => $course->day_of_week,
            'start_time' => $course->start_time,
            'end_time' => $course->end_time,
            'lecturer' => $course->lecturer ?? null,
            'lecturer_name' => $course->lecturer_name,
            'venueRelation' => $course->venueRelation ?? null,
            'venue' => $course->venue,
        ];
    }
}
