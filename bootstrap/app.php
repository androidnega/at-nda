<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin.only' => \App\Http\Middleware\EnsureAdminOnly::class,
            'admin' => \App\Http\Middleware\EnsureAdminOrLecturer::class,
            'courserep' => \App\Http\Middleware\EnsureCourseRep::class,
            'student.attendance' => \App\Http\Middleware\EnsureNotAdminOrLecturer::class,
            'student.auth' => \App\Http\Middleware\EnsureStudentAuthenticated::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // API routes should never return HTML error pages (mobile clients expect JSON).
        $exceptions->shouldRenderJsonWhen(function (Request $request, \Throwable $e): bool {
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
            if ($request->is('api/v1/*')) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthenticated',
                    'data' => null,
                    'errors' => null,
                    'meta' => null,
                ], 401);
            }
        });
    })->create();
