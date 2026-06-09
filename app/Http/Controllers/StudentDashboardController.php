<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\AttendanceWeek;
use App\Models\ClassTimetable;
use App\Models\Course;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\Student;
use App\Models\SystemSetting;
use App\Services\AuditLogService;
use App\Services\StudentAttendanceHistoryBuilder;
use App\Services\StudentSessionGuardService;
use App\Support\AttendanceSessionClassScope;
use App\Support\CacheVersions;
use App\Support\PasswordPolicy;
use App\Support\SchemaFeatures;
use App\Support\StudentSignOutLock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class StudentDashboardController extends Controller
{
    /**
     * Step 1: index number only — route to set-password (first time) or password step.
     */
    public function lookupIndex(Request $request): RedirectResponse
    {
        // If an authenticated student tries to start a new sign-in (e.g. by
        // tapping Back to a cached page and re-submitting), keep them where
        // they are instead of letting them re-auth as a different account.
        if ($request->session()->has('student_id')) {
            return redirect()->route('dashboard.dashboard');
        }

        $validated = $request->validate([
            'index_number' => 'required|string|max:64',
        ]);
        $indexNumber = strtoupper(trim($validated['index_number']));

        $student = Student::findByIndex($indexNumber);
        if (! $student) {
            return redirect()->route('home')->with('error', 'We couldn’t find a student account for that ID. Double-check and try again.');
        }

        $request->session()->forget(['pending_password_login_index', 'pending_set_password_index']);

        $settings = SystemSetting::get();
        if (empty($student->password)) {
            if ($settings->require_password_on_first_login ?? true) {
                $request->session()->put('pending_set_password_index', $student->index_number);

                return redirect()->route('student.set-password');
            }
            $request->session()->regenerate();
            session(['student_id' => $student->id, 'student_index' => $student->index_number]);
            app(StudentSessionGuardService::class)->startSession($student->id, $request);
            AuditLogService::record(AuditLogService::STUDENT_LOGIN, [
                'request' => $request,
                'subject_type' => 'student',
                'subject_id' => $student->id,
                'class_id' => $student->class_id,
                'payload' => ['index_number' => $student->index_number, 'first_login' => true],
            ]);

            return $this->redirectAfterStudentAuth($student);
        }

        $request->session()->put('pending_password_login_index', $student->index_number);

        return redirect()->route('student.login.password.form');
    }

    public function showPasswordForm(Request $request): View|RedirectResponse
    {
        if ($request->session()->has('student_id')) {
            return redirect()->route('dashboard.dashboard');
        }

        $indexNumber = $request->session()->get('pending_password_login_index');
        if (! $indexNumber || ! Student::findByIndex($indexNumber)) {
            $request->session()->forget('pending_password_login_index');

            return redirect()->route('home')->with('info', 'Please start from the sign-in page.');
        }

        return view('student.login-password', [
            'indexNumber' => $indexNumber,
        ]);
    }

    public function authenticateWithPassword(Request $request): RedirectResponse
    {
        if ($request->session()->has('student_id')) {
            return redirect()->route('dashboard.dashboard');
        }

        $validated = $request->validate([
            'password' => 'required|string',
        ]);

        $indexNumber = $request->session()->get('pending_password_login_index');
        if (! $indexNumber) {
            return redirect()->route('home')->with('info', 'Please start from the sign-in page.');
        }

        $student = Student::findByIndex($indexNumber);
        if (! $student || empty($student->password)) {
            $request->session()->forget('pending_password_login_index');

            return redirect()->route('home')->with('error', 'This step timed out. Please sign in again from the start.');
        }

        if (! PasswordPolicy::matches($validated['password'], $student->password)) {
            return redirect()->back()->withInput()->with('error', 'That password doesn’t match. Try again.');
        }

        $request->session()->forget('pending_password_login_index');
        // Rotate the underlying session id on login so prior tokens for this
        // browser can't be replayed.
        $request->session()->regenerate();
        session(['student_id' => $student->id, 'student_index' => $student->index_number]);

        app(StudentSessionGuardService::class)->startSession($student->id, $request);
        AuditLogService::record(AuditLogService::STUDENT_LOGIN, [
            'request' => $request,
            'subject_type' => 'student',
            'subject_id' => $student->id,
            'class_id' => $student->class_id,
            'payload' => ['index_number' => $student->index_number],
        ]);

        return $this->redirectAfterStudentAuth($student);
    }

    /**
     * Abandon password or set-password step and return to home (index entry).
     */
    public function cancelPendingLogin(Request $request): RedirectResponse
    {
        $request->session()->forget(['pending_password_login_index', 'pending_set_password_index']);

        // If they were already signed in (and just tapped Back into the
        // login flow), don't send them out to the public home page.
        if ($request->session()->has('student_id')) {
            return redirect()->route('dashboard.dashboard');
        }

        return redirect()->route('home');
    }

    public function logout(Request $request)
    {
        $studentId = $request->session()->get('student_id');
        if ($studentId) {
            $student = Student::find($studentId);
            if ($student && StudentSignOutLock::isSignOutBlocked($student)) {
                // Keep the student inside their authenticated area while a
                // class is in session, so they cannot sign back in as a
                // different account during the attendance window.
                return redirect()->route('dashboard.dashboard')->with(
                    'error',
                    StudentSignOutLock::blockMessage($student)
                        ?? 'Sign out is unavailable while a class is in session.'
                );
            }
        }

        if ($studentId) {
            AuditLogService::record(AuditLogService::STUDENT_LOGOUT, [
                'request' => $request,
                'subject_type' => 'student',
                'subject_id' => (int) $studentId,
            ]);
            app(StudentSessionGuardService::class)->revoke((int) $studentId, $request);
        }

        $request->session()->forget([
            'student_id',
            'student_index',
            'pending_password_login_index',
            'pending_set_password_index',
        ]);

        return redirect()->route('home');
    }

    public function dashboard(Request $request): View|RedirectResponse
    {
        $studentId = $request->session()->get('student_id');
        if (! $studentId) {
            return redirect()->route('home')->with('info', 'Please sign in to open your dashboard.');
        }

        $student = Student::with('department.faculty')->find($studentId);
        if (! $student) {
            $request->session()->forget('student_id');

            return redirect()->route('home')->with('error', 'Your session ended. Please sign in again.');
        }

        if ($student->needsBasicOnboarding()) {
            return redirect()->route('student.onboarding');
        }

        if (! $student->hasCompletedProfile()) {
            return redirect()->route('student.profile');
        }

        // Drop attendance rows whose backing week was cancelled or reset
        // so the student's headline numbers match what the rep, lecturer,
        // and PDF views show. Also restrict to sessions that were opened
        // for THIS student's class — historical reps were sometimes auto
        // marked into other classes' sessions and those rows must not be
        // counted against this student's totals.
        $baseAttendance = function () use ($student) {
            $q = Attendance::query()
                ->where('student_id', $student->id)
                ->activeWeeksOnly();
            if ($student->class_id) {
                AttendanceSessionClassScope::scopeAttendanceMarksForClasses(
                    $q,
                    [(int) $student->class_id]
                );
            }

            return $q;
        };

        // Bundle the four count() queries into a single short-TTL cache
        // entry keyed by student. Marks made by this student bump the
        // 'attendance:student:{id}' namespace, so the next read fetches
        // fresh numbers without hammering the DB on every refresh.
        $countsCacheKey = CacheVersions::key(
            'student_tiles:'.(int) $student->id.':'.now()->toDateString(),
            ['attendance:student:'.(int) $student->id]
        );
        $weekStart = now()->startOfWeek();
        $todayDate = now()->toDateString();
        try {
            $tiles = Cache::remember(
                $countsCacheKey,
                30,
                function () use ($baseAttendance, $weekStart, $todayDate): array {
                    return [
                        'total_present' => $baseAttendance()
                            ->countedAsPresent()
                            ->count(),
                        'courses_attended' => $baseAttendance()
                            ->countedAsPresent()
                            ->distinct()
                            ->count('course_id'),
                        'today_count' => $baseAttendance()
                            ->countedAsPresent()
                            ->whereDate('attendance_time', $todayDate)
                            ->distinct()
                            ->count('course_id'),
                        'week_count' => $baseAttendance()
                            ->countedAsPresent()
                            ->where('attendance_time', '>=', $weekStart)
                            ->distinct()
                            ->count('course_id'),
                    ];
                }
            );
        } catch (\Throwable $e) {
            $tiles = [
                'total_present' => $baseAttendance()->countedAsPresent()->count(),
                'courses_attended' => $baseAttendance()->countedAsPresent()->distinct()->count('course_id'),
                'today_count' => $baseAttendance()->countedAsPresent()->whereDate('attendance_time', $todayDate)->distinct()->count('course_id'),
                'week_count' => $baseAttendance()->countedAsPresent()->where('attendance_time', '>=', $weekStart)->distinct()->count('course_id'),
            ];
        }
        $totalPresent = (int) ($tiles['total_present'] ?? 0);
        $coursesAttended = (int) ($tiles['courses_attended'] ?? 0);
        $todayCount = (int) ($tiles['today_count'] ?? 0);
        $weekCount = (int) ($tiles['week_count'] ?? 0);

        // How many courses the student is enrolled in via their class —
        // gives them a denominator so "X / Y courses" makes sense.
        $totalCoursesEnrolled = $student->class_id
            ? Course::query()->forManagedClasses([(int) $student->class_id])->count()
            : 0;

        // Today's scheduled classes from the per-class timetable (if any).
        // Falls back to the legacy course.day_of_week columns when the
        // class doesn't yet have rep-managed timetable rows.
        $todaysClasses = $this->collectTodaysScheduledClasses($student);

        $liveAttendanceSessions = $this->collectLiveAttendanceSessionsForStudent($student)
            ->filter(fn (array $row) => ! $row['already_marked'])
            ->values();
        $settings = SystemSetting::get();
        $studentDashboardTheme = SystemSetting::hasStudentDashboardThemeColumn()
            ? (string) ($settings->student_dashboard_theme ?: 'classic')
            : 'classic';

        $cancelledWeeks = collect();
        if ($student->class_id) {
            $cancelledWeeks = AttendanceWeek::query()
                ->whereIn('course_id', Course::query()->forManagedClasses([$student->class_id])->select('id'))
                ->whereNotNull('cancelled_at')
                ->with(['course:id,course_name,course_code'])
                ->orderByDesc('week_date')
                ->limit(30)
                ->get();
        }

        // ---- Per-course attendance summary cards ------------------------
        // Every card on the carousel reads against the SAME denominator
        // — the class's configured semester length in weeks — so the
        // dashboard never displays inconsistent fractions like "1/1 wks"
        // next to "1/4 wks". Admins set this on the class create/edit
        // form; we fall back to the project default if unset or when
        // the schema feature isn't deployed yet.
        $courseSummaries = collect();
        if ($student->class_id) {
            try {
                $studentClass = \App\Models\SchoolClass::query()->find((int) $student->class_id);
                $semesterWeeks = \App\Support\SchemaFeatures::hasClassesSemesterWeeks() && $studentClass
                    ? $studentClass->resolvedSemesterWeeks()
                    : \App\Models\SchoolClass::DEFAULT_SEMESTER_WEEKS;

                $enrolledCourses = Course::query()
                    ->forManagedClasses([(int) $student->class_id])
                    ->with(['lecturer:id,name', 'venueRelation:id,name'])
                    ->orderBy('course_name')
                    ->limit(8)
                    ->get();

                if ($enrolledCourses->isNotEmpty()) {
                    $courseIds = $enrolledCourses->pluck('id')->all();
                    $presentByCourse = Attendance::query()
                        ->where('student_id', $student->id)
                        ->whereIn('course_id', $courseIds)
                        ->countedAsPresent()
                        ->select('course_id', DB::raw('COUNT(*) as n'))
                        ->groupBy('course_id')
                        ->pluck('n', 'course_id');

                    // "Where the class is in the semester" — class-wide,
                    // not per-course. Resolution order:
                    //
                    //   1. Calendar-based (admin override, then
                    //      semester_start_date math on SchoolClass).
                    //      This is the source of truth when configured.
                    //   2. Activity-based fallback: highest week_number
                    //      across this class's attendance_weeks rows
                    //      (cancelled weeks count — reaching week 5
                    //      still means we're in week 5). Used when no
                    //      calendar dates are set yet.
                    //
                    // Either way the value is shared across every
                    // course card so the user sees one truth.
                    $currentSemesterWeek = null;
                    if (\App\Support\SchemaFeatures::hasClassesSemesterDates() && $studentClass) {
                        $currentSemesterWeek = $studentClass->computeCurrentSemesterWeek();
                    }
                    if ($currentSemesterWeek === null) {
                        try {
                            $weekQuery = DB::table('attendance_weeks')
                                ->whereIn('course_id', $courseIds);
                            if (\App\Support\SchemaFeatures::hasAttendanceWeeksClassId()) {
                                $weekQuery->where(function ($q) use ($student) {
                                    $q->where('class_id', (int) $student->class_id)
                                      ->orWhereNull('class_id');
                                });
                            }
                            $currentSemesterWeek = (int) ($weekQuery->max('week_number') ?? 0);
                        } catch (\Throwable $e) {
                            report($e);
                            $currentSemesterWeek = 0;
                        }
                    }
                    // Don't pretend we're past the configured term.
                    $currentSemesterWeek = min((int) $currentSemesterWeek, (int) $semesterWeeks);

                    $courseSummaries = $enrolledCourses->map(function (Course $c) use ($presentByCourse, $semesterWeeks, $currentSemesterWeek) {
                        $present = (int) ($presentByCourse[$c->id] ?? 0);
                        $weeks = max(1, (int) $semesterWeeks);
                        // Cap present at the configured semester length
                        // so the fraction never reads "5/4 wks" if a
                        // course over-runs the configured term.
                        $presentDisplay = min($present, $weeks);
                        $pct = min(100, (int) round(($presentDisplay / $weeks) * 100));
                        // "Weeks left" is now semester-aware, not
                        // attendance-aware: if we're in week 5 of 14,
                        // 9 weeks remain whether you were marked or
                        // not. This fixes the bug where a student with
                        // 0 marks in week 5 was told "14 weeks left".
                        $remaining = max(0, $weeks - $currentSemesterWeek);

                        return [
                            'id' => $c->id,
                            'name' => $c->course_name,
                            'code' => $c->course_code,
                            'lecturer' => $c->lecturer?->name ?: null,
                            'venue' => $c->venueRelation?->name,
                            'present' => $presentDisplay,
                            'weeks' => $weeks,
                            'current_week' => $currentSemesterWeek,
                            'remaining' => $remaining,
                            'pct' => $pct,
                        ];
                    })->values();
                }
            } catch (\Throwable $e) {
                report($e);
                $courseSummaries = collect();
            }
        }

        // ---- Recent activity feed (last 8 attendance marks) -------------
        $recentActivity = collect();
        try {
            $recentActivity = Attendance::query()
                ->where('student_id', $student->id)
                ->with(['course:id,course_name,course_code'])
                ->latest('attendance_time')
                ->limit(8)
                ->get()
                ->map(function (Attendance $a) {
                    $status = (string) ($a->status ?? 'present');

                    return [
                        'id' => $a->id,
                        'course_name' => $a->course?->course_name ?? 'Course',
                        'course_code' => $a->course?->course_code,
                        'status' => $status,
                        'time' => $a->attendance_time,
                    ];
                });
        } catch (\Throwable $e) {
            report($e);
        }

        // Pick the soonest upcoming class that is starting WITHIN the
        // next hour — the mobile dashboard surfaces a live countdown
        // for it. We compute this server-side so the view stays dumb
        // and the JS only has to tick a timer.
        $nextClassWithin1Hour = $this->resolveImminentClass($todaysClasses);

        return view('student.dashboard', compact(
            'student',
            'totalPresent',
            'coursesAttended',
            'totalCoursesEnrolled',
            'todayCount',
            'weekCount',
            'todaysClasses',
            'liveAttendanceSessions',
            'cancelledWeeks',
            'studentDashboardTheme',
            'courseSummaries',
            'recentActivity',
            'nextClassWithin1Hour'
        ));
    }

    /**
     * From today's scheduled classes, return the next one that starts
     * within the upcoming 60 minutes — used by the mobile dashboard to
     * render a live "Starts in N min" countdown card immediately after
     * the courses strip.
     *
     * Skips classes the student has already marked, and any class
     * whose status is `live` (the live-sessions banner at the top of
     * the dashboard already covers that case more loudly).
     *
     * @param  Collection<int, array<string, mixed>>  $todaysClasses
     * @return array{course_name: string, course_code: ?string, lecturer: ?string, venue: ?string, start_iso: string, end_iso: ?string, is_active: bool, minutes_until: int}|null
     */
    private function resolveImminentClass(Collection $todaysClasses): ?array
    {
        if ($todaysClasses->isEmpty()) {
            return null;
        }

        $now = now();
        $cutoff = $now->copy()->addHour();

        $candidate = null;
        $candidateStart = null;
        $candidateEnd = null;
        $candidateIsActive = false;

        foreach ($todaysClasses as $slot) {
            $start = $this->scheduleTimeForToday($slot['start_raw'] ?? null);
            if ($start === null) {
                continue;
            }
            // Skip slots the student has already been counted for, and
            // slots where a session is already live (the top-of-page
            // live banner covers those more loudly).
            if (in_array($slot['status'] ?? '', ['marked', 'live'], true)) {
                continue;
            }

            // Compute end with a sensible fallback: if the timetable
            // doesn't carry an end_raw, assume a 60-minute slot. This
            // keeps the "active class · ends in" countdown functional
            // even for the legacy course.day_of_week path.
            $end = $this->scheduleTimeForToday($slot['end_raw'] ?? null);
            if ($end === null || $end->lessThanOrEqualTo($start)) {
                $end = $start->copy()->addHour();
            }

            // Two ways the slot is relevant for the card:
            //   1. It's about to start (within the next 60 minutes).
            //   2. It's currently in progress (start <= now < end) —
            //      so the same card can flip to "class is happening
            //      right now · ends in MM:SS" without disappearing.
            $isUpcoming = $start->greaterThanOrEqualTo($now) && $start->lessThanOrEqualTo($cutoff);
            $isActive = $start->lessThanOrEqualTo($now) && $end->greaterThan($now);
            if (! $isUpcoming && ! $isActive) {
                continue;
            }

            // Prefer an in-progress class over an upcoming one (it's
            // the more urgent thing to surface). Within the same
            // category, pick the earliest start time.
            $beatsCurrent = false;
            if ($candidate === null) {
                $beatsCurrent = true;
            } elseif ($isActive && ! $candidateIsActive) {
                $beatsCurrent = true;
            } elseif ($isActive === $candidateIsActive && $start->lessThan($candidateStart)) {
                $beatsCurrent = true;
            }
            if ($beatsCurrent) {
                $candidate = $slot;
                $candidateStart = $start;
                $candidateEnd = $end;
                $candidateIsActive = $isActive;
            }
        }

        if ($candidate === null || $candidateStart === null) {
            return null;
        }

        return [
            'course_name' => (string) ($candidate['course']->course_name ?? 'Class'),
            'course_code' => $candidate['course']->course_code ?? null,
            'lecturer' => $candidate['lecturer'] ?? null,
            'venue' => $candidate['venue'] ?? null,
            'start_iso' => $candidateStart->toIso8601String(),
            'end_iso' => $candidateEnd?->toIso8601String(),
            'is_active' => $candidateIsActive,
            'minutes_until' => max(0, (int) ceil($now->diffInSeconds($candidateStart, false) / 60)),
        ];
    }

    /**
     * Build today's expected classes for the student from the per-class
     * timetable, falling back to the legacy course.day_of_week column.
     * Returns at most a handful of rows for display — one per scheduled
     * slot today, sorted by start time.
     *
     * Each row now carries a context-aware `status`:
     *   marked   — student has already been counted as present today
     *   live     — there is an active session for this course right now
     *   upcoming — the slot's start time is in the future
     *   missed   — the slot's end time is in the past and no mark exists
     *   pending  — fallback (mid-slot, no live session yet)
     *
     * Previously every non-marked row was labelled "Pending", which was
     * misleading for upcoming classes (rep hasn't opened anything yet)
     * and for classes that ended hours ago without a mark.
     *
     * @return Collection<int, array{course: Course, start: ?string, end: ?string, start_raw: ?string, end_raw: ?string, lecturer: ?string, venue: ?string, marked: bool, status: string, status_label: string}>
     */
    private function collectTodaysScheduledClasses(Student $student): Collection
    {
        if (! $student->class_id) {
            return collect();
        }

        $today = strtolower(now()->format('l'));
        $classId = (int) $student->class_id;
        $rows = collect();

        if (SchemaFeatures::hasClassTimetables()) {
            $timetableRows = ClassTimetable::query()
                ->where('class_id', $classId)
                ->whereRaw('LOWER(day_of_week) = ?', [$today])
                ->with(['course', 'lecturer', 'venueRelation'])
                ->orderBy('start_time')
                ->get();
            foreach ($timetableRows as $row) {
                if (! $row->course) {
                    continue;
                }
                $lecturerName = trim((string) ($row->lecturer?->name ?? ''));
                if ($lecturerName === '') {
                    $lecturerName = trim((string) ($row->course->resolvedLecturerName() ?? ''));
                }
                $rows->push([
                    'course' => $row->course,
                    'start' => $this->formatScheduleTime($row->start_time),
                    'end' => $this->formatScheduleTime($row->end_time),
                    'start_raw' => $row->start_time,
                    'end_raw' => $row->end_time,
                    'lecturer' => $lecturerName !== '' ? $lecturerName : null,
                    'venue' => $row->venueRelation?->name ?? null,
                    'marked' => false,
                    'status' => 'pending',
                    'status_label' => 'Pending',
                ]);
            }
        }

        if ($rows->isEmpty()) {
            $courses = Course::query()
                ->forManagedClasses([$classId])
                ->whereRaw('LOWER(day_of_week) = ?', [$today])
                ->orderBy('start_time')
                ->with(['venueRelation', 'lecturer'])
                ->get();
            foreach ($courses as $course) {
                $rows->push([
                    'course' => $course,
                    'start' => $this->formatScheduleTime($course->start_time),
                    'end' => $this->formatScheduleTime($course->end_time),
                    'start_raw' => $course->start_time,
                    'end_raw' => $course->end_time,
                    'lecturer' => trim((string) ($course->resolvedLecturerName() ?? '')) ?: null,
                    'venue' => $course->venueRelation?->name ?? ($course->venue ?: null),
                    'marked' => false,
                    'status' => 'pending',
                    'status_label' => 'Pending',
                ]);
            }
        }

        if ($rows->isEmpty()) {
            return $rows;
        }

        // Flag which of today's slots the student has already been
        // marked present for, so the UI can label them as done.
        $courseIds = $rows->pluck('course.id')->filter()->unique()->values()->all();
        $presentTodayQuery = Attendance::query()
            ->where('student_id', $student->id)
            ->activeWeeksOnly()
            ->countedAsPresent()
            ->whereDate('attendance_time', now()->toDateString())
            ->whereIn('course_id', $courseIds);
        if ($student->class_id) {
            AttendanceSessionClassScope::scopeAttendanceMarksForClasses(
                $presentTodayQuery,
                [(int) $student->class_id]
            );
        }
        $presentToday = $presentTodayQuery
            ->pluck('course_id')
            ->map(fn ($id) => (int) $id)
            ->flip();

        $now = now();

        return $rows->map(function (array $r) use ($presentToday, $now, $classId) {
            $courseId = (int) ($r['course']->id ?? 0);
            $marked = $presentToday->has($courseId);
            $r['marked'] = $marked;

            // Parse schedule times for THIS calendar day (the raw values are
            // typically TIME columns like "09:00:00" with no date).
            $start = $this->scheduleTimeForToday($r['start_raw'] ?? null);
            $end = $this->scheduleTimeForToday($r['end_raw'] ?? null);

            if ($marked) {
                $r['status'] = 'marked';
                $r['status_label'] = 'Marked';

                return $r;
            }

            // Is there actually a live session for this course + class right
            // now? If so, this is the only state where the student can
            // realistically act, and "Live" / "Open now" is far more useful
            // than the generic "Pending".
            $hasLiveSession = false;
            try {
                $hasLiveSession = $r['course']->activeSessionForClass($classId) !== null;
            } catch (\Throwable $e) {
                report($e);
            }
            if ($hasLiveSession) {
                $r['status'] = 'live';
                $r['status_label'] = 'Open now';

                return $r;
            }

            if ($start !== null && $now->lt($start)) {
                $r['status'] = 'upcoming';
                $r['status_label'] = 'Upcoming';

                return $r;
            }

            if ($end !== null && $now->gt($end)) {
                $r['status'] = 'missed';
                $r['status_label'] = 'Missed';

                return $r;
            }

            // Mid-slot but the rep hasn't opened a session yet — leave it
            // as Pending so the student knows they still have a chance.
            $r['status'] = 'pending';
            $r['status_label'] = 'Pending';

            return $r;
        });
    }

    /**
     * Convert a TIME-only schedule value (e.g. "09:00:00") into a Carbon
     * datetime anchored on TODAY. Returns null if the value can't be
     * parsed — caller treats that as "no boundary".
     */
    private function scheduleTimeForToday(mixed $value): ?Carbon
    {
        if (! $value) {
            return null;
        }
        try {
            $parsed = Carbon::parse((string) $value);

            return now()->copy()
                ->setTime($parsed->hour, $parsed->minute, $parsed->second);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function formatScheduleTime(mixed $value): ?string
    {
        if (! $value) {
            return null;
        }
        try {
            return Carbon::parse((string) $value)->format('g:i A');
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function attendanceHistory(Request $request): View|RedirectResponse
    {
        $studentId = $request->session()->get('student_id');
        if (! $studentId) {
            return redirect()->route('home')->with('info', 'Please sign in to view attendance history.');
        }

        $student = Student::with('department.faculty')->find($studentId);
        if (! $student) {
            $request->session()->forget('student_id');

            return redirect()->route('home')->with('error', 'Your session ended. Please sign in again.');
        }

        if ($student->needsBasicOnboarding()) {
            return redirect()->route('student.onboarding');
        }

        if (! $student->hasCompletedProfile()) {
            return redirect()->route('student.profile');
        }

        $built = app(StudentAttendanceHistoryBuilder::class)->build($student);
        $history = $built['history'];
        $presentCount = $built['presentCount'];
        $absentCount = $built['absentCount'];
        $totalSessions = $built['totalSessions'];
        $attendanceRate = $built['attendanceRate'];
        $courseStats = $built['courseStats'];
        $trend = $built['trend'];

        // Hide attendance rows whose backing week has been cancelled or
        // wiped during an admin reset — those still live in the DB for
        // audit, but shouldn't surface as "missed class" entries. Also
        // exclude marks tied to sessions opened for another class
        // (legacy rep auto-mark across all assigned classes).
        $applyClassScope = function ($q) use ($student) {
            if ($student->class_id) {
                AttendanceSessionClassScope::scopeAttendanceMarksForClasses(
                    $q,
                    [(int) $student->class_id]
                );
            }
        };

        $attendancesQuery = Attendance::where('student_id', $student->id)
            ->activeWeeksOnly()
            ->with(['course', 'attendanceWeek'])
            ->orderByDesc('attendance_time')
            ->limit(50);
        $applyClassScope($attendancesQuery);
        $attendances = $attendancesQuery->get();

        $byCourseQuery = Attendance::where('student_id', $student->id)
            ->countedAsPresent()
            ->activeWeeksOnly()
            ->join('courses', 'attendances.course_id', '=', 'courses.id')
            ->select('courses.course_name', 'courses.course_code', DB::raw('COUNT(*) as count'))
            ->groupBy('courses.id', 'courses.course_name', 'courses.course_code');
        $applyClassScope($byCourseQuery);
        $byCourse = $byCourseQuery->get();

        $byWeekQuery = Attendance::where('student_id', $student->id)
            ->countedAsPresent()
            ->activeWeeksOnly()
            ->join('attendance_weeks', 'attendances.attendance_week_id', '=', 'attendance_weeks.id')
            ->whereNull('attendance_weeks.cancelled_at')
            ->select('attendance_weeks.week_number', DB::raw('COUNT(*) as count'))
            ->groupBy('attendance_weeks.week_number')
            ->orderBy('attendance_weeks.week_number');
        $applyClassScope($byWeekQuery);
        $byWeek = $byWeekQuery->get();

        return view('student.attendance-history', compact(
            'student',
            'history',
            'presentCount',
            'absentCount',
            'totalSessions',
            'attendanceRate',
            'attendances',
            'byCourse',
            'byWeek',
            'courseStats',
            'trend'
        ));
    }

    public function profileForm(Request $request): View|RedirectResponse
    {
        $studentId = $request->session()->get('student_id');
        if (! $studentId) {
            return redirect()->route('home')->with('info', 'Please sign in to manage your profile.');
        }

        $student = Student::with(['department.faculty', 'schoolClass.department', 'schoolClass.faculty'])->find($studentId);
        if (! $student) {
            $request->session()->forget('student_id');

            return redirect()->route('home')->with('error', 'Your session ended. Please sign in again.');
        }

        if ($student->class_id && ! $student->department_id) {
            $class = $student->schoolClass;
            if ($class?->department_id) {
                $student->update(['department_id' => $class->department_id]);

                return redirect()->route('dashboard.dashboard')->with('success', 'Profile updated from your class.');
            }
        }

        $faculties = $this->getUniqueFacultiesWithDepartments();

        $rawFacultyId = old('faculty_id') ?? $student->department?->faculty_id ?? $student->schoolClass?->faculty_id;
        $rawDepartmentId = old('department_id') ?? $student->department_id ?? $student->schoolClass?->department_id;
        $prefillFacultyId = $this->resolveCanonicalFacultyId($faculties, $rawFacultyId);
        $prefillDepartmentId = $rawDepartmentId;

        if ($student->needsBasicOnboarding()) {
            return redirect()->route('student.onboarding');
        }

        $profileLayout = $student->isRep() ? 'classrep' : 'student';

        return view('student.profile', compact('student', 'faculties', 'prefillFacultyId', 'prefillDepartmentId', 'profileLayout'));
    }

    public function onboardingForm(Request $request): View|RedirectResponse
    {
        $studentId = $request->session()->get('student_id');
        if (! $studentId) {
            return redirect()->route('home')->with('info', 'Please sign in to continue.');
        }

        $student = Student::find($studentId);
        if (! $student) {
            $request->session()->forget('student_id');

            return redirect()->route('home')->with('error', 'Your session ended. Please sign in again.');
        }

        $settings = SystemSetting::get();
        $requirePhoto = $settings->require_profile_image_on_onboarding ?? true;

        if (! $student->needsBasicOnboarding($requirePhoto)) {
            return redirect()->route(
                $student->hasCompletedProfile()
                    ? 'dashboard.dashboard'
                    : 'student.profile'
            );
        }

        $layout = $student->isRep() ? 'classrep' : 'student';
        $missingFields = $student->missingBasicOnboardingFields($requirePhoto);

        return view('student.onboarding', compact('student', 'layout', 'missingFields'));
    }

    public function onboardingStore(Request $request): RedirectResponse
    {
        $studentId = $request->session()->get('student_id');
        if (! $studentId) {
            return redirect()->route('home')->with('info', 'Please sign in to continue.');
        }

        $student = Student::find($studentId);
        if (! $student) {
            $request->session()->forget('student_id');

            return redirect()->route('home')->with('error', 'Your session ended. Please sign in again.');
        }

        $settings = SystemSetting::get();
        $requirePhoto = $settings->require_profile_image_on_onboarding ?? true;
        $missing = $student->missingBasicOnboardingFields($requirePhoto);
        if ($missing === []) {
            return redirect()->route(
                $student->hasCompletedProfile()
                    ? 'dashboard.dashboard'
                    : 'student.profile'
            );
        }

        $rules = [];
        foreach ($missing as $field) {
            if ($field === 'phone_number') {
                $rules['phone_number'] = 'required|string|min:10|max:30';
            } elseif ($field === 'profile_image') {
                $rules['profile_photo'] = 'required|image|mimes:jpg,jpeg,png,webp|max:10240';
            } else {
                $rules[$field] = 'required|string|max:255';
            }
        }

        $validated = $request->validate($rules);

        if (isset($validated['first_name'])) {
            $student->first_name = $validated['first_name'];
        }
        if (isset($validated['last_name'])) {
            $student->last_name = $validated['last_name'];
        }
        if (isset($validated['phone_number'])) {
            $student->phone_number = preg_replace('/[^0-9+]/', '', $validated['phone_number']);
        }
        if (in_array('profile_image', $missing, true) && $request->hasFile('profile_photo')) {
            if (! $student->saveProfileImageFromUpload($request->file('profile_photo'))) {
                return redirect()->back()->withInput()->with('error', 'Could not process that profile image. Use a clear JPG, PNG, or WEBP photo.');
            }
        }
        $student->save();

        if (! $student->hasCompletedProfile()) {
            return redirect()->route('student.profile')->with('success', 'Now choose your faculty and department.');
        }

        return redirect()->route('dashboard.dashboard')
            ->with('success', 'Welcome to a-tenda!');
    }

    /**
     * After login / password set: onboarding → profile (if needed) → dashboard.
     */
    /**
     * “Mark attendance” (sidebar / web): jump straight into the attendance flow when there is exactly one open session.
     */
    public function attendanceWebEntry(Request $request): RedirectResponse
    {
        $studentId = $request->session()->get('student_id');
        if (! $studentId) {
            return redirect()->route('home')->with('info', 'Please sign in to mark attendance.');
        }

        $student = Student::find($studentId);
        if (! $student) {
            $request->session()->forget('student_id');

            return redirect()->route('home')->with('error', 'Your session ended. Please sign in again.');
        }

        if ($student->needsBasicOnboarding()) {
            return redirect()->route('student.onboarding');
        }

        if (! $student->hasCompletedProfile()) {
            return redirect()->route('student.profile');
        }

        if ($student->isRep()) {
            return redirect()->route('dashboard.dashboard')->with('info', 'Open session from your dashboard when you’re running a class; use Mark attendance when you’re marking as a student.');
        }

        $rows = $this->collectLiveAttendanceSessionsForStudent($student);
        $unmarked = $rows->filter(fn (array $r) => ! $r['already_marked']);

        if ($unmarked->isEmpty()) {
            return redirect()->route('dashboard.dashboard')->with('info', 'No open attendance session to mark right now.');
        }

        if ($unmarked->count() === 1) {
            return redirect()->route('web.attendance.form', $unmarked->first()['course']);
        }

        return redirect()->route('dashboard.dashboard')->with('info', 'Choose your course below to mark attendance.');
    }

    /**
     * @return Collection<int, array{session: AttendanceSession, course: Course, already_marked: bool}>
     */
    private function collectLiveAttendanceSessionsForStudent(Student $student): Collection
    {
        if (! $student->class_id) {
            return collect();
        }

        $query = AttendanceSession::query()
            ->whereHas('course', fn ($q) => $q->forManagedClasses([$student->class_id]))
            ->activeWithinTimeWindow()
            ->where(function ($q) {
                $q->whereNull('attendance_week_id')
                    ->orWhereHas('attendanceWeek', fn ($w) => $w->whereNull('cancelled_at'));
            });

        AttendanceSessionClassScope::applyForClass($query, (int) $student->class_id);

        return $query
            ->with(['course.lecturer', 'course.venueRelation', 'attendanceWeek'])
            ->orderBy('expires_at')
            ->get()
            ->map(function (AttendanceSession $session) use ($student) {
                $attendance = Attendance::where('student_id', $student->id)
                    ->where('attendance_session_id', $session->id)
                    ->first();
                $alreadyMarked = $attendance !== null && Attendance::countsAsPresent($attendance->status);
                if ($session->isCheckInCheckoutMode()) {
                    $alreadyMarked = $attendance !== null
                        && Attendance::countsAsPresent($attendance->status)
                        && ! empty($attendance->check_out_time);
                }

                return [
                    'session' => $session,
                    'course' => $session->course,
                    'already_marked' => $alreadyMarked,
                    'my_attendance' => $attendance,
                ];
            });
    }

    private function redirectAfterStudentAuth(Student $student): RedirectResponse
    {
        if ($student->needsBasicOnboarding()) {
            return redirect()->route('student.onboarding');
        }

        if (! $student->hasCompletedProfile()) {
            return redirect()->route('student.profile');
        }

        // First login since we added the password-reset-via-email flow:
        // if mail delivery is configured and this student never set an
        // email, gently prompt them once per login so future forgotten
        // passwords can be recovered. They can skip and continue —
        // we only ask once per session.
        if ($this->shouldPromptForRecoveryEmail($student)) {
            return redirect()->route('student.email-prompt');
        }

        if ($student->isRep()) {
            return redirect()->route('dashboard.dashboard');
        }

        $rows = $this->collectLiveAttendanceSessionsForStudent($student);
        $unmarked = $rows->filter(fn (array $r) => ! $r['already_marked']);
        if ($unmarked->count() === 1) {
            return redirect()->route('web.attendance.form', $unmarked->first()['course']);
        }

        return redirect()->route('dashboard.dashboard');
    }

    /**
     * Decide whether the post-login funnel should pause on the email
     * prompt. We only ask if the student really has no email saved AND
     * they haven't already dismissed the prompt in this browser session.
     */
    private function shouldPromptForRecoveryEmail(Student $student): bool
    {
        if (! SchemaFeatures::hasStudentsEmail()) {
            return false;
        }

        if (trim((string) ($student->email ?? '')) !== '') {
            return false;
        }

        if (session()->get('recovery_email_prompt_dismissed') === true) {
            return false;
        }

        return true;
    }

    /**
     * Show a one-question form asking the student for a recovery email.
     * Optional — the "Skip for now" button just marks the prompt as
     * dismissed for this session and continues to the dashboard.
     */
    public function emailPromptForm(Request $request)
    {
        $studentId = $request->session()->get('student_id');
        if (! $studentId) {
            return redirect()->route('home')->with('info', 'Please sign in to continue.');
        }

        $student = Student::find($studentId);
        if (! $student) {
            return redirect()->route('home');
        }

        // If the student already has an email, or the column hasn't
        // been migrated on this deploy, skip the prompt entirely.
        if (! SchemaFeatures::hasStudentsEmail()
            || trim((string) ($student->email ?? '')) !== '') {
            return redirect()->route('dashboard.dashboard');
        }

        return view('student.email-prompt', ['student' => $student]);
    }

    /**
     * Save the recovery email (or dismiss the prompt) and continue.
     */
    public function emailPromptSubmit(Request $request): RedirectResponse
    {
        $studentId = $request->session()->get('student_id');
        if (! $studentId) {
            return redirect()->route('home')->with('info', 'Please sign in to continue.');
        }

        $student = Student::find($studentId);
        if (! $student) {
            return redirect()->route('home');
        }

        if ($request->boolean('skip')) {
            $request->session()->put('recovery_email_prompt_dismissed', true);

            return redirect()->route('dashboard.dashboard')
                ->with('info', 'Got it. You can add an email any time from your profile.');
        }

        $validated = $request->validate([
            'email' => 'required|email|max:255',
        ]);

        if (SchemaFeatures::hasStudentsEmail()) {
            $student->forceFill(['email' => mb_strtolower(trim($validated['email']))])->save();
        }
        $request->session()->put('recovery_email_prompt_dismissed', true);

        return redirect()->route('dashboard.dashboard')
            ->with('success', 'Email saved. If you forget your password we can email you a reset code.');
    }

    /**
     * Dashboard avatar uploader. Receives a base64 PNG/JPEG produced
     * by the in-browser cropper, persists it synchronously (we don't
     * want users on shared hosting to depend on a queue worker), and
     * returns the new public URL so the dashboard can swap the avatar
     * without reloading.
     */
    public function profileImageUpdate(Request $request): JsonResponse
    {
        $studentId = $request->session()->get('student_id');
        if (! $studentId) {
            return response()->json(['ok' => false, 'error' => 'Session expired. Please sign in again.'], 401);
        }

        $student = Student::find($studentId);
        if (! $student) {
            $request->session()->forget('student_id');

            return response()->json(['ok' => false, 'error' => 'Account not found. Please sign in again.'], 401);
        }

        $validated = $request->validate([
            // ~7 MB cap on the data URL itself; the model still runs
            // its own dimension + raw-byte guard after decoding.
            'image_data' => ['required', 'string', 'min:64', 'max:7340032'],
        ]);

        if (! preg_match('#^data:image/(jpeg|jpg|png|webp);base64,#i', (string) $validated['image_data'])) {
            return response()->json(['ok' => false, 'error' => 'Image must be a JPEG, PNG or WEBP data URL.'], 422);
        }

        if (! $student->saveProfileImageFromBase64Sync($validated['image_data'])) {
            return response()->json(['ok' => false, 'error' => "Could not process that image. Try a clearer JPG, PNG or WEBP."], 422);
        }

        // Touch updated_at so profileImageUrl()'s ?v= cache buster
        // flips and the browser refetches the new bytes.
        $student->save();

        return response()->json([
            'ok' => true,
            'url' => $student->profileImageUrl(),
        ]);
    }

    public function profileUpdate(Request $request)
    {
        $studentId = $request->session()->get('student_id');
        if (! $studentId) {
            return redirect()->route('home')->with('info', 'Please sign in to update your profile.');
        }

        $student = Student::find($studentId);
        if (! $student) {
            $request->session()->forget('student_id');

            return redirect()->route('home')->with('error', 'Your session ended. Please sign in again.');
        }

        $settings = SystemSetting::get();
        $requirePhoto = $settings->require_profile_image_on_onboarding ?? true;

        // Identity (names, faculty, department) is set ONCE — during
        // the initial onboarding flow that runs out of this same page
        // — and locked forever after. Students and class reps must not
        // be able to rename themselves or jump faculties / departments
        // on the live system (per spec). The view hides the fields
        // when the profile is already complete, and this server-side
        // gate is the source of truth: any spoofed POST containing
        // first_name / faculty_id / department_id from a fully-
        // onboarded account is silently ignored.
        $identityLocked = $student->hasCompletedProfile();

        $rules = [
            'phone_number' => 'nullable|string|max:30',
            'profile_photo' => ($requirePhoto && ! $student->profile_image)
                ? ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240']
                : ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ];
        if (! $identityLocked) {
            $rules += [
                'first_name' => 'required|string|max:255',
                'middle_name' => 'nullable|string|max:255',
                'last_name' => 'required|string|max:255',
                'faculty_id' => 'required|exists:faculties,id',
                'department_id' => 'required|exists:departments,id',
            ];
        }
        if (SchemaFeatures::hasStudentsEmail()) {
            $rules['email'] = 'nullable|email|max:255';
        }
        $validated = $request->validate($rules);

        if (! $identityLocked && isset($validated['department_id'], $validated['faculty_id'])) {
            $department = Department::find($validated['department_id']);
            if ($department && $department->faculty_id != $validated['faculty_id']) {
                return redirect()->back()->with('error', 'That department doesn’t match the faculty you selected.');
            }
        }

        if ($request->hasFile('profile_photo')) {
            if (! $student->saveProfileImageFromUpload($request->file('profile_photo'))) {
                return redirect()->back()->withInput()->with('error', 'Could not process that profile image. Use a clear JPG, PNG, or WEBP photo.');
            }
        }

        if (array_key_exists('phone_number', $validated) && $validated['phone_number'] !== null && $validated['phone_number'] !== '') {
            $student->phone_number = preg_replace('/[^0-9+]/', '', $validated['phone_number']);
        }

        $payload = [];
        if (! $identityLocked) {
            $middle = $validated['middle_name'] ?? null;
            $payload['first_name'] = $validated['first_name'];
            $payload['middle_name'] = ($middle !== null && trim((string) $middle) !== '') ? trim((string) $middle) : null;
            $payload['last_name'] = $validated['last_name'];
            $payload['department_id'] = $validated['department_id'];
        }
        if (SchemaFeatures::hasStudentsEmail() && array_key_exists('email', $validated)) {
            $email = $validated['email'] !== null ? trim((string) $validated['email']) : '';
            $payload['email'] = $email !== '' ? mb_strtolower($email) : null;
        }
        if ($payload !== []) {
            $student->fill($payload);
        }
        $student->save();

        return redirect()->route('dashboard.dashboard')->with('success', 'Profile updated');
    }

    private function getUniqueFacultiesWithDepartments(): Collection
    {
        return Faculty::with('departments')->orderBy('name')->get()->map(function ($f) {
            return (object) [
                'id' => $f->id,
                'name' => $f->name,
                'departments' => $f->departments->sortBy('name')->map(fn ($d) => (object) ['id' => $d->id, 'name' => $d->name])->values()->all(),
            ];
        });
    }

    private function resolveCanonicalFacultyId(?Collection $faculties, ?int $facultyId): ?int
    {
        return $facultyId;
    }

    public function departmentsByFaculty(int $facultyId)
    {
        $departments = Department::where('faculty_id', $facultyId)->orderBy('name')->get(['id', 'name']);

        return response()->json($departments);
    }

    public function setPasswordForm(Request $request): View|RedirectResponse
    {
        if ($request->session()->has('student_id')) {
            return redirect()->route('dashboard.dashboard');
        }

        $indexNumber = $request->session()->get('pending_set_password_index');
        if (! $indexNumber || ! Student::findByIndex($indexNumber)) {
            $request->session()->forget('pending_set_password_index');

            return redirect()->route('home')->with('info', 'Let’s start from the sign-in page.');
        }

        return view('student.set-password', compact('indexNumber'));
    }

    public function setPassword(Request $request): RedirectResponse
    {
        $indexNumber = $request->session()->get('pending_set_password_index');
        if (! $indexNumber) {
            return redirect()->route('home')->with('error', 'Something went wrong. Please start sign-in again.');
        }

        $validated = $request->validate([
            'password' => 'required|string|min:6|confirmed',
        ]);

        $student = Student::findByIndex($indexNumber);
        if (! $student) {
            $request->session()->forget('pending_set_password_index');

            return redirect()->route('home')->with('error', 'We couldn’t verify your account. Please try again.');
        }

        if (! empty($student->password)) {
            $request->session()->forget('pending_set_password_index');

            return redirect()->route('home')->with('error', 'You already have a password. Sign in from the start.');
        }

        $student->update(['password' => Hash::make($validated['password'])]);

        $request->session()->forget('pending_set_password_index');
        $request->session()->regenerate();
        session(['student_id' => $student->id, 'student_index' => $student->index_number]);
        app(StudentSessionGuardService::class)->startSession($student->id, $request);
        AuditLogService::record(AuditLogService::STUDENT_LOGIN, [
            'request' => $request,
            'subject_type' => 'student',
            'subject_id' => $student->id,
            'class_id' => $student->class_id,
            'payload' => ['index_number' => $student->index_number, 'first_login' => true, 'via' => 'set_password'],
        ]);

        return $this->redirectAfterStudentAuth($student)->with('success', 'You’re all set. Welcome to a-tenda!');
    }
}
