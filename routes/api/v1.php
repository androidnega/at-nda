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
    Route::prefix('auth')->middleware('throttle:api-v1-login')->group(function () {
        Route::post('login', [AuthController::class, 'login']);
    });

    Route::get('settings', [SettingsController::class, 'index'])->middleware('throttle:api-v1');

    Route::get('students/removed', [LegacyStudentController::class, 'removed'])->middleware('throttle:api-v1');
    Route::get('students/status', [LegacyStudentController::class, 'status'])->middleware('throttle:api-v1');

    Route::middleware(['auth:sanctum', 'throttle:api-v1'])->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::get('profile', [ProfileController::class, 'show']);
        Route::get('sessions', [SessionController::class, 'index']);
        Route::get('sessions/active', [SessionController::class, 'active']);
        Route::post('attendance', [AttendanceController::class, 'store']);
    });
});
