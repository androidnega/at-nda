<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Http\Controllers\LecturerDashboardController;
use App\Http\Controllers\StudentDashboardController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        if ($request->session()->has('admin_id')) {
            return app(AdminController::class)->dashboard($request);
        }
        if ($request->session()->has('lecturer_id')) {
            return app(LecturerDashboardController::class)->dashboard($request);
        }
        if ($request->session()->has('student_id')) {
            $student = Student::find($request->session()->get('student_id'));
            if ($student && $student->classReps()->exists()) {
                return app(ClassRepController::class)->overview($request);
            }
            return app(StudentDashboardController::class)->dashboard($request);
        }

        return redirect()->route('home')->with('info', 'Please sign in to view your dashboard.');
    }
}
