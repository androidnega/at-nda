<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Lecturer;
use App\Models\Student;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sanctum-required gate for endpoints that return per-class data
 * (rosters, attendance histories, session lists). Resolves the
 * authenticated principal from the bearer, then asserts the
 * requested class scope is one the caller is entitled to see.
 *
 * Reads the requested class id from (in order):
 *   - $request->route('class')          (route binding)
 *   - $request->input('class_id')       (form body)
 *   - $request->query('class_id')       (query string)
 *
 * When no class id is provided, the middleware leaves resolution
 * to the controller (it will derive the caller's own class).
 */
final class EnsureCallerCanSeeClass
{
    public function handle(Request $request, Closure $next): Response
    {
        $bearer = $request->bearerToken();
        if ($bearer === null || $bearer === '') {
            return self::unauthenticated();
        }

        $pat = PersonalAccessToken::findToken($bearer);
        if ($pat === null || $pat->tokenable === null) {
            return self::unauthenticated();
        }

        $owner = $pat->tokenable;

        $rawClassId = $request->route('class')
            ?? $request->input('class_id')
            ?? $request->query('class_id');

        $requestedClassId = is_numeric($rawClassId) ? (int) $rawClassId : null;

        if ($requestedClassId === null) {
            // No explicit class id — controller will derive from the
            // token's tokenable. Allow.
            $request->attributes->set('caller_principal', $owner);

            return $next($request);
        }

        if (! self::isAllowed($owner, $requestedClassId)) {
            return response()->json([
                'message' => 'You do not have access to that class.',
                'error_code' => 'class_access_denied',
            ], 403);
        }

        $request->attributes->set('caller_principal', $owner);

        return $next($request);
    }

    private static function isAllowed(object $owner, int $classId): bool
    {
        if ($owner instanceof Student) {
            if ((int) ($owner->class_id ?? 0) === $classId) {
                return true;
            }
            $managed = $owner->repManagedClassIds()
                ->map(fn ($id) => (int) $id)
                ->all();
            if (in_array($classId, $managed, true)) {
                return true;
            }

            return false;
        }

        if ($owner instanceof Lecturer) {
            if (\App\Support\SchemaFeatures::hasClassLecturerPivot()) {
                return $owner->schoolClasses()
                    ->where('classes.id', $classId)
                    ->exists();
            }

            // Older deploys: a lecturer is allowed only their
            // legacy class_id column.
            return (int) ($owner->class_id ?? 0) === $classId;
        }

        return false;
    }

    private static function unauthenticated(): Response
    {
        return response()->json([
            'message' => 'Unauthenticated',
            'error_code' => 'unauthenticated',
        ], 401);
    }
}
