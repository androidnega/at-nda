<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lightweight gate that lets any signed-in actor pass — student, class rep,
 * lecturer, or admin. Controllers downstream are still responsible for
 * narrower role checks; this middleware only ensures *somebody* is logged in
 * so we don't expose endpoints to anonymous visitors.
 */
class EnsureSignedInAnybody
{
    public function handle(Request $request, Closure $next): Response
    {
        $session = $request->session();
        if (
            $session->has('student_id')
            || $session->has('lecturer_id')
            || $session->has('admin_id')
        ) {
            return $next($request);
        }

        return redirect()->route('home')->with('info', 'Please sign in to continue.');
    }
}
