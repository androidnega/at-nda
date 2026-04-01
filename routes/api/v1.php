<?php

use App\Http\Controllers\Api\StudentController as LegacyStudentController;
use App\Http\Controllers\Api\V1\AttendanceController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\SessionController;
use App\Http\Controllers\Api\V1\SettingsController;
use Illuminate\Support\Facades\Route;

/*
| Versioned JSON API — strict envelope (see App\Support\ApiEnvelope).
| Legacy unversioned routes remain in routes/api.php unchanged.
*/

Route::middleware('api.https')->group(function () {
    // Browsers and mistaken clients use GET; login only accepts POST with JSON body.
    Route::get('auth/login', function () {
        return response()->json([
            'status' => false,
            'message' => 'Use POST with JSON body: index_number, password. GET is not supported.',
            'data' => [
                'method' => 'POST',
                'content_type' => 'application/json',
                'body' => [
                    'index_number' => 'string (student index)',
                    'password' => 'string',
                ],
            ],
            'errors' => null,
            'meta' => null,
        ], 405);
    })->middleware('throttle:api-v1');

    Route::prefix('auth')->middleware('throttle:api-v1-login')->group(function () {
        Route::post('login', [AuthController::class, 'login']);
    });

    Route::get('settings', [SettingsController::class, 'index'])->middleware('throttle:api-v1');

    Route::get('students/removed', [LegacyStudentController::class, 'removed'])->middleware('throttle:api-v1');
    Route::get('students/status', [LegacyStudentController::class, 'status'])->middleware('throttle:api-v1');

    Route::middleware(['auth:sanctum', 'throttle:api-v1'])->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::get('test-auth', function (\Illuminate\Http\Request $request) {
            return response()->json([
                'status' => true,
                'message' => 'Success',
                'data' => $request->user(),
            ]);
        });
        Route::get('profile', [ProfileController::class, 'show']);
        Route::get('sessions', [SessionController::class, 'index']);
        Route::get('sessions/active', [SessionController::class, 'active']);
        Route::post('attendance', [AttendanceController::class, 'store']);
    });
});
