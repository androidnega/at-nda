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

        return $next($request);
    }
}
