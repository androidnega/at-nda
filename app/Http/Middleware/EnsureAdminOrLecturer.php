<?php

namespace App\Http\Middleware;

use App\Support\LecturerAccess;
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
            $routeName = (string) $request->route()?->getName();
            if (! LecturerAccess::routeAllowedForLecturer($routeName)) {
                return redirect()->route('dashboard.dashboard')->with('error', 'Access denied for lecturer account.');
            }
        }

        return $next($request);
    }
}
