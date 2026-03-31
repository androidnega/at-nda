<?php

namespace App\Http\Middleware;

use App\Support\RoleAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCourseRep
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($r = RoleAccess::requireCourseRep($request)) {
            return $r;
        }

        return $next($request);
    }
}
