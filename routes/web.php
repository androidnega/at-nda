<?php

use App\Http\Controllers\AdminAttendanceWeekController;
use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AttendancePdfController;
use App\Http\Controllers\AttendanceSessionController;
use App\Http\Controllers\ClassController;
use App\Http\Controllers\ClassLogoController;
use App\Http\Controllers\UniversityLogoController;
use App\Http\Controllers\ClassRepController;
use App\Http\Controllers\ClassRepTimetableController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\CourseMaterialController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DashboardProfileController;
use App\Http\Controllers\DashboardStudentsController;
use App\Http\Controllers\DashboardTimetableController;
use App\Http\Controllers\LecturerAttendanceWeekController;
use App\Http\Controllers\LecturerAuthController;
use App\Http\Controllers\LecturerController;
use App\Http\Controllers\MobileDashboardThemeController;
use App\Http\Controllers\RunMigrationsController;
use App\Http\Controllers\SemesterController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\StaffAccountController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\StudentDashboardController;
use App\Http\Controllers\StudentImageController;
use App\Http\Controllers\StudentOnboardingController;
use App\Http\Controllers\StudentPasswordResetController;
use App\Http\Controllers\UniversityController;
use App\Http\Controllers\VenueController;
use App\Models\AttendanceSession;
use App\Models\Course;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function (Request $request) {
    if ($request->session()->has('student_id')) {
        return redirect()->route('dashboard.dashboard');
    }

    return view('home');
})->middleware('no-store')->name('home');

Route::post('/filter-by-index', function (Request $request) {
    $validated = $request->validate(['index_number' => 'required|string']);
    $indexNumber = strtoupper(trim($validated['index_number']));
    $student = Student::findByIndex($indexNumber);
    if (! $student) {
        return redirect()->route('home')->with('error', 'We couldn’t find that student ID.');
    }
    if ($student->class_id) {
        $request->session()->put('filter_class_id', $student->class_id);
        $request->session()->put('filter_index', $indexNumber);

        return redirect()->route('home')->with('success', 'Showing courses for your class.');
    }
    $request->session()->forget(['filter_class_id', 'filter_index']);

    return redirect()->route('home')->with('info', 'No class assigned. Showing all courses.');
})->name('home.filter');

Route::post('/clear-filter', function (Request $request) {
    $request->session()->forget(['filter_class_id', 'filter_index']);

    return redirect()->route('home');
})->name('home.clear-filter');

Route::get('/run-migrations', RunMigrationsController::class)->name('system.run-migrations');
Route::get('/run-migartions', RunMigrationsController::class); // alias for common typo
Route::get('/run-migrations-auto', function () {
    $key = RunMigrationsController::expectedKey();
    if ($key === '') {
        abort(500, 'Migration key cannot be derived from configuration.');
    }

    return redirect()->route('system.run-migrations', ['key' => $key]);
});
Route::get('/run-migartions-auto', function () {
    $key = RunMigrationsController::expectedKey();
    if ($key === '') {
        abort(500, 'Migration key cannot be derived from configuration.');
    }

    return redirect()->route('system.run-migrations', ['key' => $key]);
});
Route::get('/run-migraiton-auto', function () {
    $key = RunMigrationsController::expectedKey();
    if ($key === '') {
        abort(500, 'Migration key cannot be derived from configuration.');
    }

    return redirect()->route('system.run-migrations', ['key' => $key]);
});
Route::get('/run-migration-auto', function () {
    $key = RunMigrationsController::expectedKey();
    if ($key === '') {
        abort(500, 'Migration key cannot be derived from configuration.');
    }

    return redirect()->route('system.run-migrations', ['key' => $key]);
});

Route::get('/media/students/{student}/profile-image', [StudentImageController::class, 'show'])
    ->name('media.students.profile-image')
    ->scopeBindings();
Route::get('/media/classes/{schoolClass}/logo', [ClassLogoController::class, 'show'])
    ->name('media.classes.logo')
    ->whereNumber('schoolClass');
Route::get('/media/universities/{university}/logo', [UniversityLogoController::class, 'show'])
    ->name('media.universities.logo')
    ->whereNumber('university');

/*
| Web-only student attendance (Blade + fetch). Canonical paths under /web/attendance.
| Flutter/mobile continues to use /api/* only — unchanged here.
*/
Route::middleware(['student.attendance', 'student.session.integrity', 'no-store'])->prefix('web/attendance')->name('web.attendance.')->group(function () {
    Route::post('verify', [AttendanceController::class, 'verify'])->name('verify');
    Route::post('mark', [AttendanceController::class, 'mark'])->name('mark');
    Route::post('sync', [AttendanceController::class, 'sync'])->name('sync');
    Route::get('direct/{course}', [AttendanceController::class, 'directEntry'])->name('direct');
    Route::get('{course}/success', [AttendanceController::class, 'success'])->name('success');
    Route::get('{course}', [AttendanceController::class, 'form'])->name('form');
});

Route::middleware('student.attendance')->get('/mark-attendance/{course}', [AttendanceController::class, 'directEntry'])
    ->name('attendance.direct.link');

Route::middleware('student.attendance')->group(function () {
    Route::get('/attendance/{course}', [AttendanceController::class, 'legacyRedirectToForm']);
    Route::get('/attendance/{course}/success', [AttendanceController::class, 'legacyRedirectToSuccess']);
});

Route::middleware('no-store')->group(function () {
    Route::post('/student/lookup', [StudentDashboardController::class, 'lookupIndex'])->name('student.lookup');
    Route::get('/student/login/password', [StudentDashboardController::class, 'showPasswordForm'])->name('student.login.password.form');
    Route::post('/student/login/password', [StudentDashboardController::class, 'authenticateWithPassword'])->name('student.login.password');
    Route::get('/student/login/cancel', [StudentDashboardController::class, 'cancelPendingLogin'])->name('student.login.cancel');
    Route::get('/student/set-password', [StudentDashboardController::class, 'setPasswordForm'])->name('student.set-password');
    Route::post('/student/set-password', [StudentDashboardController::class, 'setPassword'])->name('student.set-password.post');
    Route::post('/student/logout', [StudentDashboardController::class, 'logout'])->name('student.logout');

    // Forgot password by email — students enter their index number, get a
    // 6-digit code in their inbox, then set a new password.
    Route::get('/student/recovery-email', [StudentDashboardController::class, 'emailPromptForm'])->name('student.email-prompt');
    Route::post('/student/recovery-email', [StudentDashboardController::class, 'emailPromptSubmit'])->name('student.email-prompt.submit');
    Route::get('/student/password/forgot', [StudentPasswordResetController::class, 'requestForm'])->name('student.password.request.form');
    Route::post('/student/password/forgot', [StudentPasswordResetController::class, 'sendCode'])->name('student.password.request.send');
    Route::get('/student/password/verify', [StudentPasswordResetController::class, 'verifyForm'])->name('student.password.verify.form');
    Route::post('/student/password/verify', [StudentPasswordResetController::class, 'confirm'])->name('student.password.confirm');
});

Route::middleware(['student.auth', 'student.session.integrity', 'no-store'])->group(function () {
    Route::get('/student/attendance-web', [StudentDashboardController::class, 'attendanceWebEntry'])->name('student.attendance.web');
    Route::get('/student/attendance-history', [StudentDashboardController::class, 'attendanceHistory'])->name('student.attendance.history');
    Route::get('/student/onboarding', [StudentDashboardController::class, 'onboardingForm'])->name('student.onboarding');
    Route::post('/student/onboarding', [StudentDashboardController::class, 'onboardingStore'])->name('student.onboarding.post');
    Route::get('/student/profile', [StudentDashboardController::class, 'profileForm'])->name('student.profile');
    Route::post('/student/profile', [StudentDashboardController::class, 'profileUpdate'])->name('student.profile.update');
});
Route::get('/student/departments/{faculty}', [StudentDashboardController::class, 'departmentsByFaculty'])->name('student.departments');

Route::get('/admin', [AdminAuthController::class, 'loginForm'])->name('admin.login');
Route::post('/admin', [AdminAuthController::class, 'login'])->name('admin.login.post');
Route::post('/admin/logout', [AdminAuthController::class, 'logout'])->name('admin.logout');

Route::get('/lecturer/login', [LecturerAuthController::class, 'loginForm'])->name('lecturer.login');
Route::post('/lecturer/login', [LecturerAuthController::class, 'login']);
Route::post('/lecturer/logout', [LecturerAuthController::class, 'logout'])->name('lecturer.logout');
Route::get('/lecturer/change-password', [LecturerAuthController::class, 'changePasswordForm'])->name('lecturer.password.change.form');
Route::post('/lecturer/change-password', [LecturerAuthController::class, 'changePassword'])->name('lecturer.password.change.post');

Route::middleware('lecturer')->prefix('lecturer')->name('lecturer.')->group(function () {
    Route::post('courses/{course}/weeks/{attendanceWeek}/cancel', [LecturerAttendanceWeekController::class, 'cancel'])->name('courses.week.cancel');
    Route::post('courses/{course}/weeks/{attendanceWeek}/uncancel', [LecturerAttendanceWeekController::class, 'uncancel'])->name('courses.week.uncancel');
});

Route::get('/onboarding/check', [StudentOnboardingController::class, 'check'])->name('onboarding.check');
Route::post('/onboarding/complete', [StudentOnboardingController::class, 'complete'])->name('onboarding.complete');

Route::prefix('dashboard')->middleware('no-store')->name('dashboard.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/students', [DashboardStudentsController::class, 'index'])->name('students.index');
    Route::get('/students/{student}', [DashboardStudentsController::class, 'show'])->name('students.show')->scopeBindings();
    Route::post('/students/{student}/reset-password', [DashboardStudentsController::class, 'resetPassword'])->name('students.reset-password')->scopeBindings();

    Route::middleware('student.auth')->get('/timetable', [DashboardTimetableController::class, 'show'])->name('timetable');

    // Course materials: any signed-in user (student, rep or lecturer) can
    // hit the index/download endpoints. The controller decides which
    // materials are visible and who is allowed to upload/delete based on
    // the session role, so we only require *some* signed-in session here.
    Route::middleware('signed-in-anybody')->group(function () {
        Route::get('/materials', [CourseMaterialController::class, 'index'])->name('materials.index');
        Route::get('/materials/{material}/download', [CourseMaterialController::class, 'download'])->name('materials.download');
        Route::post('/materials', [CourseMaterialController::class, 'store'])->name('materials.store');
        Route::delete('/materials/{material}', [CourseMaterialController::class, 'destroy'])->name('materials.destroy');
    });

    Route::middleware('classrep')->group(function () {
        Route::get('/timetable/manage', [ClassRepTimetableController::class, 'index'])->name('timetable.manage');
        Route::post('/timetable', [ClassRepTimetableController::class, 'store'])->name('timetable.store');
        Route::put('/timetable/{entry}', [ClassRepTimetableController::class, 'update'])
            ->name('timetable.update')
            ->whereNumber('entry');
        Route::delete('/timetable/{entry}', [ClassRepTimetableController::class, 'destroy'])
            ->name('timetable.destroy')
            ->whereNumber('entry');

        Route::get('/session', [ClassRepController::class, 'dashboard'])->name('session');
        Route::get('/my-class', [ClassRepController::class, 'classShow'])->name('my-class');
        Route::get('/class-attendance', [ClassRepController::class, 'attendanceIndex'])->name('class-attendance.index');
        Route::get('/class-attendance/course/{course}', [ClassRepController::class, 'attendanceForCourse'])->name('class-attendance.course');
        Route::get('/class-attendance/course/{course}/pdf', [AttendancePdfController::class, 'export'])->name('class-attendance.course.pdf');
        Route::get('/class-attendance/course/{course}/weeks/{attendanceWeek}/pdf', [AttendancePdfController::class, 'exportWeek'])->name('class-attendance.course.week.pdf');
        Route::get('/class-attendance/course/{course}/export.json', [ClassRepController::class, 'exportAttendanceJson'])->name('class-attendance.course.export-json');
        Route::post('/class-attendance/course/{course}/import.json', [ClassRepController::class, 'importAttendanceJson'])->name('class-attendance.course.import-json');
        Route::get('/class-attendance/course/{course}/weeks/{attendanceWeek}/export.json', [ClassRepController::class, 'exportAttendanceJsonWeek'])->name('class-attendance.course.week.export-json');
        Route::post('/class-attendance/course/{course}/weeks/{attendanceWeek}/import.json', [ClassRepController::class, 'importAttendanceJsonWeek'])->name('class-attendance.course.week.import-json');
        Route::post('/class-attendance/course/{course}/weeks/{attendanceWeek}/cancel', [ClassRepController::class, 'cancelAttendanceWeek'])->name('class-attendance.week.cancel');
        Route::post('/class-attendance/course/{course}/weeks/{attendanceWeek}/uncancel', [ClassRepController::class, 'uncancelAttendanceWeek'])->name('class-attendance.week.uncancel');
        Route::post('/class-attendance/course/{course}/weeks/{attendanceWeek}/rename', [ClassRepController::class, 'renameAttendanceWeek'])->name('class-attendance.week.rename');
        // Rep manually marks a student attendance with a reason.
        Route::post('/class-attendance/course/{course}/weeks/{attendanceWeek}/manual-mark', [ClassRepController::class, 'manualMarkAttendance'])->name('class-attendance.manual-mark');
        // Rep deletes a single attendance row (only when super admin has enabled it).
        Route::delete('/class-attendance/{attendance}', [ClassRepController::class, 'deleteAttendance'])->name('class-attendance.delete');
        // Read-only audit log scoped to courses / classes this rep manages.
        // Audit logs are admin-only (see /dashboard/audit-logs below).
        // Older deploys exposed a rep view here; it has been removed
        // because reps shouldn't see other classes' login / device
        // events even when scoped, and admins are the only role
        // accountable for inspecting the trail.

        // Rep bulk-imports a class roster (index numbers, optionally names).
        Route::post('/rep/students/import', [ClassRepController::class, 'importStudents'])->name('rep.students.import');
        Route::post('/live-sessions', [ClassRepController::class, 'openSession'])->name('live-sessions.store');
        Route::get('/live-sessions/{session}/close', [ClassRepController::class, 'closeSessionConfirm'])->name('live-sessions.close.confirm');
        Route::post('/live-sessions/{session}/close', [ClassRepController::class, 'closeSession'])->name('live-sessions.close');
        Route::get('/live-sessions/{session}/qr', [ClassRepController::class, 'qr'])->name('live-sessions.qr')->scopeBindings();
        Route::get('/live-sessions/{session}/qr-stats', [ClassRepController::class, 'qrStats'])->name('live-sessions.qr-stats')->scopeBindings();
        Route::get('/live-sessions/{session}/qr-payload', [ClassRepController::class, 'qrPayload'])->name('live-sessions.qr-payload')->scopeBindings();
        Route::get('/live-sessions/{session}/qr-download', [ClassRepController::class, 'qrDownload'])->name('live-sessions.qr-download')->scopeBindings();
    });

    Route::permanentRedirect('reps', '/dashboard');
    Route::permanentRedirect('reps/session', '/dashboard/session');
    Route::permanentRedirect('reps/class', '/dashboard/my-class');
    Route::permanentRedirect('reps/timetable', '/dashboard/timetable');
    Route::permanentRedirect('reps/attendances', '/dashboard/class-attendance');
    Route::permanentRedirect('reps/students', '/dashboard/students');
    Route::get('reps/students/{student}', function (Student $student) {
        return redirect(route('dashboard.students.show', $student), 301);
    })->scopeBindings();

    Route::get('reps/attendances/course/{course}', function (Course $course) {
        return redirect(route('dashboard.class-attendance.course', $course), 301);
    })->scopeBindings();
    Route::get('reps/courses/{course}/attendance/pdf', function (Course $course) {
        return redirect(route('dashboard.class-attendance.course.pdf', $course), 301);
    })->scopeBindings();
    Route::get('reps/sessions/{session}/close', function (AttendanceSession $session) {
        return redirect(route('dashboard.live-sessions.close.confirm', $session), 301);
    })->scopeBindings();
    Route::get('reps/sessions/{session}/qr', function (AttendanceSession $session) {
        return redirect(route('dashboard.live-sessions.qr', $session), 301);
    })->scopeBindings();
    Route::get('reps/sessions/{session}/qr-stats', function (AttendanceSession $session) {
        return redirect(route('dashboard.live-sessions.qr-stats', $session), 301);
    })->scopeBindings();
    Route::get('reps/sessions/{session}/qr-payload', function (AttendanceSession $session) {
        return redirect(route('dashboard.live-sessions.qr-payload', $session), 301);
    })->scopeBindings();
    Route::get('reps/sessions/{session}/qr-download', function (AttendanceSession $session) {
        return redirect(route('dashboard.live-sessions.qr-download', $session), 301);
    })->scopeBindings();

    Route::middleware('admin')->group(function () {
        Route::get('/portal', [AttendanceSessionController::class, 'index'])->name('portal');
        Route::post('/sessions', [AttendanceSessionController::class, 'store'])->name('sessions.store');
        Route::post('/sessions/{session}/close', [AttendanceSessionController::class, 'close'])->name('sessions.close')->scopeBindings();
        Route::get('/sessions/{session}/qr', [AttendanceSessionController::class, 'qr'])->name('sessions.qr')->scopeBindings();
        Route::get('/sessions/{session}/qr-download', [AttendanceSessionController::class, 'qrDownload'])->name('sessions.qr-download')->scopeBindings();

        Route::get('/profile', [DashboardProfileController::class, 'edit'])->name('profile.edit');
        Route::post('/profile', [DashboardProfileController::class, 'update'])->name('profile.update');
        Route::middleware('admin.only')->get('/attendances', [AdminController::class, 'attendances'])->name('attendances');
        Route::resource('courses', CourseController::class)->except(['show']);
        Route::get('/attendance-weeks', [AdminAttendanceWeekController::class, 'index'])->name('attendance-weeks.index');
        Route::post('/attendance-weeks/next-course', [AdminAttendanceWeekController::class, 'setNextForCourse'])->name('attendance-weeks.next-course');
        Route::post('/attendance-weeks/next-class', [AdminAttendanceWeekController::class, 'setNextForClass'])->name('attendance-weeks.next-class');
        Route::post('/attendance-weeks/reset-course', [AdminAttendanceWeekController::class, 'resetCourse'])->name('attendance-weeks.reset-course');
        Route::post('/attendance-weeks/reset-class', [AdminAttendanceWeekController::class, 'resetClass'])->name('attendance-weeks.reset-class');
        Route::post('/attendance-weeks/reset-all', [AdminAttendanceWeekController::class, 'resetAll'])->name('attendance-weeks.reset-all');
        Route::post('/attendance-weeks/dedupe', [AdminAttendanceWeekController::class, 'dedupeWeeklyMarks'])->name('attendance-weeks.dedupe');
        Route::get('/pdf/{course}', [AttendancePdfController::class, 'export'])->name('pdf.export');
        Route::post('/students/{student}/assign-rep', [StudentController::class, 'assignRep'])->name('students.assign-rep')->scopeBindings();
        Route::post('/students/{student}/remove-rep', [StudentController::class, 'removeRep'])->name('students.remove-rep')->scopeBindings();
        Route::middleware('admin.only')->delete('/students/{student}', [StudentController::class, 'destroy'])->name('students.destroy')->scopeBindings();
        Route::post('/students', [StudentController::class, 'store'])->name('students.store');
        Route::post('/students/import', [StudentController::class, 'import'])->name('students.import');
        Route::get('/my-classes', [\App\Http\Controllers\LecturerClassController::class, 'index'])->name('my-classes.index');
        Route::prefix('teaching')->name('teaching.')->group(function () {
            Route::get('/attendance', [\App\Http\Controllers\LecturerAttendanceController::class, 'index'])->name('attendance.index');
            Route::get('/attendance/course/{course}', [\App\Http\Controllers\LecturerAttendanceController::class, 'forCourse'])->name('attendance.course')->scopeBindings();
            Route::get('/attendance/course/{course}/pdf', [AttendancePdfController::class, 'export'])->name('attendance.course.pdf')->scopeBindings();
            Route::get('/attendance/course/{course}/export.json', [\App\Http\Controllers\LecturerAttendanceController::class, 'exportJson'])->name('attendance.course.export-json')->scopeBindings();
            Route::post('/attendance/course/{course}/import.json', [\App\Http\Controllers\LecturerAttendanceController::class, 'importJson'])->name('attendance.course.import-json')->scopeBindings();
        });
        Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::post('/settings', [SettingsController::class, 'update'])->name('settings.update');
        Route::middleware('admin.only')->get('/audit-logs', [AuditLogController::class, 'adminIndex'])->name('audit-logs.index');
        Route::middleware('admin.only')->prefix('api/mobile-app')->name('api.mobile-app.')->group(function () {
            Route::get('dashboard-themes', [MobileDashboardThemeController::class, 'show'])->name('dashboard-themes.show');
            Route::match(['put', 'patch'], 'dashboard-themes', [MobileDashboardThemeController::class, 'update'])->name('dashboard-themes.update');
        });
        Route::resource('venues', VenueController::class)->except(['show']);
        Route::middleware('admin.only')->prefix('staff-accounts')->name('staff-accounts.')->group(function () {
            Route::get('/', [StaffAccountController::class, 'index'])->name('index');
            Route::get('/create', [StaffAccountController::class, 'create'])->name('create');
            Route::post('/', [StaffAccountController::class, 'store'])->name('store');
            Route::post('/lecturers/{lecturer}/reset-password', [StaffAccountController::class, 'resetLecturerPassword'])->name('lecturers.reset-password');
            Route::delete('/lecturers/{lecturer}', [StaffAccountController::class, 'removeLecturerAccount'])->name('lecturers.destroy');
            Route::get('/admins/{user}/edit', [StaffAccountController::class, 'editAdmin'])->name('admins.edit');
            Route::put('/admins/{user}', [StaffAccountController::class, 'updateAdmin'])->name('admins.update');
            Route::delete('/admins/{user}', [StaffAccountController::class, 'destroyAdmin'])->name('admins.destroy');
        });
        Route::resource('lecturers', LecturerController::class)->except(['show']);
        Route::resource('universities', UniversityController::class)->except(['show']);
        Route::get('/classes', [ClassController::class, 'index'])->name('classes.index');
        Route::get('/classes/create', [ClassController::class, 'create'])->name('classes.create');
        Route::post('/classes', [ClassController::class, 'store'])->name('classes.store');
        Route::get('/classes/{schoolClass}', [ClassController::class, 'show'])->name('classes.show');
        Route::post('/classes/{schoolClass}/students', [ClassController::class, 'storeStudent'])->name('classes.students.store');
        Route::post('/classes/{schoolClass}/students/import', [ClassController::class, 'importStudents'])->name('classes.students.import');
        Route::get('/classes/{schoolClass}/edit', [ClassController::class, 'edit'])->name('classes.edit');
        Route::put('/classes/{schoolClass}', [ClassController::class, 'update'])->name('classes.update');
        Route::delete('/classes/{schoolClass}', [ClassController::class, 'destroy'])->name('classes.destroy');

        Route::get('/semesters', [SemesterController::class, 'index'])->name('semesters.index');
        Route::get('/semesters/create', [SemesterController::class, 'create'])->name('semesters.create');
        Route::post('/semesters', [SemesterController::class, 'store'])->name('semesters.store');
        Route::get('/semesters/{semester}/edit', [SemesterController::class, 'edit'])->name('semesters.edit');
        Route::put('/semesters/{semester}', [SemesterController::class, 'update'])->name('semesters.update');
        Route::delete('/semesters/{semester}', [SemesterController::class, 'destroy'])->name('semesters.destroy');
    });
});
