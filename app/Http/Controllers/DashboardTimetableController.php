<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Student;
use App\Support\RoleAccess;
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

        $classIds = $student->timetableVisibleClassIds();
        $courses = $classIds->isEmpty()
            ? collect()
            : Course::with(['schoolClass', 'lecturer', 'venueRelation'])
                ->whereIn('class_id', $classIds)
                ->whereNotNull('day_of_week')
                ->whereNotNull('start_time')
                ->orderByRaw("CASE day_of_week WHEN 'Monday' THEN 1 WHEN 'Tuesday' THEN 2 WHEN 'Wednesday' THEN 3 WHEN 'Thursday' THEN 4 WHEN 'Friday' THEN 5 WHEN 'Saturday' THEN 6 WHEN 'Sunday' THEN 7 ELSE 99 END")
                ->orderBy('start_time')
                ->get();

        $courses->each(function (Course $course) {
            $course->setAttribute(
                'day_key',
                ucfirst(strtolower(trim((string) $course->day_of_week)))
            );
        });

        $byDay = $courses->groupBy('day_key');
        $dayOrder = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $orderedDays = collect($dayOrder)->filter(fn (string $d) => $byDay->has($d))->values()->all();
        $weekProgress = $student->weeklyTimetableSummary();

        return view('dashboard.timetable', [
            'courses' => $courses,
            'byDay' => $byDay,
            'orderedDays' => $orderedDays,
            'weekProgress' => $weekProgress,
            'layout' => $student->isRep() ? 'layouts.courserep' : 'layouts.student',
            'student' => $student,
        ]);
    }
}
