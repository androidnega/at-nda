<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stamps every `/api/*` response with a stable version header and, for
 * legacy unversioned routes, a Sunset header pointing at the canonical
 * `/api/v1/*` endpoints. The mobile app can read `X-Api-Version` to
 * decide whether to call new fields, and `Sunset` to warn users on the
 * day the legacy routes go away.
 *
 * This middleware never alters the response body — only headers — so
 * adding it is safe to roll out alongside the existing mobile clients.
 */
class ApiVersionHeaders
{
    /** Bump the date when we actually retire legacy /api/* routes. */
    private const LEGACY_SUNSET = '2027-06-01';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $path = trim($request->path(), '/');
        if (! str_starts_with($path, 'api')) {
            return $response;
        }

        $isV1 = str_starts_with($path, 'api/v1');
        $version = $isV1 ? 'v1' : 'legacy';

        $response->headers->set('X-Api-Version', $version);
        $response->headers->set('X-Api-Supported', 'v1');

        if (! $isV1) {
            // RFC 8594 Sunset header: lets the mobile app surface a one-time
            // "please update" banner before the legacy endpoints are
            // removed.
            $response->headers->set('Deprecation', 'true');
            $response->headers->set('Sunset', self::LEGACY_SUNSET);
            $response->headers->set('Link', '</api/v1>; rel="successor-version"');
        }

        return $response;
    }
}
