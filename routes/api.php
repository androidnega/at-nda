<?php

use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AttendanceRecordsController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClassRepApiController;
use App\Http\Controllers\Api\ClassRepRestController;
use App\Http\Controllers\Api\ClassSessionController;
use App\Http\Controllers\Api\DeviceTokenController;
use App\Http\Controllers\Api\FacultyController;
use App\Http\Controllers\Api\LecturerMobileApiController;
use App\Http\Controllers\Api\StudentAttendanceInsightsController;
use App\Http\Controllers\Api\NotificationsController;
use App\Http\Controllers\Api\SessionController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\StudentController;
use App\Http\Controllers\Api\StudentProfileController;
use App\Http\Controllers\Api\StudentTimetableController;
use App\Http\Controllers\StudentImageController;
use App\Http\Controllers\StudentOnboardingController;
use Illuminate\Support\Facades\Route;

/*
| API v1 (extended) — production envelope + Sanctum (kept for existing clients).
| GET /api/v1/students is defined in routes/api/v1.php (auth + class scope).
*/
Route::prefix('v1')->group(base_path('routes/api/v1.php'));

Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout']);
Route::post('/me', [AuthController::class, 'me']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/timetable', [StudentTimetableController::class, 'show']);
    Route::get('/student/attendance-insights', [StudentAttendanceInsightsController::class, 'show']);
    Route::get('/lecturer/dashboard', [LecturerMobileApiController::class, 'dashboard']);
    Route::get('/lecturer/courses/{course}', [LecturerMobileApiController::class, 'courseDetail'])
        ->whereNumber('course');
    Route::post('/lecturer/messages/send', [LecturerMobileApiController::class, 'sendDirectMessage']);
});

Route::post('/student/profile', [StudentProfileController::class, 'update']);
Route::post('/update-profile', [StudentProfileController::class, 'updateProfile']);
Route::post('/device-token', [DeviceTokenController::class, 'store']);

Route::get('/faculties', [FacultyController::class, 'index']);
Route::get('/departments', [FacultyController::class, 'departments']);

Route::get('/students/removed', [StudentController::class, 'removed']);
Route::get('/students/status', [StudentController::class, 'status']);
// Same binary as web /media/... but under /api/* so HandleCors applies (Flutter web).
Route::get('/students/{student}/profile-image', [StudentImageController::class, 'show'])
    ->whereNumber('student');
Route::get('/students', [StudentController::class, 'index']);
Route::match(['get', 'post'], '/students/lookup', [StudentController::class, 'lookup']);
Route::get('/sessions/active', [SessionController::class, 'active']);
Route::get('/class/active-session', [ClassSessionController::class, 'activeSession']);
Route::get('/session/{session}/stats', [ClassSessionController::class, 'stats'])->whereNumber('session');

Route::match(['get', 'post'], '/class-rep/dashboard', [ClassRepRestController::class, 'dashboard']);
Route::match(['get', 'post'], '/class-rep/students', [ClassRepRestController::class, 'students']);
Route::match(['get', 'post'], '/class-rep/student-detail', [ClassRepRestController::class, 'studentDetail']);
Route::post('/class-rep/sessions/open', [ClassRepRestController::class, 'openSession']);
Route::post('/class-rep/sessions/close', [ClassRepRestController::class, 'closeSession']);
Route::post('/class-rep/sessions/extend', [ClassRepRestController::class, 'extendSession']);

Route::post('/attendance/open', [ClassRepRestController::class, 'openSession']);
Route::post('/attendance/close', [ClassRepRestController::class, 'closeSession']);

Route::post('/rep/courses', [ClassRepApiController::class, 'courses']);
Route::post('/rep/sessions/open', [ClassRepApiController::class, 'openSession']);
Route::post('/rep/sessions/{session}/close', [ClassRepApiController::class, 'closeSession'])->whereNumber('session');
Route::post('/sessions/{session}/location', [SessionController::class, 'updateLocation'])->whereNumber('session');
Route::get('/sessions/current-qr/{session}', [SessionController::class, 'currentQr'])->whereNumber('session');
Route::get('/settings', [SettingsController::class, 'index']);
Route::post('/notifications/pending', [NotificationsController::class, 'pending']);
Route::post('/attendance', [AttendanceController::class, 'markAttendance']);
Route::post('/attendance/checkout', [AttendanceController::class, 'checkout']);
Route::get('/attendance/sync', [AttendanceController::class, 'sync']);
Route::post('/attendance/sync', [AttendanceController::class, 'syncPush']);
Route::get('/attendance/missed-warnings', [AttendanceController::class, 'missedWarnings']);

Route::get('/attendance/{session}/records', [AttendanceRecordsController::class, 'records'])->whereNumber('session');
Route::get('/attendance/{session}/export/csv', [AttendanceRecordsController::class, 'exportCsv'])->whereNumber('session');
Route::get('/attendance/{session}/export/excel', [AttendanceRecordsController::class, 'exportExcel'])->whereNumber('session');
Route::get('/attendance/{session}/export/pdf', [AttendanceRecordsController::class, 'exportPdf'])->whereNumber('session');

Route::get('/onboarding/check', [StudentOnboardingController::class, 'check']);
Route::post('/onboarding/complete', [StudentOnboardingController::class, 'complete']);
