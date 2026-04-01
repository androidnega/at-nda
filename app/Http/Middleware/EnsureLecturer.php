<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Requires an authenticated lecturer session (lecturer_id), not admin/student.
 */
class EnsureLecturer
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->session()->has('lecturer_id')) {
            return redirect()->route('lecturer.login')->with('info', 'Please sign in as a lecturer.');
        }

        return $next($request);
    }
}
