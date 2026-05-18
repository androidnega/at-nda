<?php

namespace App\Http\Controllers;

use App\Models\Lecturer;
use App\Support\SchemaFeatures;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LecturerDashboardController extends Controller
{
    public function dashboard(Request $request): View|RedirectResponse
    {
        $lecturerId = $request->session()->get('lecturer_id');
        if (! $lecturerId) {
            return redirect()->route('lecturer.login');
        }

        $lecturer = Lecturer::query()
            ->with(SchemaFeatures::hasClassLecturerPivot() ? ['schoolClasses'] : ['schoolClass'])
            ->find($lecturerId);
        if (! $lecturer) {
            $request->session()->forget('lecturer_id');

            return redirect()->route('lecturer.login');
        }
        if ($lecturer->must_change_password) {
            return redirect()->route('lecturer.password.change.form');
        }

        $assignedClasses = $lecturer->assignedSchoolClasses();
        $courses = $lecturer->teachingCourses();
        $classIds = $lecturer->assignedClassIds();

        $classGroups = $assignedClasses->map(function ($schoolClass) use ($courses) {
            return [
                'class' => $schoolClass,
                'courses' => $courses->filter(
                    fn ($course) => $course->isAssignedToClass((int) $schoolClass->id)
                )->values(),
            ];
        });

        $orphanCourses = $courses->filter(function ($course) use ($classIds) {
            $linked = collect($course->assignedClassIds());

            return $linked->isEmpty() || $linked->intersect($classIds)->isEmpty();
        })->values();

        $totalStudents = (int) $assignedClasses->sum('students_count');

        return view('dashboard.lecturer', [
            'lecturer' => $lecturer,
            'courses' => $courses,
            'classGroups' => $classGroups,
            'orphanCourses' => $orphanCourses,
            'totalStudents' => $totalStudents,
            'dashboardRole' => 'lecturer',
        ]);
    }
}
