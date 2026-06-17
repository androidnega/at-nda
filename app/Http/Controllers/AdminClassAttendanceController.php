<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\SchoolClass;
use App\Services\ClassScopedAttendanceService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminClassAttendanceController extends Controller
{
    public function forCourse(
        Request $request,
        SchoolClass $schoolClass,
        Course $course,
        ClassScopedAttendanceService $attendance
    ): View {
        if (! $request->session()->has('admin_id') && ! $request->session()->has('lecturer_id')) {
            abort(403);
        }

        $schoolClass->loadMissing(['faculty', 'department']);
        $course->loadMissing(['schoolClass', 'schoolClasses', 'lecturer', 'venueRelation']);

        $detail = $attendance->courseDetail($schoolClass, $course);

        return view('admin.class-attendance-course', array_merge($detail, [
            'schoolClass' => $schoolClass,
            'course' => $course,
        ]));
    }
}
