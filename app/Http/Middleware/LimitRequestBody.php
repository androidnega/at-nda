<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reject oversized request bodies before they enter the controller layer.
 *
 * Apply to write-heavy routes whose payload size is known and bounded —
 * the attendance batch sync endpoint, the late-attendance review
 * endpoints, etc. A misbehaving (or malicious) client sending a 5 MB
 * JSON body would otherwise consume PHP memory, run the validator
 * across thousands of items, and then fail anyway. With this middleware
 * the request is dropped at the edge with 413 Payload Too Large.
 *
 * Usage:
 *   Route::post(...)->middleware('max.body:256');   // 256 KB cap
 *
 * Behaviour:
 *   - Reads the Content-Length header (PHP populates this on every
 *     request that carries a body, including chunked transfers once
 *     they are buffered).
 *   - When the header is missing (rare; chunked uploads on some
 *     proxies) we fall back to the raw request size.
 *   - The limit argument is in KILOBYTES (1 KB = 1024 B).
 *
 * Never throws — a missing or non-numeric header is treated as 0 and
 * passes through. The PHP / web-server level body cap remains the
 * outer safety net.
 */
class LimitRequestBody
{
    /** Hard ceiling so a "max.body:9999999" never bypasses sanity. */
    private const ABSOLUTE_MAX_BYTES = 4 * 1024 * 1024;

    public function handle(Request $request, Closure $next, string $kilobytes = '256'): Response
    {
        $limit = (int) $kilobytes;
        if ($limit <= 0) {
            $limit = 256;
        }
        $limitBytes = min($limit * 1024, self::ABSOLUTE_MAX_BYTES);

        $contentLength = (int) ($request->headers->get('Content-Length') ?? 0);
        if ($contentLength <= 0) {
            $contentLength = strlen((string) $request->getContent(false));
        }

        if ($contentLength > $limitBytes) {
            return response()->json([
                'success' => false,
                'status' => 'error',
                'message' => 'Request body is too large.',
                'limit_kb' => $limit,
            ], 413);
        }

        return $next($request);
    }
}
