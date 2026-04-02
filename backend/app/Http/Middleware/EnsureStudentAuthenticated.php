<?php

namespace App\Http\Middleware;

use App\Support\RoleAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Student-only routes (profile, onboarding). Blocks staff sessions from opening student pages.
 */
class EnsureStudentAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($r = RoleAccess::denyStaffForStudentRoutes($request)) {
            return $r;
        }
        if ($r = RoleAccess::requireStudentSession($request)) {
            return $r;
        }

        return $next($request);
    }
}
