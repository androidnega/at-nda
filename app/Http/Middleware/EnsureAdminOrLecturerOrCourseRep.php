<?php

namespace App\Http\Middleware;

use App\Models\Student;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminOrLecturerOrCourseRep
{
    /**
     * Allow admin, lecturer, or course rep (student assigned as class/course rep).
     * Course reps login as students but get full admin dashboard access.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->session()->has('admin_id') || $request->session()->has('lecturer_id')) {
            return $next($request);
        }

        if ($request->session()->has('student_id')) {
            $student = Student::find($request->session()->get('student_id'));
            if ($student && ($student->classReps()->exists() || $student->courseReps()->exists())) {
                return $next($request);
            }
        }

        return redirect()->route('admin.login')->with('info', 'Please log in to access this area.');
    }
}
