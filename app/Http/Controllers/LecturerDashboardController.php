<?php

namespace App\Http\Controllers;

use App\Models\Lecturer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LecturerDashboardController extends Controller
{
    public function dashboard(Request $request): View|RedirectResponse
    {
        $lecturerId = $request->session()->get('lecturer_id');
        if (!$lecturerId) {
            return redirect()->route('lecturer.login');
        }

        $lecturer = Lecturer::find($lecturerId);
        if (!$lecturer) {
            $request->session()->forget('lecturer_id');
            return redirect()->route('lecturer.login');
        }
        if ($lecturer->must_change_password) {
            return redirect()->route('lecturer.password.change.form');
        }

        $courses = $lecturer->courses()
            ->with(['schoolClass', 'attendanceWeeks'])
            ->get();

        return view('dashboard.lecturer', ['lecturer' => $lecturer, 'courses' => $courses, 'dashboardRole' => 'lecturer']);
    }
}
