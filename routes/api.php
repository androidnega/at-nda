<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\DeviceTokenController;
use App\Http\Controllers\Api\FacultyController;
use App\Http\Controllers\Api\SessionController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\StudentController;
use App\Http\Controllers\Api\StudentProfileController;
use App\Http\Controllers\StudentOnboardingController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/students', [StudentController::class, 'index']);
});

/*
| API v1 (extended) — production envelope + Sanctum (kept for existing clients).
*/
Route::prefix('v1')->group(base_path('routes/api/v1.php'));

Route::post('/login', [AuthController::class, 'login']);
Route::post('/me', [AuthController::class, 'me']);

Route::post('/student/profile', [StudentProfileController::class, 'update']);
Route::post('/update-profile', [StudentProfileController::class, 'updateProfile']);
Route::post('/device-token', [DeviceTokenController::class, 'store']);

Route::get('/faculties', [FacultyController::class, 'index']);
Route::get('/departments', [FacultyController::class, 'departments']);

Route::get('/students/removed', [StudentController::class, 'removed']);
Route::get('/students/status', [StudentController::class, 'status']);
Route::get('/students', [StudentController::class, 'index']);
Route::match(['get', 'post'], '/students/lookup', [StudentController::class, 'lookup']);
Route::get('/sessions/active', [SessionController::class, 'active']);
Route::post('/sessions/{session}/location', [SessionController::class, 'updateLocation'])->whereNumber('session');
Route::get('/sessions/current-qr/{session}', [SessionController::class, 'currentQr'])->whereNumber('session');
Route::get('/settings', [SettingsController::class, 'index']);
Route::post('/attendance', [AttendanceController::class, 'markAttendance']);
Route::get('/attendance/sync', [AttendanceController::class, 'sync']);
Route::post('/attendance/sync', [AttendanceController::class, 'syncPush']);
Route::get('/attendance/missed-warnings', [AttendanceController::class, 'missedWarnings']);

Route::get('/onboarding/check', [StudentOnboardingController::class, 'check']);
Route::post('/onboarding/complete', [StudentOnboardingController::class, 'complete']);
