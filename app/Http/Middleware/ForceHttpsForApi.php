<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForceHttpsForApi
{
    /**
     * One year HSTS, applied to subdomains. We do NOT use `preload`
     * yet — that requires a longer commitment than this app is ready
     * for. Switch to preload only after at least one full HSTS year
     * with no rollback.
     */
    private const HSTS_HEADER = 'max-age=31536000; includeSubDomains';

    public function handle(Request $request, Closure $next): Response
    {
        if (! app()->environment('production')) {
            return $next($request);
        }

        $proto = strtolower((string) $request->header('x-forwarded-proto', ''));
        if (! $request->isSecure() && $proto !== 'https') {
            return redirect()->secure($request->getRequestUri(), 308);
        }

        /** @var Response $response */
        $response = $next($request);
        if (! $response->headers->has('Strict-Transport-Security')) {
            $response->headers->set('Strict-Transport-Security', self::HSTS_HEADER);
        }

        return $response;
    }
}
