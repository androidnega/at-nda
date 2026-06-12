<?php

use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AttendanceLateController;
use App\Http\Controllers\Api\AttendanceRecordsController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AppReleaseApiController;
use App\Http\Controllers\Api\ClassRepApiController;
use App\Http\Controllers\Api\ClassRepMarksController;
use App\Http\Controllers\Api\ClassRepRestController;
use App\Http\Controllers\Api\ClassSessionController;
use App\Http\Controllers\Api\StudentAttendanceGridController;
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

/*
| Auth + lookup — throttled at 5/min/IP (same limiter as /api/v1/auth/login).
| /login and /me both accept stored credentials in the body and issue a fresh
| Sanctum token, so they must NOT sit behind auth:sanctum (the existing Flutter
| client calls /me without a Bearer during cold-start profile refresh).
| /students/quick-status is the anti-enumeration pre-auth funnel.
*/
Route::middleware('throttle:api-v1-login')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/me', [AuthController::class, 'me']);
    Route::post('/students/quick-status', [StudentController::class, 'quickStatus']);
});

// Mobile-app update check. Unauthenticated by design — the app
// hits this on launch (before login) to learn if a newer build is
// available. Light read-only call, no PII.
Route::get('/app/latest', [AppReleaseApiController::class, 'latest']);

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth:sanctum');

/*
| Authenticated student-app surface. Per-class endpoints are wrapped in
| `class.access` so the caller must be in / rep for / lecture the
| requested class.
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/timetable', [StudentTimetableController::class, 'show']);
    Route::get('/student/attendance-insights', [StudentAttendanceInsightsController::class, 'show']);
    Route::get('/lecturer/dashboard', [LecturerMobileApiController::class, 'dashboard']);
    Route::get('/lecturer/courses/{course}', [LecturerMobileApiController::class, 'courseDetail'])
        ->whereNumber('course');
    Route::post('/lecturer/messages/send', [LecturerMobileApiController::class, 'sendDirectMessage']);

    Route::post('/student/profile', [StudentProfileController::class, 'update']);
    Route::post('/update-profile', [StudentProfileController::class, 'updateProfile']);
    Route::post('/device-token', [DeviceTokenController::class, 'store']);

    Route::get('/faculties', [FacultyController::class, 'index']);
    Route::get('/departments', [FacultyController::class, 'departments']);

    Route::middleware('class.access')->group(function () {
        Route::get('/students', [StudentController::class, 'index']);
        Route::match(['get', 'post'], '/students/lookup', [StudentController::class, 'lookup']);
        Route::get('/sessions/active', [SessionController::class, 'active']);
        Route::get('/class/active-session', [ClassSessionController::class, 'activeSession']);
        Route::get('/session/{session}/stats', [ClassSessionController::class, 'stats'])
            ->whereNumber('session');
        // Per-student week-by-week attendance grid (JSON + PDF).
        // Match both verbs so the mobile app can POST credentials in
        // the JSON body or pass them as query params on GET.
        Route::match(['get', 'post'], '/student/attendance-grid', [StudentAttendanceGridController::class, 'index']);
        Route::match(['get', 'post'], '/student/attendance-grid/pdf', [StudentAttendanceGridController::class, 'pdf']);
        Route::get('/sessions/current-qr/{session}', [SessionController::class, 'currentQr'])
            ->whereNumber('session');
    });

    // Per-row scope gating happens inside the controller (admin / staff vs.
    // student); the route only needs Sanctum here.
    Route::get('/students/removed', [StudentController::class, 'removed']);
    Route::get('/students/status', [StudentController::class, 'status']);
});

// Profile image binary intentionally open — cached <img> tags depend on it
// and the controller enforces its own access rules. Same binary as web
// /media/... but under /api/* so HandleCors applies (Flutter web).
Route::get('/students/{student}/profile-image', [StudentImageController::class, 'show'])
    ->whereNumber('student');

/*
| Routes below carry their own bearer / password parsing in-controller.
| They stay outside auth:sanctum to preserve the existing Flutter contract
| (index+password in body/query). Phase 3 will migrate them.
*/

Route::match(['get', 'post'], '/class-rep/dashboard', [ClassRepRestController::class, 'dashboard']);
Route::match(['get', 'post'], '/class-rep/students', [ClassRepRestController::class, 'students']);
Route::match(['get', 'post'], '/class-rep/student-detail', [ClassRepRestController::class, 'studentDetail']);
Route::post('/class-rep/sessions/open', [ClassRepRestController::class, 'openSession']);
Route::post('/class-rep/sessions/close', [ClassRepRestController::class, 'closeSession']);
Route::post('/class-rep/sessions/extend', [ClassRepRestController::class, 'extendSession']);
Route::post('/class-rep/sessions/prune-ghosts', [ClassRepRestController::class, 'pruneGhostSessions']);

// Per-student roster for an active session, plus the manual mark
// endpoint that backs the mobile rep "Mark students" flow. Both
// idempotent and rate-limited via the same throttle as the rest
// of the rep API.
Route::post('/class-rep/sessions/roster', [ClassRepMarksController::class, 'roster']);
Route::post('/class-rep/marks', [ClassRepMarksController::class, 'mark']);

Route::post('/attendance/open', [ClassRepRestController::class, 'openSession']);
Route::post('/attendance/close', [ClassRepRestController::class, 'closeSession']);

Route::post('/rep/courses', [ClassRepApiController::class, 'courses']);
Route::post('/rep/sessions/open', [ClassRepApiController::class, 'openSession']);
Route::post('/rep/sessions/{session}/close', [ClassRepApiController::class, 'closeSession'])->whereNumber('session');
Route::post('/sessions/{session}/location', [SessionController::class, 'updateLocation'])->whereNumber('session');
Route::get('/settings', [SettingsController::class, 'index']);
Route::post('/notifications/pending', [NotificationsController::class, 'pending']);
Route::post('/attendance', [AttendanceController::class, 'markAttendance']);
Route::post('/attendance/checkout', [AttendanceController::class, 'checkout']);
Route::get('/attendance/sync', [AttendanceController::class, 'sync']);

// Batch offline-sync — throttle to 30 batches/minute per token and
// reject any body larger than 256 KB before the validator runs. The
// validator additionally caps `records` at 50 entries (mirrors the
// mobile bin-packer's batchMaxRows constant).
Route::post('/attendance/sync', [AttendanceController::class, 'syncPush'])
    ->middleware(['throttle:attendance-sync', 'max.body:256']);

Route::get('/attendance/missed-warnings', [AttendanceController::class, 'missedWarnings']);

// Late-attendance review surface. Reads are 60/min/token, decides are
// 20/min/token (see POST_IMPLEMENTATION_ARCHITECTURE_AUDIT §C-3). All
// bodies are tiny (notes only) — cap at 8 KB.
Route::get('/attendance/late', [AttendanceLateController::class, 'index'])
    ->middleware(['throttle:attendance-late-read', 'max.body:8']);
Route::get('/attendance/late/status/{uuid}', [AttendanceLateController::class, 'statusByUuid'])
    ->where('uuid', '[A-Za-z0-9._-]+')
    ->middleware(['throttle:attendance-late-read', 'max.body:8']);
Route::post('/attendance/late/{id}/approve', [AttendanceLateController::class, 'approve'])
    ->whereNumber('id')
    ->middleware(['throttle:attendance-late-decide', 'max.body:8']);
Route::post('/attendance/late/{id}/deny', [AttendanceLateController::class, 'deny'])
    ->whereNumber('id')
    ->middleware(['throttle:attendance-late-decide', 'max.body:8']);

Route::get('/attendance/{session}/records', [AttendanceRecordsController::class, 'records'])->whereNumber('session');
Route::get('/attendance/{session}/export/csv', [AttendanceRecordsController::class, 'exportCsv'])->whereNumber('session');
Route::get('/attendance/{session}/export/excel', [AttendanceRecordsController::class, 'exportExcel'])->whereNumber('session');
Route::get('/attendance/{session}/export/pdf', [AttendanceRecordsController::class, 'exportPdf'])->whereNumber('session');

Route::get('/onboarding/check', [StudentOnboardingController::class, 'check']);
Route::post('/onboarding/complete', [StudentOnboardingController::class, 'complete']);
