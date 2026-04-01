<?php

namespace App\Http\Controllers;

use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardStudentsController extends Controller
{
    public function index(Request $request): View|\Illuminate\Http\JsonResponse|RedirectResponse
    {
        if ($this->isAdminOrLecturer($request)) {
            return app(StudentController::class)->index($request);
        }
        if ($request->session()->has('student_id')) {
            $sessionStudent = Student::find($request->session()->get('student_id'));
            if ($sessionStudent && !$sessionStudent->isRep()) {
                return redirect()->route('dashboard.dashboard')->with('error', 'You do not have access to this page.');
            }

            return app(ClassRepController::class)->studentsIndex($request);
        }

        return redirect()->route('home')->with('info', 'Please sign in to continue.');
    }

    public function show(Request $request, Student $student): View|RedirectResponse
    {
        if ($this->isAdminOrLecturer($request)) {
            return app(StudentController::class)->show($student);
        }
        if ($request->session()->has('student_id')) {
            $sessionStudent = Student::find($request->session()->get('student_id'));
            if ($sessionStudent && !$sessionStudent->isRep()) {
                return redirect()->route('dashboard.dashboard')->with('error', 'You do not have access to this page.');
            }

            return app(ClassRepController::class)->studentShow($request, $student);
        }

        return redirect()->route('home')->with('info', 'Please sign in to continue.');
    }

    public function resetPassword(Request $request, Student $student): RedirectResponse
    {
        if ($this->isAdminOrLecturer($request)) {
            return app(StudentController::class)->resetPassword($request, $student);
        }
        if ($request->session()->has('student_id')) {
            $sessionStudent = Student::find($request->session()->get('student_id'));
            if ($sessionStudent && !$sessionStudent->isRep()) {
                return redirect()->route('dashboard.dashboard')->with('error', 'You do not have access to this page.');
            }

            return app(ClassRepController::class)->resetPassword($request, $student);
        }

        return redirect()->route('home')->with('info', 'Please sign in to continue.');
    }

    private function isAdminOrLecturer(Request $request): bool
    {
        return $request->session()->has('admin_id') || $request->session()->has('lecturer_id');
    }
}
