<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForceHttpsForApi
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! app()->environment('production')) {
            return $next($request);
        }

        $proto = strtolower((string) $request->header('x-forwarded-proto', ''));
        if (! $request->isSecure() && $proto !== 'https') {
            return redirect()->secure($request->getRequestUri(), 308);
        }

        return $next($request);
    }
}
