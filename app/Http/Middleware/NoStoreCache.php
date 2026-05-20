<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets aggressive no-store cache headers so authenticated student/lecturer
 * pages (and the matching login pages) are never served from browser BFCache
 * or history cache. This is what allows the server-side "redirect already-
 * authenticated users away from the login page" logic to actually win when
 * the user taps the phone Back button after signing in.
 */
class NoStoreCache
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0, private');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }
}
