<?php

use App\Console\Commands\DispatchClassStartReminders;
use App\Console\Commands\MigrateSqliteToMysql;
use App\Http\Middleware\EnsureAdminOnly;
use App\Http\Middleware\EnsureAdminOrLecturer;
use App\Http\Middleware\EnsureClassRep;
use App\Http\Middleware\EnsureLecturer;
use App\Http\Middleware\EnsureNotAdminOrLecturer;
use App\Http\Middleware\EnsureStudentAuthenticated;
use App\Http\Middleware\ForceHttpsForApi;
use App\Http\Middleware\NoStoreCache;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withCommands([
        MigrateSqliteToMysql::class,
        DispatchClassStartReminders::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        // Laravel defaults to route('login'), which this app does not define.
        // API clients often omit Accept: application/json; without this, auth middleware
        // tries to redirect and throws RouteNotFoundException instead of 401.
        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->is('api/*')) {
                return null;
            }

            return route('admin.login');
        });

        $middleware->alias([
            'admin.only' => EnsureAdminOnly::class,
            'admin' => EnsureAdminOrLecturer::class,
            'classrep' => EnsureClassRep::class,
            'lecturer' => EnsureLecturer::class,
            'student.attendance' => EnsureNotAdminOrLecturer::class,
            'student.auth' => EnsureStudentAuthenticated::class,
            'api.https' => ForceHttpsForApi::class,
            'no-store' => NoStoreCache::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // API routes should never return HTML error pages (mobile clients expect JSON).
        $exceptions->shouldRenderJsonWhen(function (Request $request, Throwable $e): bool {
            return $request->is('api/*');
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/v1/*')) {
                return response()->json([
                    'status' => false,
                    'message' => $e->validator->errors()->first() ?: 'Validation failed',
                    'data' => null,
                    'errors' => $e->errors(),
                    'meta' => null,
                ], 422);
            }
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthenticated',
                    'data' => null,
                    'errors' => null,
                    'meta' => null,
                ], 401);
            }
        });

        // Route model binding 404s (e.g. invalid session id) — never expose model class names to clients.
        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'We could not find that session or record.',
                ], 404);
            }
        });

        $exceptions->render(function (QueryException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            Log::error('api.query_exception', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong on our side. Please try again in a moment.',
            ], 500);
        });
    })->create();
