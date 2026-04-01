<?php

namespace App\Support;

use App\Models\Student;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Centralized redirects for student vs staff vs class-rep access.
 */
class RoleAccess
{
    public static function denyStaffForStudentRoutes(Request $request): ?Response
    {
        if ($request->session()->has('admin_id') || $request->session()->has('lecturer_id')) {
            return redirect()->route('dashboard.dashboard')
                ->with('error', 'This page is for students. Please use your staff dashboard.');
        }

        return null;
    }

    public static function requireStudentSession(Request $request): ?Response
    {
        if (!$request->session()->has('student_id')) {
            return redirect()->route('home')->with('info', 'Please sign in to a-tenda first.');
        }

        return null;
    }

    public static function denyStudentForStaffRoutes(Request $request): ?Response
    {
        if ($request->session()->has('student_id')) {
            return redirect()->route('dashboard.dashboard')
                ->with('error', 'This area is for staff only. Open your student dashboard from the main site.');
        }

        return null;
    }

    public static function requireStaffSession(Request $request): ?Response
    {
        if (!$request->session()->has('admin_id') && !$request->session()->has('lecturer_id')) {
            return redirect()->route('admin.login')->with('info', 'Please sign in with your staff account.');
        }

        return null;
    }

    public static function requireClassRep(Request $request): ?Response
    {
        if ($r = self::denyStaffForStudentRoutes($request)) {
            return $r;
        }
        if ($r = self::requireStudentSession($request)) {
            return $r;
        }

        $student = Student::find($request->session()->get('student_id'));
        if (! $student || $student->classReps()->count() === 0) {
            return redirect()->route('dashboard.dashboard')->with('error', 'That area isn’t available for your account.');
        }

        return null;
    }

    /** @deprecated Use {@see requireClassRep} */
    public static function requireCourseRep(Request $request): ?Response
    {
        return self::requireClassRep($request);
    }
}
