<?php

namespace App\Http\Middleware;

use App\Support\RoleAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminOrLecturer
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($r = RoleAccess::denyStudentForStaffRoutes($request)) {
            return $r;
        }
        if ($r = RoleAccess::requireStaffSession($request)) {
            return $r;
        }
        if ($request->session()->has('lecturer_id') && ! $request->session()->has('admin_id')) {
            $allowedForLecturer = [
                'dashboard.dashboard',
                'dashboard.students.index',
                'dashboard.students.show',
                'dashboard.students.reset-password',
                'dashboard.pdf.export',
            ];
            if (! in_array((string) $request->route()?->getName(), $allowedForLecturer, true)) {
                return redirect()->route('dashboard.dashboard')->with('error', 'Access denied for lecturer account.');
            }
        }

        return $next($request);
    }
}
