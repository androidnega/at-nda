<?php

namespace App\Http\Controllers;

use App\Events\SessionLiveEvent;
use App\Imports\StudentsImport;
use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\AttendanceWeek;
use App\Models\ClassTimetable;
use App\Models\Course;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\SystemSetting;
use App\Models\Venue;
use App\Services\AuditLogService;
use App\Services\ClassSessionScopeService;
use App\Services\FcmNotificationService;
use App\Support\AttendanceSessionClassScope;
use App\Support\CacheVersions;
use App\Support\ClassTimetableAccess;
use App\Support\LiveAttendanceCache;
use App\Support\RepCourseAccess;
use App\Support\SchemaFeatures;
use App\Support\SecureQrToken;
use App\Support\SessionQrPng;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClassRepController extends Controller
{
    private function getStudent(Request $request): ?Student
    {
        $id = $request->session()->get('student_id');
        if (! $id) {
            return null;
        }

        return Student::find($id);
    }

    private function requireClassRep(Request $request): Student|RedirectResponse
    {
        $student = $this->getStudent($request);
        if (! $student) {
            return redirect()->route('home')->with('info', 'Please sign in to continue.');
        }
        $classIds = $this->getRepClassIds($student);
        if ($classIds->isEmpty()) {
            return redirect()->route('dashboard.dashboard')->with('error', 'You are not a class rep');
        }

        return $student;
    }

    private function requireMainRep(Student $student, int $courseId): bool
    {
        $course = Course::find($courseId);

        return $course && RepCourseAccess::isMainRepForCourse($student, $course);
    }

    private function repCanAccessCourse(Student $rep, Course $course): bool
    {
        return RepCourseAccess::canAccessCourse($rep, $course);
    }

    private function getRepClassIds(Student $rep): Collection
    {
        return $rep->repManagedClassIds();
    }

    /**
     * Class the rep is acting on behalf of for a given course (intersection of
     * the rep's managed classes and the course's assigned classes). Returns the
     * first matching class id so per-class attendance weeks are tracked correctly.
     */
    private function resolveRepClassId(Student $rep, Course $course): ?int
    {
        $scoped = RepCourseAccess::scopedClassIdsForCourse($rep, $course);

        return $scoped !== [] ? (int) $scoped[0] : null;
    }

    private function canAccessStudent(Student $rep, Student $target): bool
    {
        $classIds = $this->getRepClassIds($rep);

        return $target->class_id && $classIds->contains($target->class_id);
    }

    public function overview(Request $request): View|RedirectResponse
    {
        $student = $this->requireClassRep($request);
        if ($student instanceof RedirectResponse) {
            return $student;
        }

        // The rep dashboard renders five independent counters + a "today's
        // schedule" list. A single failing query (corrupted Cache return
        // after a Redis driver flip, missing column on an out-of-date
        // production schema, etc.) used to take the whole page to a 500.
        // We now isolate every section behind safeCount/safeCall so the
        // page renders zeros + an empty timetable instead, and report()
        // the exception so it surfaces in storage/logs/laravel.log for
        // post-mortem without locking the rep out of their dashboard.
        $classIds = $this->getRepClassIds($student);
        $classIdsArr = $classIds->map(fn ($id) => (int) $id)->all();

        $studentsCount = $this->safeCount(
            fn () => Student::whereIn('class_id', $classIds)->count(),
            'rep_overview.students_count'
        );

        // The rep dashboard now mirrors the per-class timetable: a course is
        // "assigned" only once the rep has added it to their own class
        // timetable. This keeps the Courses tile and Today's schedule in
        // sync with /dashboard/timetable.
        $useClassTimetable = $this->safeCall(
            fn () => SchemaFeatures::hasClassTimetables() && $classIdsArr !== [],
            'rep_overview.has_class_timetables',
            false
        );

        $timetableCourseIds = $useClassTimetable
            ? $this->safeCall(
                fn () => ClassTimetable::query()
                    ->whereIn('class_id', $classIdsArr)
                    ->whereNotNull('course_id')
                    ->pluck('course_id')
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->values(),
                'rep_overview.timetable_course_ids',
                collect()
            )
            : collect();

        $coursesCount = $this->safeCount(
            fn () => $useClassTimetable
                ? $timetableCourseIds->count()
                : (clone RepCourseAccess::coursesQueryForRep($student))->count(),
            'rep_overview.courses_count'
        );

        // Attendance counters stay scoped to every course assigned to the
        // rep's classes so historical marks aren't hidden if a slot was
        // later removed from the timetable.
        $courseIds = $this->safeCall(
            fn () => (clone RepCourseAccess::coursesQueryForRep($student))->pluck('id'),
            'rep_overview.course_ids',
            collect()
        );

        // All three dashboard counters skip attendance rows pointing at
        // cancelled / reset weeks so the headline numbers match what the
        // PDFs and per-course pages show.
        $marksBase = null;
        try {
            $marksBase = $courseIds->isEmpty()
                ? null
                : Attendance::query()
                    ->whereIn('course_id', $courseIds)
                    ->whereHas('student', fn ($q) => $q->whereIn('class_id', $classIds))
                    ->activeWeeksOnly();

            if ($marksBase !== null) {
                AttendanceSessionClassScope::scopeAttendanceMarksForClasses($marksBase, $classIdsArr);
            }
        } catch (\Throwable $e) {
            report($e);
            $marksBase = null;
        }

        $totalAttendanceMarks = $marksBase === null
            ? 0
            : $this->safeCount(fn () => (clone $marksBase)->count(), 'rep_overview.total_marks');

        $todayAttendanceMarks = $marksBase === null
            ? 0
            : $this->safeCount(
                fn () => (clone $marksBase)->whereDate('attendance_time', today())->count(),
                'rep_overview.today_marks'
            );

        $weekAttendanceMarks = $marksBase === null
            ? 0
            : $this->safeCount(
                fn () => (clone $marksBase)->where('attendance_time', '>=', now()->subDays(7)->startOfDay())->count(),
                'rep_overview.week_marks'
            );

        $todayCourses = $this->safeCall(
            fn () => $this->buildRepTodayCourses($student, $classIdsArr, $useClassTimetable),
            'rep_overview.today_courses',
            collect()
        );

        // ─── Charts & trends data for the dashboard ───────────────
        // All four are computed off the same $marksBase builder so we
        // automatically respect the rep's class scope + the
        // activeWeeksOnly() filter. Each lives behind a safeCall so a
        // single broken query never kills the whole page.
        $trendDays = 14;
        $weeklyTrend = $this->safeCall(
            fn () => $this->buildWeeklyTrend($marksBase, $trendDays),
            'rep_overview.weekly_trend',
            collect()
        );

        $modeBreakdown = $this->safeCall(
            fn () => $this->buildModeBreakdown($marksBase),
            'rep_overview.mode_breakdown',
            collect()
        );

        $topCourses = $this->safeCall(
            fn () => $this->buildTopCoursesForRep($marksBase),
            'rep_overview.top_courses',
            collect()
        );

        $topStudents = $this->safeCall(
            fn () => $this->buildTopStudentsForRep($marksBase),
            'rep_overview.top_students',
            collect()
        );

        return view('classrep.overview', [
            'student' => $student,
            'studentsCount' => $studentsCount,
            'coursesCount' => $coursesCount,
            'totalAttendanceMarks' => $totalAttendanceMarks,
            'todayAttendanceMarks' => $todayAttendanceMarks,
            'weekAttendanceMarks' => $weekAttendanceMarks,
            'todayCourses' => $todayCourses,
            'weeklyTrend' => $weeklyTrend,
            'modeBreakdown' => $modeBreakdown,
            'topCourses' => $topCourses,
            'topStudents' => $topStudents,
            'trendDays' => $trendDays,
            'dashboardRole' => 'classrep',
        ]);
    }

    /**
     * Build the daily attendance count series for the rep's classes.
     * Always returns exactly [$days] entries (zero-fills empty days)
     * so the bar chart never has gaps. Newest day last.
     *
     * @param  \Illuminate\Database\Eloquent\Builder|null  $marksBase
     */
    private function buildWeeklyTrend($marksBase, int $days = 14): Collection
    {
        $start = now()->subDays($days - 1)->startOfDay();
        $countsByDate = [];

        if ($marksBase !== null) {
            $rows = (clone $marksBase)
                ->where('attendance_time', '>=', $start)
                ->selectRaw('DATE(attendance_time) as d, COUNT(*) as c')
                ->groupBy('d')
                ->pluck('c', 'd')
                ->toArray();
            $countsByDate = $rows;
        }

        $out = collect();
        for ($i = $days - 1; $i >= 0; $i--) {
            $d = now()->subDays($i);
            $key = $d->format('Y-m-d');
            $out->push([
                'date' => $key,
                'label' => $d->format('D j'),
                'short' => $d->format('D'),
                'count' => (int) ($countsByDate[$key] ?? 0),
            ]);
        }

        return $out;
    }

    /**
     * Group marks by the originating session's capture mode for the
     * donut chart. Falls back to 'location' so legacy rows without a
     * session link still surface somewhere.
     *
     * @param  \Illuminate\Database\Eloquent\Builder|null  $marksBase
     */
    private function buildModeBreakdown($marksBase): Collection
    {
        if ($marksBase === null) {
            return collect();
        }

        $raw = (clone $marksBase)
            ->leftJoin('attendance_sessions', 'attendances.attendance_session_id', '=', 'attendance_sessions.id')
            ->selectRaw('COALESCE(attendance_sessions.mode, "location") as mode, COUNT(*) as total')
            ->groupBy('mode')
            ->pluck('total', 'mode')
            ->toArray();

        // Keep a fixed ordering so the chart palette stays stable.
        return collect(['location', 'qr', 'hybrid', 'wifi'])
            ->map(fn ($m) => [
                'mode' => $m,
                'label' => match ($m) {
                    'qr' => 'QR scan',
                    'hybrid' => 'Hybrid',
                    'wifi' => 'Wi-Fi',
                    default => 'Location',
                },
                'count' => (int) ($raw[$m] ?? 0),
            ])
            ->filter(fn ($r) => $r['count'] > 0)
            ->values();
    }

    /**
     * Top courses by attendance count within the rep's scope.
     *
     * @param  \Illuminate\Database\Eloquent\Builder|null  $marksBase
     */
    private function buildTopCoursesForRep($marksBase, int $limit = 5): Collection
    {
        if ($marksBase === null) {
            return collect();
        }

        return (clone $marksBase)
            ->join('courses', 'attendances.course_id', '=', 'courses.id')
            ->selectRaw('courses.id, courses.course_name, courses.course_code, COUNT(*) as total')
            ->groupBy('courses.id', 'courses.course_name', 'courses.course_code')
            ->orderByDesc('total')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => [
                'id' => (int) $r->id,
                'name' => (string) $r->course_name,
                'code' => (string) ($r->course_code ?? ''),
                'count' => (int) $r->total,
            ]);
    }

    /**
     * Leaderboard of the rep's students by attendance count for the
     * dashboard "Top performers" panel.
     *
     * @param  \Illuminate\Database\Eloquent\Builder|null  $marksBase
     */
    private function buildTopStudentsForRep($marksBase, int $limit = 5): Collection
    {
        if ($marksBase === null) {
            return collect();
        }

        return (clone $marksBase)
            ->join('students', 'attendances.student_id', '=', 'students.id')
            ->selectRaw('students.id, students.index_number, students.first_name, students.last_name, COUNT(*) as total')
            ->groupBy('students.id', 'students.index_number', 'students.first_name', 'students.last_name')
            ->orderByDesc('total')
            ->limit($limit)
            ->get()
            ->map(function ($r) {
                $name = trim(($r->first_name ?? '').' '.($r->last_name ?? ''));
                if ($name === '') {
                    $name = (string) ($r->index_number ?? 'Student');
                }

                return [
                    'id' => (int) $r->id,
                    'name' => $name,
                    'index' => (string) ($r->index_number ?? ''),
                    'count' => (int) $r->total,
                ];
            });
    }

    /**
     * Build the GeoJSON-ish point list that powers the rep dashboard
     * Leaflet map. Skips rows without coordinates (QR-only marks made
     * before geolocation was added) and projects each row into a flat
     * array the view can json_encode directly.
     *
     * @param  \Illuminate\Database\Eloquent\Builder|null  $marksBase
     */
    private function buildAttendanceMapPoints($marksBase): Collection
    {
        if ($marksBase === null) {
            return collect();
        }

        $tz = config('app.timezone');
        $rows = (clone $marksBase)
            ->whereNotNull('lat')
            ->whereNotNull('lng')
            ->where('attendance_time', '>=', now()->subDays(14)->startOfDay())
            ->with([
                'student:id,index_number,first_name,last_name',
                'course:id,course_name,course_code',
                'session:id,mode,location_lat,location_lng,attendance_range_m',
            ])
            ->orderByDesc('attendance_time')
            ->limit(500)
            ->get();

        return $rows->map(function (Attendance $a) use ($tz) {
            $lat = (float) $a->lat;
            $lng = (float) $a->lng;
            if ($lat === 0.0 && $lng === 0.0) {
                return null;
            }

            $studentName = trim((string) ($a->student?->first_name.' '.$a->student?->last_name));
            if ($studentName === '') {
                $studentName = (string) ($a->student?->index_number ?? 'Student');
            }

            $time = $a->attendance_time?->timezone($tz);

            return [
                'lat' => $lat,
                'lng' => $lng,
                'student' => $studentName,
                'index' => (string) ($a->student?->index_number ?? ''),
                'course' => (string) ($a->course?->course_name ?? '—'),
                'course_code' => (string) ($a->course?->course_code ?? ''),
                'course_id' => (int) $a->course_id,
                'session_id' => (int) ($a->attendance_session_id ?? 0),
                'mode' => (string) ($a->session?->mode ?? 'location'),
                'time' => $time?->format('M j, g:i A'),
                'time_iso' => $time?->toIso8601String(),
                'status' => (string) ($a->status ?? 'present'),
            ];
        })->filter()->values();
    }

    /**
     * Course-level anchor circles (centre + accuracy radius) drawn under
     * the per-student pins. Only courses with both coordinates set are
     * included; the radius defaults to 75 m when not configured.
     *
     * @param  \Illuminate\Support\Collection<int>  $courseIds
     */
    private function buildCourseAnchors(Collection $courseIds): Collection
    {
        if ($courseIds->isEmpty()) {
            return collect();
        }

        return Course::query()
            ->whereIn('id', $courseIds)
            ->whereNotNull('location_lat')
            ->whereNotNull('location_lng')
            ->get(['id', 'course_name', 'course_code', 'location_lat', 'location_lng', 'attendance_range_m'])
            ->map(fn (Course $c) => [
                'id' => (int) $c->id,
                'name' => (string) $c->course_name,
                'code' => (string) ($c->course_code ?? ''),
                'lat' => (float) $c->location_lat,
                'lng' => (float) $c->location_lng,
                'radius_m' => (int) ($c->attendance_range_m ?: 75),
            ])
            ->values();
    }

    /**
     * Run a counter query and fall back to 0 if it throws. Keeps the rep
     * dashboard usable when a single tile's query is broken (bad cache
     * value, missing column on a stale schema, transient DB hiccup).
     */
    private function safeCount(callable $fn, string $context): int
    {
        try {
            return (int) $fn();
        } catch (\Throwable $e) {
            report($e);
            Log::warning(
                'rep_overview_safe_count_failed',
                ['context' => $context, 'error' => $e->getMessage()]
            );

            return 0;
        }
    }

    /**
     * Generic safe wrapper for non-int values (collections, booleans). See
     * safeCount() for the rationale.
     *
     * @template T
     *
     * @param  callable(): T  $fn
     * @param  T  $fallback
     * @return T
     */
    private function safeCall(callable $fn, string $context, mixed $fallback): mixed
    {
        try {
            return $fn();
        } catch (\Throwable $e) {
            report($e);
            Log::warning(
                'rep_overview_safe_call_failed',
                ['context' => $context, 'error' => $e->getMessage()]
            );

            return $fallback;
        }
    }

    /**
     * Build a normalized list of today's slots for the rep's dashboard,
     * preferring per-class timetable entries and falling back to the legacy
     * course-level day/time columns when no per-class entries exist.
     *
     * @return Collection<int, object>
     */
    private function buildRepTodayCourses(Student $student, array $classIdsArr, bool $useClassTimetable): Collection
    {
        $todayName = strtolower(now()->format('l'));

        if ($useClassTimetable && $classIdsArr !== []) {
            $slots = ClassTimetable::query()
                ->whereIn('class_id', $classIdsArr)
                ->whereRaw('LOWER(TRIM(day_of_week)) = ?', [$todayName])
                ->with(['course', 'venueRelation', 'lecturer'])
                ->orderBy('start_time')
                ->limit(10)
                ->get();

            if ($slots->isNotEmpty()) {
                return $slots->map(function (ClassTimetable $slot) {
                    $course = $slot->course;
                    $scheduleParts = [];
                    try {
                        $start = Carbon::parse($slot->start_time)->format('H:i');
                        $end = Carbon::parse($slot->end_time)->format('H:i');
                        $scheduleParts[] = $start.'–'.$end;
                    } catch (\Throwable $e) {
                        // Ignore unparsable times — they'll just be omitted from the label.
                    }
                    $venueDisplay = $slot->resolvedVenueName();
                    if ($venueDisplay !== '') {
                        $scheduleParts[] = $venueDisplay;
                    }

                    return (object) [
                        'course_name' => $course?->course_name ?? '—',
                        'course_code' => $course?->course_code,
                        'schedule_label' => implode(' · ', $scheduleParts),
                        'has_active_session' => $course?->activeSessionForClass((int) $slot->class_id) !== null,
                    ];
                })->values();
            }
        }

        // Legacy fallback: course-level day/time when the rep has no per-class
        // timetable rows yet (or the schema doesn't support them).
        return RepCourseAccess::coursesQueryForRep($student)
            ->with(['schoolClass', 'schoolClasses', 'lecturer', 'venueRelation'])
            ->whereNotNull('day_of_week')
            ->whereRaw('LOWER(TRIM(day_of_week)) = ?', [$todayName])
            ->orderBy('start_time')
            ->limit(6)
            ->get()
            ->map(fn (Course $c) => (object) [
                'course_name' => $c->course_name,
                'course_code' => $c->course_code,
                'schedule_label' => $c->getScheduleLabel(),
                'has_active_session' => $c->activeSessionForClass($this->resolveRepClassId($student, $c)) !== null,
            ]);
    }

    /**
     * Dedicated full-page attendance map for the rep. Lives on its own
     * sidebar entry so the overview/dashboard can stay focused on
     * trends + counters instead of also hosting a tall map widget.
     *
     * Supports a `?days=` filter (7/14/30/90, defaulting to 14) so the
     * rep can broaden or tighten the window without leaving the page.
     */
    public function attendanceMap(Request $request): View|RedirectResponse
    {
        $student = $this->requireClassRep($request);
        if ($student instanceof RedirectResponse) {
            return $student;
        }

        $allowedWindows = [7, 14, 30, 90];
        $days = (int) $request->query('days', 14);
        if (! in_array($days, $allowedWindows, true)) {
            $days = 14;
        }

        $classIds = $this->getRepClassIds($student);
        $classIdsArr = $classIds->map(fn ($id) => (int) $id)->all();

        $courseIds = $this->safeCall(
            fn () => (clone RepCourseAccess::coursesQueryForRep($student))->pluck('id'),
            'rep_map.course_ids',
            collect()
        );

        $marksBase = null;
        try {
            $marksBase = $courseIds->isEmpty()
                ? null
                : Attendance::query()
                    ->whereIn('course_id', $courseIds)
                    ->whereHas('student', fn ($q) => $q->whereIn('class_id', $classIds))
                    ->activeWeeksOnly()
                    ->where('attendance_time', '>=', now()->subDays($days)->startOfDay());

            if ($marksBase !== null) {
                AttendanceSessionClassScope::scopeAttendanceMarksForClasses($marksBase, $classIdsArr);
            }
        } catch (\Throwable $e) {
            report($e);
            $marksBase = null;
        }

        $attendanceMapPoints = $this->safeCall(
            fn () => $this->buildAttendanceMapPoints($marksBase),
            'rep_map.map_points',
            collect()
        );

        $courseAnchors = $this->safeCall(
            fn () => $this->buildCourseAnchors($courseIds),
            'rep_map.course_anchors',
            collect()
        );

        return view('classrep.attendance-map', [
            'student' => $student,
            'attendanceMapPoints' => $attendanceMapPoints,
            'courseAnchors' => $courseAnchors,
            'days' => $days,
            'allowedWindows' => $allowedWindows,
            'dashboardRole' => 'classrep',
        ]);
    }

    public function dashboard(Request $request): View|RedirectResponse
    {
        $student = $this->requireClassRep($request);
        if ($student instanceof RedirectResponse) {
            return $student;
        }

        // /dashboard/session — same hardening as overview(). Each of the
        // three queries below can independently fail (corrupted Cache,
        // stale schema, missing pivot column) and we want the page to
        // still render with whatever data we *can* fetch.
        $courses = $this->safeCall(
            fn () => RepCourseAccess::coursesQueryForRep($student)
                ->with([
                    'schoolClass.faculty.university',
                    'schoolClasses',
                    'attendanceSessions' => fn ($q) => $q->where('is_active', true),
                ])
                ->orderBy('course_name')
                ->get()
                ->map(function (Course $c) use ($student) {
                    $repClassId = $this->resolveRepClassId($student, $c);

                    return (object) [
                        'course' => $c,
                        'class_id' => $repClassId,
                        'role' => $this->safeCall(
                            fn () => RepCourseAccess::classRepForCourse($student, $c)?->role,
                            'rep_dashboard.role.'.$c->id,
                            null
                        ) ?? 'rep',
                        'canOpenSession' => $this->safeCall(
                            fn () => $this->requireMainRep($student, $c->id),
                            'rep_dashboard.main_rep.'.$c->id,
                            false
                        ),
                        'active_session' => $repClassId
                            ? $this->safeCall(
                                fn () => $c->activeSessionForClass($repClassId),
                                'rep_dashboard.active_session.'.$c->id,
                                null
                            )
                            : null,
                    ];
                }),
            'rep_dashboard.courses',
            collect()
        );

        $settings = $this->safeCall(
            fn () => SystemSetting::get(),
            'rep_dashboard.settings',
            null
        );

        $hasAttendanceModeColumns = $this->safeCall(
            fn () => SystemSetting::hasAttendanceModeColumns(),
            'rep_dashboard.has_attendance_mode_columns',
            false
        );

        $attendanceMode = ($hasAttendanceModeColumns && $settings)
            ? (string) ($settings->attendance_mode ?: SystemSetting::ATTENDANCE_MODE_INSTANT)
            : SystemSetting::ATTENDANCE_MODE_INSTANT;
        $instantModeType = ($hasAttendanceModeColumns && $settings)
            ? (string) ($settings->instant_mode_type ?: SystemSetting::INSTANT_MODE_LOCATION_QR)
            : SystemSetting::INSTANT_MODE_LOCATION_QR;

        // Venues let the rep override the timetable default for one session
        // (e.g. when class meets in a different room today).
        $venues = $this->safeCall(
            fn () => Venue::query()->orderBy('name')->get(),
            'rep_dashboard.venues',
            collect()
        );

        return view('classrep.dashboard', [
            'student' => $student,
            'courses' => $courses,
            'dashboardRole' => 'classrep',
            'attendanceMode' => $attendanceMode,
            'instantModeType' => $instantModeType,
            'venues' => $venues,
        ]);
    }

    /**
     * Telemetry sink for the open-session form's GPS cascade.
     *
     * The browser's geolocation API only reports a numeric error code
     * + a vague string ("kCLErrorLocationUnknown") and *never* gives
     * the server any visibility, so when a rep complains "GPS isn't
     * picking up" we previously had nothing to go on. This endpoint
     * accepts a small JSON envelope from the cascade and writes it
     * to the Laravel log with a `[GPS-DEBUG]` tag.
     *
     * Operator usage (PuTTY / SSH):
     *   tail -F storage/logs/laravel-$(date +%F).log | grep GPS-DEBUG
     *
     * Never throws — telemetry must never block the rep's actual
     * session-open flow.
     */
    public function logGpsDiag(Request $request): JsonResponse
    {
        try {
            $data = $request->validate([
                'event' => 'required|string|max:64',
                'step' => 'nullable|string|max:64',
                'code' => 'nullable|integer',
                'message' => 'nullable|string|max:300',
                'secure' => 'nullable|boolean',
                'permission' => 'nullable|string|max:32',
                'has_api' => 'nullable|boolean',
                'duration_ms' => 'nullable|integer|min:0|max:120000',
                'accuracy' => 'nullable|numeric',
                'ua_short' => 'nullable|string|max:120',
            ]);

            $student = $request->session()->has('student_id')
                ? Student::find($request->session()->get('student_id'))
                : null;

            Log::warning('[GPS-DEBUG] '.$data['event'], array_merge($data, [
                'rep_id' => $student?->id,
                'rep_index' => $student?->index_number,
                'ip' => $request->ip(),
            ]));
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json(['ok' => true]);
    }

    public function openSession(Request $request): RedirectResponse
    {
        $student = $this->requireClassRep($request);
        if ($student instanceof RedirectResponse) {
            return $student;
        }

        $settings = SystemSetting::get();
        $attendanceMode = SystemSetting::hasAttendanceModeColumns()
            ? (string) ($settings->attendance_mode ?: SystemSetting::ATTENDANCE_MODE_INSTANT)
            : SystemSetting::ATTENDANCE_MODE_INSTANT;
        $forcedMode = null;
        if ($attendanceMode === SystemSetting::ATTENDANCE_MODE_CHECKIN_CHECKOUT) {
            $forcedMode = 'location';
        } else {
            $instantType = SystemSetting::hasAttendanceModeColumns()
                ? (string) ($settings->instant_mode_type ?: SystemSetting::INSTANT_MODE_LOCATION_QR)
                : SystemSetting::INSTANT_MODE_LOCATION_QR;
            $forcedMode = match ($instantType) {
                SystemSetting::INSTANT_MODE_LOCATION => 'location',
                SystemSetting::INSTANT_MODE_WIFI => 'wifi',
                default => 'hybrid',
            };
        }

        $validated = $request->validate([
            'course_id' => 'required|exists:courses,id',
            'mode' => 'nullable|in:location,qr,hybrid,wifi',
            'lecturer_status' => 'required|in:present,absent',
            'duration_minutes' => 'nullable|integer|min:5|max:480',
            'location_lat' => 'nullable|numeric',
            'location_lng' => 'nullable|numeric',
            'attendance_range_m' => 'nullable|integer|min:1|max:500',
            'allowed_wifi_ssid' => 'required_if:mode,wifi|nullable|string|max:128',
            'week_number' => 'nullable|integer|min:1|max:500',
            'venue_id' => 'nullable|integer|exists:venues,id',
        ]);
        $validated['mode'] = $forcedMode;

        $course = Course::findOrFail($validated['course_id']);
        if (! $this->repCanAccessCourse($student, $course)) {
            abort(403, 'You can only open sessions for courses in your class.');
        }
        if (! $this->requireMainRep($student, $course->id)) {
            return back()->with('error', 'Only main reps can open sessions');
        }

        $repClassId = $this->resolveRepClassId($student, $course);
        if (! $course->hasScheduleForClass($repClassId)) {
            return back()->with('error', 'Add this course to your class timetable (day, time, lecturer) first.');
        }

        RepCourseAccess::deactivateSessionsForCourse($student, $course);

        $overrideWeekNumber = isset($validated['week_number']) && $validated['week_number'] !== null
            ? (int) $validated['week_number']
            : null;
        $week = $course->createOrGetAttendanceWeekForToday($repClassId, $overrideWeekNumber);

        $duration = (int) ($validated['duration_minutes'] ?? 60);
        $expectedEnd = $course->computeSessionExpiresAt($duration, $repClassId);
        $expiresAt = $expectedEnd->copy();
        $needsAnchor = in_array($validated['mode'], ['location', 'hybrid'], true);
        $wifiSsid = isset($validated['allowed_wifi_ssid']) ? trim((string) $validated['allowed_wifi_ssid']) : null;

        $lat = $validated['location_lat'] ?? null;
        $lng = $validated['location_lng'] ?? null;
        $range = $validated['attendance_range_m'] ?? null;
        if ($needsAnchor) {
            if (($lat === null || $lng === null || $range === null) && $course->hasDefaultSessionLocation()) {
                $lat = $course->location_lat;
                $lng = $course->location_lng;
                $range = $course->attendance_range_m;
            }
            if ($lat === null || $lng === null || $range === null) {
                return back()->with('error', 'Set a default location on the course (admin → Courses → edit) or use GPS / course location / manual coordinates when opening.');
            }
        }

        $snapshot = ClassTimetableAccess::resolveScheduleSnapshot($course, $repClassId);
        $sessionLecturerId = $snapshot['lecturer_id'] ?? $course->lecturer_id;
        // Optional venue override from the rep wins over the timetable default
        // for this single session (does not rewrite the timetable row).
        $overrideVenueId = isset($validated['venue_id']) && $validated['venue_id'] !== null
            ? (int) $validated['venue_id']
            : null;
        $sessionVenueId = $overrideVenueId ?? ($snapshot['venue_id'] ?? $course->venue_id);

        [$sessionModel, $wasReopened] = AttendanceSession::openOrReopenForClass(
            (int) $course->id,
            $repClassId ? (int) $repClassId : null,
            (int) $week->id,
            [
                'mode' => $validated['mode'],
                'attendance_mode' => $attendanceMode,
                'allowed_wifi_ssid' => $validated['mode'] === 'wifi' ? $wifiSsid : null,
                'checkout_enabled' => false,
                'session_token' => Str::random(32),
                'lecturer_id' => $sessionLecturerId,
                'venue_id' => $sessionVenueId,
                'start_time' => now(),
                'end_time' => $expiresAt,
                'expected_end_time' => $expectedEnd,
                'expires_at' => $expiresAt,
                'lecturer_status' => $validated['lecturer_status'],
                'location_lat' => $needsAnchor ? $lat : null,
                'location_lng' => $needsAnchor ? $lng : null,
                'attendance_range_m' => $needsAnchor ? $range : null,
            ]
        );

        ClassSessionScopeService::autoMarkClassRepsForSession($sessionModel, $course, $repClassId);

        app(FcmNotificationService::class)->sendSessionStartedToClass($course, $repClassId);

        $presentCount = Attendance::where('attendance_session_id', $sessionModel->id)
            ->where('status', 'present')
            ->count();
        event(new SessionLiveEvent($sessionModel->fresh(['course']), $wasReopened ? 'session_reopened' : 'session_opened', ['present_count' => $presentCount]));

        AuditLogService::record(
            $wasReopened ? AuditLogService::SESSION_REOPENED : AuditLogService::SESSION_OPENED,
            [
                'request' => $request,
                'course_id' => (int) $course->id,
                'class_id' => $repClassId ? (int) $repClassId : null,
                'attendance_session_id' => (int) $sessionModel->id,
                'subject_type' => 'attendance_session',
                'subject_id' => (int) $sessionModel->id,
                'payload' => [
                    'week_number' => $week->week_number,
                    'duration_minutes' => $duration,
                    'mode' => $validated['mode'],
                    'venue_override' => $overrideVenueId,
                ],
            ]
        );

        $activeMinutes = max(1, (int) ceil(($expectedEnd->getTimestamp() - now()->getTimestamp()) / 60));

        $verb = $wasReopened ? 'Session reopened' : 'Session opened';
        $msg = $verb.'. Week '.$week->week_number.'. Active for ~'.$activeMinutes.' min.';
        if ($overrideVenueId !== null) {
            $venueName = optional(Venue::find($overrideVenueId))->name;
            if ($venueName) {
                $msg .= ' Venue overridden to '.$venueName.' for this session.';
            }
        }

        return back()->with('success', $msg);
    }

    /**
     * GET confirmation page (avoids 405 when the close URL is opened directly or refreshed as GET).
     */
    public function closeSessionConfirm(Request $request, AttendanceSession $session): View|RedirectResponse
    {
        $student = $this->requireClassRep($request);
        if ($student instanceof RedirectResponse) {
            return $student;
        }

        $course = $session->course;
        $classIds = $this->getRepClassIds($student);
        if (! $this->repCanAccessCourse($student, $course)) {
            abort(403, 'You can only manage sessions for courses in your class.');
        }
        if (! $this->requireMainRep($student, $session->course_id)) {
            return redirect()->route('dashboard.session')->with('error', 'Only main reps can close sessions');
        }

        $session->load('course');

        return view('classrep.session-close-confirm', compact('session'));
    }

    public function closeSession(Request $request, AttendanceSession $session): RedirectResponse
    {
        $student = $this->requireClassRep($request);
        if ($student instanceof RedirectResponse) {
            return $student;
        }

        $course = $session->course;
        $classIds = $this->getRepClassIds($student);
        if (! $this->repCanAccessCourse($student, $course)) {
            abort(403, 'You can only manage sessions for courses in your class.');
        }
        if (! $this->requireMainRep($student, $session->course_id)) {
            return back()->with('error', 'Only main reps can close sessions');
        }
        if ($session->isCheckInCheckoutMode()) {
            // End the live window immediately; students keep checkout via checkout_enabled / !isValid().
            $session->update([
                'checkout_enabled' => true,
                'is_active' => false,
            ]);
        } else {
            $session->update(['is_active' => false]);
        }
        $session->refresh();
        $session->load('course');
        $presentCount = Attendance::where('attendance_session_id', $session->id)
            ->where('status', 'present')
            ->count();
        event(new SessionLiveEvent($session, 'session_closed', ['present_count' => $presentCount]));

        AuditLogService::record(AuditLogService::SESSION_CLOSED, [
            'request' => $request,
            'course_id' => (int) $session->course_id,
            'class_id' => $session->class_id ? (int) $session->class_id : null,
            'attendance_session_id' => (int) $session->id,
            'subject_type' => 'attendance_session',
            'subject_id' => (int) $session->id,
            'payload' => [
                'present_count' => $presentCount,
                'mode' => $session->isCheckInCheckoutMode() ? 'check_in_checkout' : 'instant',
            ],
        ]);

        return back()->with('success', $session->isCheckInCheckoutMode()
            ? 'Class ended. Checkout is now enabled.'
            : 'Session closed.');
    }

    /**
     * Push back the session's expires_at / end_time by N minutes (default 15).
     * Reactivates the session if it had already expired (within a sane safety
     * window). Honours the same rep / main-rep / course-access checks as
     * open and close. No business-logic change to how marks are recorded;
     * we only move the time window.
     */
    public function extendSession(Request $request, AttendanceSession $session): RedirectResponse
    {
        $student = $this->requireClassRep($request);
        if ($student instanceof RedirectResponse) {
            return $student;
        }

        $course = $session->course;
        if (! $this->repCanAccessCourse($student, $course)) {
            abort(403, 'You can only manage sessions for courses in your class.');
        }
        if (! $this->requireMainRep($student, $session->course_id)) {
            return back()->with('error', 'Only main reps can extend sessions');
        }

        $validated = $request->validate([
            'minutes' => 'nullable|integer|min:5|max:120',
        ]);
        $minutes = (int) ($validated['minutes'] ?? 15);

        // Reject extending a session that has been closed manually too long
        // ago — reps shouldn't be able to silently revive a session from
        // yesterday. canBeMarkedByClassRep() already enforces a sensible
        // lookback window (config 'app.attendance_rep_supplemental_days').
        if (! $session->canBeMarkedByClassRep()) {
            return back()->with('error', 'This session is too old to extend. Open a new session instead.');
        }

        // Base the new expiry on whichever is later: now() or the existing
        // expires_at. Otherwise extending an already-expired session by 15
        // minutes would still leave it in the past.
        $base = $session->expires_at && $session->expires_at->isFuture()
            ? $session->expires_at->copy()
            : now();
        $newExpiry = $base->copy()->addMinutes($minutes);

        $previousExpiresAt = $session->expires_at;
        $previousEndTime = $session->end_time;

        $session->update([
            'expires_at' => $newExpiry,
            'end_time' => $newExpiry,
            'is_active' => true,
        ]);
        $session->refresh();
        $session->load('course');

        // Bust the cached "active sessions for this course" list so the
        // student dashboards / Flutter app see the new window immediately
        // instead of waiting for the 5s TTL.
        try {
            LiveAttendanceCache::bump();
        } catch (\Throwable $e) {
            report($e);
        }

        $presentCount = Attendance::where('attendance_session_id', $session->id)
            ->where('status', 'present')
            ->count();
        event(new SessionLiveEvent($session, 'session_extended', [
            'present_count' => $presentCount,
            'expires_at' => $newExpiry->toIso8601String(),
        ]));

        AuditLogService::record(AuditLogService::SESSION_EXTENDED, [
            'request' => $request,
            'course_id' => (int) $session->course_id,
            'class_id' => $session->class_id ? (int) $session->class_id : null,
            'attendance_session_id' => (int) $session->id,
            'subject_type' => 'attendance_session',
            'subject_id' => (int) $session->id,
            'payload' => [
                'minutes' => $minutes,
                'previous_expires_at' => $previousExpiresAt?->toIso8601String(),
                'previous_end_time' => $previousEndTime?->toIso8601String(),
                'new_expires_at' => $newExpiry->toIso8601String(),
            ],
        ]);

        return back()->with('success', 'Session extended by '.$minutes.' min. Now ends '.$newExpiry->format('g:i A').'.');
    }

    public function qr(AttendanceSession $session, Request $request)
    {
        $student = $this->requireClassRep($request);
        if ($student instanceof RedirectResponse) {
            return $student;
        }

        $course = $session->course;
        if (! $this->repCanAccessCourse($student, $course)) {
            abort(403, 'You can only view QR for courses in your class.');
        }
        if (! $session->isValid()) {
            return back()->with('error', 'Session expired');
        }
        $session->load(['course', 'attendanceWeek']);
        // Build a freshly-signed, short-TTL QR payload so each render is
        // unique and screenshots stop working within seconds. The
        // qr-display page polls qr-payload every few seconds to refresh
        // the image, which is what gives every student a "different code".
        $payload = $this->buildRotatingQrPayload($session);
        $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=400x400&data='.urlencode($payload);
        $scopedClassIds = RepCourseAccess::scopedClassIdsForCourse($student, $course);
        $scannedCount = $session->attendances()
            ->whereHas('student', fn ($q) => $q->whereIn('class_id', $scopedClassIds))
            ->whereIn('status', ['present', 'late'])
            ->count();

        return view('classrep.qr-display', [
            'session' => $session,
            'qrUrl' => $qrUrl,
            'scannedCount' => $scannedCount,
            // Used as the JS setInterval cadence. Halved relative to the
            // rotating-code window so the screen updates well within one
            // rotation — feels reactive instead of laggy.
            'qrRotateSeconds' => max(3, (int) ceil(SecureQrToken::ROTATION_WINDOW_SECONDS / 2)),
            'rotatingCode' => SecureQrToken::rotatingCode($session),
            'rotatingCodeWindow' => SecureQrToken::ROTATION_WINDOW_SECONDS,
        ]);
    }

    /**
     * Build a freshly signed QR payload for this session — a different
     * payload on every call (because the issued/expiry timestamps move),
     * so screenshots stop working once the TTL elapses. The same rotating
     * short code that the rep reads aloud is embedded so an offline
     * scanner could verify it without a second round-trip.
     */
    private function buildRotatingQrPayload(AttendanceSession $session): string
    {
        $signed = SecureQrToken::encode($session);

        return json_encode([
            'session_id' => $session->id,
            'token' => $signed,
            'course_id' => $session->course_id,
            'code' => SecureQrToken::rotatingCode($session),
            'iat' => now()->timestamp,
        ]);
    }

    /**
     * PNG download for printing (same QR payload as the on-screen image).
     */
    public function qrDownload(AttendanceSession $session, Request $request): StreamedResponse|RedirectResponse
    {
        $student = $this->requireClassRep($request);
        if ($student instanceof RedirectResponse) {
            return $student;
        }

        $course = $session->course;
        if (! $this->repCanAccessCourse($student, $course)) {
            abort(403, 'You can only download QR codes for courses in your class.');
        }
        if (! $session->isValid()) {
            return redirect()->route('dashboard.session')->with('error', 'Session expired');
        }

        $session->loadMissing('course');
        $payload = json_encode($session->getQrPayload());
        $png = SessionQrPng::fetchBytes($payload);
        $slug = Str::slug($course->course_code ?: $course->course_name ?: 'session');
        $filename = 'attendance-qr-'.$session->id.'-'.$slug.'.png';

        return response()->streamDownload(function () use ($png) {
            echo $png;
        }, $filename, [
            'Content-Type' => 'image/png',
        ]);
    }

    /**
     * JSON: number of students marked present for this session (for QR page live counter).
     */
    public function qrStats(AttendanceSession $session, Request $request): JsonResponse|RedirectResponse
    {
        $student = $this->requireClassRep($request);
        if ($student instanceof RedirectResponse) {
            return $student;
        }

        $course = $session->course;
        if (! $this->repCanAccessCourse($student, $course)) {
            abort(403, 'You can only view stats for courses in your class.');
        }

        $scopedClassIds = RepCourseAccess::scopedClassIdsForCourse($student, $course);
        $scannedCount = $session->attendances()
            ->whereHas('student', fn ($q) => $q->whereIn('class_id', $scopedClassIds))
            ->whereIn('status', ['present', 'late'])
            ->count();

        return response()->json([
            'scanned_count' => $scannedCount,
        ]);
    }

    /**
     * Returns a freshly signed QR payload (rotates every poll) so the
     * classrep.qr-display page can re-paint the QR every few seconds.
     * Each call produces a *new* signed token because the issued / expiry
     * timestamps move forward, which kills the "screenshot the QR and
     * send it to a friend" attack within the TTL window.
     */
    public function qrPayload(AttendanceSession $session, Request $request): JsonResponse|RedirectResponse
    {
        $student = $this->requireClassRep($request);
        if ($student instanceof RedirectResponse) {
            return $student;
        }

        $course = $session->course;
        if (! $this->repCanAccessCourse($student, $course)) {
            abort(403, 'You can only view QR for courses in your class.');
        }
        if (! $session->isValid()) {
            return response()->json(['message' => 'Session expired'], 410);
        }

        $payload = $this->buildRotatingQrPayload($session);

        return response()->json([
            'payload' => json_decode($payload, true),
            'payload_raw' => $payload,
            'image_url' => 'https://api.qrserver.com/v1/create-qr-code/?size=400x400&data='.urlencode($payload),
            // Rep page polls this endpoint at this cadence — kept short
            // so the visible QR + rotating code stay close to the actual
            // rotation window (no more 18s waits).
            'rotates_in_seconds' => max(3, (int) ceil(SecureQrToken::ROTATION_WINDOW_SECONDS / 2)),
            'rotating_code' => SecureQrToken::rotatingCode($session),
            'rotating_code_seconds_left' => SecureQrToken::rotatingCodeSecondsRemaining(),
            'rotating_code_window_seconds' => SecureQrToken::ROTATION_WINDOW_SECONDS,
        ]);
    }

    public function studentsIndex(Request $request): View|RedirectResponse
    {
        $rep = $this->requireClassRep($request);
        if ($rep instanceof RedirectResponse) {
            return $rep;
        }

        $classIds = $this->getRepClassIds($rep);
        $query = Student::with('schoolClass')->whereIn('class_id', $classIds)->orderBy('last_name')->orderBy('first_name');

        $search = $request->get('search');
        if (is_string($search) && trim($search) !== '') {
            $query->searchTerm($search);
        }

        $classId = $request->get('class_id');
        if ($classId && $classIds->contains((int) $classId)) {
            $query->where('class_id', $classId);
        }

        $students = $query->get();
        $classes = SchoolClass::whereIn('id', $classIds)->orderBy('name')->get();

        return view('classrep.students', ['students' => $students, 'classes' => $classes, 'dashboardRole' => 'classrep']);
    }

    /**
     * Class rep bulk-import of students by index number for a class they manage.
     * Accepts xlsx/xls/csv with at least an `index_number` column; optional
     * first_name / middle_name / last_name. Rows without a class column are
     * routed to the rep-selected target class.
     */
    public function importStudents(Request $request): RedirectResponse
    {
        $rep = $this->requireClassRep($request);
        if ($rep instanceof RedirectResponse) {
            return $rep;
        }

        $validated = $request->validate([
            'class_id' => 'required|integer|exists:classes,id',
            'file' => 'required|file|mimes:xlsx,xls,csv|max:5120',
        ]);

        $classId = (int) $validated['class_id'];
        $managedIds = $this->getRepClassIds($rep)->map(fn ($id) => (int) $id);
        if (! $managedIds->contains($classId)) {
            abort(403, 'You may only import students into a class you rep.');
        }

        $import = new StudentsImport([$classId], $classId);
        Excel::import($import, $request->file('file'));

        return redirect()
            ->route('dashboard.students.index', ['class_id' => $classId])
            ->with('success', "Roster import complete: {$import->created} added, {$import->updated} updated, {$import->skipped} skipped.");
    }

    public function studentShow(Request $request, Student $student): View|RedirectResponse
    {
        $rep = $this->requireClassRep($request);
        if ($rep instanceof RedirectResponse) {
            return $rep;
        }

        if (! $this->canAccessStudent($rep, $student)) {
            abort(403, 'You can only view students in your classes.');
        }

        $student->load([
            'schoolClass.faculty',
            'schoolClass.department',
            'department.faculty',
            'classReps.schoolClass',
        ]);

        // Reps must only see data for courses inside classes they actually
        // manage — never another class's courses, even when the student
        // happens to be in their own class.
        $repClassIds = $this->getRepClassIds($rep)->map(fn ($id) => (int) $id);
        $studentClassId = (int) ($student->class_id ?? 0);
        $allowedClassIds = $repClassIds->intersect([$studentClassId])->values()->all();

        $coursesInClass = $allowedClassIds !== []
            ? Course::query()
                ->forManagedClasses($allowedClassIds)
                ->orderBy('course_name')
                ->get()
            : collect();

        $courseIds = $coursesInClass->pluck('id')->map(fn ($id) => (int) $id)->all();

        $coursesCount = $coursesInClass->count();

        // Counts must ignore attendances that point at a cancelled or
        // already-reset week — those records linger for audit purposes
        // but should never inflate the student's totals.
        $attendanceRecordsCount = 0;
        if ($courseIds !== []) {
            $arQuery = $student->attendances()
                ->whereIn('course_id', $courseIds)
                ->activeWeeksOnly();
            if ($allowedClassIds !== []) {
                AttendanceSessionClassScope::scopeAttendanceMarksForClasses($arQuery, $allowedClassIds);
            }
            $attendanceRecordsCount = (int) $arQuery->count();
        }

        $countsByCourseId = collect();
        if ($coursesInClass->isNotEmpty()) {
            $countsQuery = Attendance::query()
                ->where('student_id', $student->id)
                ->whereIn('course_id', $courseIds)
                ->activeWeeksOnly()
                ->selectRaw('course_id, COUNT(*) as cnt')
                ->groupBy('course_id');
            if ($allowedClassIds !== []) {
                AttendanceSessionClassScope::scopeAttendanceMarksForClasses($countsQuery, $allowedClassIds);
            }
            $countsByCourseId = $countsQuery->pluck('cnt', 'course_id');
        }

        $attendanceByCourse = [];
        foreach ($coursesInClass as $course) {
            $attendanceByCourse[] = [
                'course' => $course,
                'count' => (int) ($countsByCourseId[$course->id] ?? 0),
            ];
        }

        $coursesWithMarks = collect($attendanceByCourse)->where('count', '>', 0)->count();

        // Scheduled-week total also drops cancelled rows so the "weeks
        // covered" stat lines up with the live attendance counts.
        $scheduledWeekRows = $courseIds !== []
            ? (int) \DB::table('attendance_weeks')
                ->whereIn('course_id', $courseIds)
                ->whereNull('cancelled_at')
                ->count()
            : 0;

        $recentAttendancesQuery = Attendance::query()
            ->where('student_id', $student->id)
            ->activeWeeksOnly()
            ->with(['course', 'attendanceWeek'])
            ->latest('attendance_time')
            ->limit(15);
        if ($courseIds === []) {
            $recentAttendances = collect();
        } else {
            $recentAttendancesQuery->whereIn('course_id', $courseIds);
            if ($allowedClassIds !== []) {
                AttendanceSessionClassScope::scopeAttendanceMarksForClasses(
                    $recentAttendancesQuery,
                    $allowedClassIds
                );
            }
            $recentAttendances = $recentAttendancesQuery->get();
        }

        $isRepStudent = $student->classReps->isNotEmpty();
        $repAssignments = $isRepStudent
            ? ['classReps' => $student->classReps]
            : null;

        $hasPassword = filled($student->getRawOriginal('password') ?? null);
        $missingProfileFields = $student->missingBasicOnboardingFields();

        return view('classrep.student-detail', [
            'student' => $student,
            'coursesCount' => $coursesCount,
            'attendanceRecordsCount' => $attendanceRecordsCount,
            'coursesWithMarks' => $coursesWithMarks,
            'scheduledWeekRows' => $scheduledWeekRows,
            'attendanceByCourse' => $attendanceByCourse,
            'recentAttendances' => $recentAttendances,
            'repAssignments' => $repAssignments,
            'isRepStudent' => $isRepStudent,
            'hasPassword' => $hasPassword,
            'missingProfileFields' => $missingProfileFields,
            'dashboardRole' => 'classrep',
        ]);
    }

    public function classShow(Request $request): View|RedirectResponse
    {
        $rep = $this->requireClassRep($request);
        if ($rep instanceof RedirectResponse) {
            return $rep;
        }

        $classIds = $this->getRepClassIds($rep);
        $classes = SchoolClass::with(['faculty', 'department'])
            ->whereIn('id', $classIds)
            ->withCount('students')
            ->orderBy('name')
            ->get();

        // Load courses from BOTH the legacy class_id column AND the course_class
        // pivot, since many courses are now linked exclusively via the pivot.
        $hasPivot = SchemaFeatures::hasCourseClassPivot();
        foreach ($classes as $class) {
            $courses = Course::query()
                ->where(function ($q) use ($class, $hasPivot) {
                    $q->where('courses.class_id', $class->id);
                    if ($hasPivot) {
                        $q->orWhereHas(
                            'schoolClasses',
                            fn ($sq) => $sq->where('classes.id', $class->id)
                        );
                    }
                })
                ->orderBy('course_name')
                ->get()
                ->unique('id')
                ->values();

            $class->setAttribute('courses_count', $courses->count());
            $class->setRelation('assignedCourses', $courses);
        }

        return view('classrep.class', ['classes' => $classes]);
    }

    /**
     * Attendance hub: list courses (reps drill into a course to see who attended).
     */
    public function attendanceIndex(Request $request): View|RedirectResponse
    {
        $rep = $this->requireClassRep($request);
        if ($rep instanceof RedirectResponse) {
            return $rep;
        }

        // Strictly scoped to courses linked (via course_class pivot or the
        // legacy class_id column) to a class this student reps — no leaks.
        $courses = RepCourseAccess::coursesQueryForRep($rep)
            ->with(['schoolClass', 'schoolClasses'])
            ->orderBy('course_name')
            ->get();

        return view('classrep.attendance-index', [
            'courses' => $courses,
            'rep' => $rep,
            'dashboardRole' => 'classrep',
        ]);
    }

    /**
     * Attendance records for one course (students who marked attendance in this course).
     */
    public function attendanceForCourse(Request $request, Course $course): View|RedirectResponse
    {
        $rep = $this->requireClassRep($request);
        if ($rep instanceof RedirectResponse) {
            return $rep;
        }

        $classIds = $this->getRepClassIds($rep);
        if (! $this->repCanAccessCourse($rep, $course)) {
            abort(403, 'You can only view attendance for courses in your class.');
        }

        $course->loadMissing(['schoolClass', 'schoolClasses', 'lecturer', 'venueRelation']);

        // Resolve the rep's *own* class context for this course. The course
        // may be taught to several classes (legacy class_id + course_class
        // pivot), so blindly showing $course->schoolClass->name would
        // mislabel a shared course as the wrong cohort. We pin the header
        // to whichever class(es) the rep actually manages for it.
        $repClassLabel = RepCourseAccess::repClassLabelForCourse($rep, $course);
        // Best-effort recent sessions list. We only ask for columns that
        // definitely exist on the model; failures here (stale schema cache,
        // missing migration on a fresh server) must never blow up the whole
        // page since the rep simply wants to see the weekly grid.
        try {
            $recentSessions = AttendanceSession::query()
                ->where('course_id', $course->id)
                ->latest('id')
                ->limit(20)
                ->get();
        } catch (\Throwable $e) {
            report($e);
            $recentSessions = collect();
        }

        $query = RepCourseAccess::scopeAttendanceForRep(
            Attendance::query()->with(['student']),
            $rep,
            $course
        )->latest('attendance_time');

        if ($request->filled('date_from')) {
            $query->whereDate('attendance_time', '>=', $request->query('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('attendance_time', '<=', $request->query('date_to'));
        }
        if ($request->filled('search')) {
            $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], trim((string) $request->query('search'))).'%';
            $query->whereHas('student', function ($q) use ($term) {
                $q->where('index_number', 'like', $term)
                    ->orWhere('first_name', 'like', $term)
                    ->orWhere('middle_name', 'like', $term)
                    ->orWhere('last_name', 'like', $term);
            });
        }

        $attendances = $query->paginate(30)->withQueryString();

        // Show only the weeks the rep's own class has opened (each class runs
        // its own week counter, so reps should not see other classes' rows).
        try {
            $repClassIds = RepCourseAccess::scopedClassIdsForCourse($rep, $course);
            $weeksQuery = $course->attendanceWeeks()->orderBy('week_number');
            if (SchemaFeatures::hasAttendanceWeeksClassId() && $repClassIds !== []) {
                $weeksQuery->where(function ($q) use ($repClassIds) {
                    $q->whereIn('class_id', $repClassIds)->orWhereNull('class_id');
                });
            }
            $attendanceWeeks = $weeksQuery->get();
        } catch (\Throwable $e) {
            report($e);
            $repClassIds = [];
            $attendanceWeeks = collect();
        }

        // The page now drives its visual layout from $weeklyAttendees; the
        // legacy $dailyStats grid was removed in the per-week redesign, so we
        // no longer pay for that aggregation on every page load.
        try {
            $weeklyAttendees = $this->buildWeeklyAttendees($rep, $course, $attendanceWeeks, $repClassIds);
        } catch (\Throwable $e) {
            report($e);
            $weeklyAttendees = collect();
        }
        try {
            $enrolledCount = $repClassIds === []
                ? 0
                : Student::query()->whereIn('class_id', $repClassIds)->count();
        } catch (\Throwable $e) {
            report($e);
            $enrolledCount = 0;
        }

        // Classmates roster for the per-week "Manually mark a student"
        // form. The dropdown was disappearing because this list wasn't
        // being passed to the view — without it the @if guard in
        // resources/views/classrep/attendance-course.blade.php hides
        // the whole control. Cached for 60s under the 'students'
        // namespace so admin roster changes refresh on the next read.
        // We cache plain stdClass rows (via the query builder) instead of
        // Eloquent models. Eloquent collections don't round-trip cleanly
        // through every cache driver / serializer combo (especially the
        // freshly-flipped Redis store on shared hosting), and we only
        // need 5 scalar columns to render the dropdown anyway.
        try {
            if ($repClassIds === []) {
                $classmates = collect();
            } else {
                $cacheKey = CacheVersions::key(
                    'rep_classmates_v2:'.implode('-', $repClassIds),
                    ['students']
                );
                $classmates = Cache::remember(
                    $cacheKey,
                    60,
                    fn () => DB::table('students')
                        ->whereIn('class_id', $repClassIds)
                        ->orderBy('last_name')
                        ->orderBy('first_name')
                        ->orderBy('index_number')
                        ->get(['id', 'index_number', 'first_name', 'middle_name', 'last_name'])
                );

                // Defensive: if the cache backend handed us back something
                // unexpected (string, null, broken serialize blob), drop
                // it and rebuild from the DB so the view never tries to
                // dereference ->id on a string.
                if (! $classmates instanceof Collection
                    || ($classmates->isNotEmpty() && ! is_object($classmates->first()))) {
                    Cache::forget($cacheKey);
                    $classmates = DB::table('students')
                        ->whereIn('class_id', $repClassIds)
                        ->orderBy('last_name')
                        ->orderBy('first_name')
                        ->orderBy('index_number')
                        ->get(['id', 'index_number', 'first_name', 'middle_name', 'last_name']);
                }
            }
        } catch (\Throwable $e) {
            report($e);
            $classmates = collect();
        }

        return view('classrep.attendance-course', [
            'course' => $course,
            'attendances' => $attendances,
            'recentSessions' => $recentSessions,
            'attendanceWeeks' => $attendanceWeeks,
            'weeklyAttendees' => $weeklyAttendees,
            'enrolledCount' => $enrolledCount,
            'repClassLabel' => $repClassLabel,
            'classmates' => $classmates,
            'dashboardRole' => 'classrep',
        ]);
    }

    /**
     * For each teaching week, build the list of students from the rep's class
     * who marked attendance, plus the absent count derived from class size.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, AttendanceWeek>  $weeks
     * @param  list<int>  $repClassIds
     * @return Collection<int, array{week: AttendanceWeek, present: Collection<int, Attendance>, present_count: int, absent_count: int}>
     */
    private function buildWeeklyAttendees(Student $rep, Course $course, $weeks, array $repClassIds): Collection
    {
        if ($weeks->isEmpty() || $repClassIds === []) {
            return collect();
        }
        // Enrolled count rarely changes; keep it cached for 60s and let
        // the 'students' namespace version bump invalidate it when an
        // admin adds/removes students from a class.
        $enrolledCacheKey = CacheVersions::key(
            'rep_enrolled:'.implode('-', $repClassIds),
            ['students']
        );
        try {
            $enrolled = (int) Cache::remember(
                $enrolledCacheKey,
                60,
                fn () => Student::query()->whereIn('class_id', $repClassIds)->count()
            );
        } catch (\Throwable $e) {
            $enrolled = Student::query()->whereIn('class_id', $repClassIds)->count();
        }
        $weekIds = $weeks->pluck('id')->all();
        $byWeek = RepCourseAccess::scopeAttendanceForRep(
            Attendance::query()->with(['student'])->whereIn('attendance_week_id', $weekIds),
            $rep,
            $course
        )
            ->orderBy('attendance_time')
            ->get()
            ->groupBy(fn ($a) => (int) $a->attendance_week_id);

        return collect($weeks)->map(function (AttendanceWeek $week) use ($byWeek, $enrolled): array {
            $present = $byWeek->get((int) $week->id, collect());
            $presentCount = $present->pluck('student_id')->unique()->count();

            return [
                'week' => $week,
                'present' => $present,
                'present_count' => $presentCount,
                'absent_count' => max(0, $enrolled - $presentCount),
            ];
        });
    }

    /**
     * Class rep: mark a teaching week as cancelled (no class expected).
     */
    public function cancelAttendanceWeek(Request $request, Course $course, AttendanceWeek $attendanceWeek): RedirectResponse
    {
        $rep = $this->requireClassRep($request);
        if ($rep instanceof RedirectResponse) {
            return $rep;
        }

        if (! $this->repCanAccessCourse($rep, $course)) {
            abort(403);
        }
        if ((int) $attendanceWeek->course_id !== (int) $course->id) {
            abort(404);
        }

        $validated = $request->validate([
            'note' => 'nullable|string|max:2000',
        ]);

        $attendanceWeek->update([
            'cancelled_at' => now(),
            'cancelled_by' => 'rep',
            'cancellation_note' => $validated['note'] ?? null,
        ]);

        AttendanceSession::query()
            ->where('attendance_week_id', $attendanceWeek->id)
            ->where('is_active', true)
            ->update(['is_active' => false]);

        return back()->with('success', 'Week '.$attendanceWeek->week_number.' marked as cancelled for this course.');
    }

    public function uncancelAttendanceWeek(Request $request, Course $course, AttendanceWeek $attendanceWeek): RedirectResponse
    {
        $rep = $this->requireClassRep($request);
        if ($rep instanceof RedirectResponse) {
            return $rep;
        }

        if (! $this->repCanAccessCourse($rep, $course)) {
            abort(403);
        }
        if ((int) $attendanceWeek->course_id !== (int) $course->id) {
            abort(404);
        }

        $attendanceWeek->update([
            'cancelled_at' => null,
            'cancelled_by' => null,
            'cancellation_note' => null,
        ]);

        return back()->with('success', 'Week '.$attendanceWeek->week_number.' cancellation cleared.');
    }

    /**
     * Rename an existing week row (change the displayed week_number) — handy
     * when the rep realises a session was labelled the wrong week.
     */
    public function renameAttendanceWeek(Request $request, Course $course, AttendanceWeek $attendanceWeek): RedirectResponse
    {
        $rep = $this->requireClassRep($request);
        if ($rep instanceof RedirectResponse) {
            return $rep;
        }

        if (! $this->repCanAccessCourse($rep, $course)) {
            abort(403);
        }
        if ((int) $attendanceWeek->course_id !== (int) $course->id) {
            abort(404);
        }

        $validated = $request->validate([
            'week_number' => 'required|integer|min:1|max:500',
        ]);

        $newNumber = (int) $validated['week_number'];
        $oldNumber = (int) $attendanceWeek->week_number;
        if ($newNumber === $oldNumber) {
            return back()->with('success', 'Week number unchanged.');
        }

        $attendanceWeek->update(['week_number' => $newNumber]);

        return back()->with(
            'success',
            'Week '.$oldNumber.' renamed to Week '.$newNumber.' for this course.'
        );
    }

    /**
     * Rep manually marks attendance for one student, with a required reason
     * (e.g. "phone broke down", "marked late after lecturer confirmed").
     */
    public function manualMarkAttendance(Request $request, Course $course, AttendanceWeek $attendanceWeek): RedirectResponse
    {
        $debugId = bin2hex(random_bytes(4));
        Log::info('[MANUAL-MARK] request.received', [
            'debug_id' => $debugId,
            'course_id' => (int) $course->id,
            'week_id' => (int) $attendanceWeek->id,
            'has_csrf' => $request->session()->token() === $request->input('_token'),
            'payload_keys' => array_keys($request->except(['_token'])),
            'student_id' => $request->input('student_id'),
            'status' => $request->input('status'),
            'reason_len' => mb_strlen((string) $request->input('reason')),
            'ip' => $request->ip(),
        ]);

        $rep = $this->requireClassRep($request);
        if ($rep instanceof RedirectResponse) {
            Log::warning('[MANUAL-MARK] auth.redirect', ['debug_id' => $debugId]);

            return $rep;
        }
        if (! $this->repCanAccessCourse($rep, $course)) {
            Log::warning('[MANUAL-MARK] access.denied', [
                'debug_id' => $debugId,
                'rep_id' => (int) $rep->id,
                'course_id' => (int) $course->id,
            ]);
            abort(403, 'You can only manage attendance for your class courses.');
        }
        if ((int) $attendanceWeek->course_id !== (int) $course->id) {
            Log::warning('[MANUAL-MARK] week.course_mismatch', [
                'debug_id' => $debugId,
                'week_course_id' => (int) $attendanceWeek->course_id,
                'course_id' => (int) $course->id,
            ]);
            abort(404);
        }

        try {
            $validated = $request->validate([
                'student_id' => 'required|integer|exists:students,id',
                'status' => 'required|in:present,late,absent',
                'reason' => 'required|string|min:3|max:500',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('[MANUAL-MARK] validation.failed', [
                'debug_id' => $debugId,
                'errors' => $e->errors(),
            ]);

            throw $e;
        }
        Log::info('[MANUAL-MARK] validation.passed', [
            'debug_id' => $debugId,
            'student_id' => $validated['student_id'],
            'status' => $validated['status'],
        ]);

        $repClassId = $this->resolveRepClassId($rep, $course);
        $student = Student::find((int) $validated['student_id']);
        if (! $student || (int) $student->class_id !== (int) $repClassId) {
            Log::warning('[MANUAL-MARK] student.class_mismatch', [
                'debug_id' => $debugId,
                'student_id' => (int) $validated['student_id'],
                'student_class_id' => $student?->class_id,
                'rep_class_id' => $repClassId,
            ]);

            return back()->with('error', 'You can only mark students in your own class.');
        }

        $session = AttendanceSession::query()
            ->where('course_id', $course->id)
            ->where('attendance_week_id', $attendanceWeek->id)
            ->when(SchemaFeatures::hasAttendanceSessionsClassId() && $repClassId, function ($q) use ($repClassId) {
                $q->where('class_id', $repClassId);
            })
            ->orderByDesc('id')
            ->first();
        if (! $session) {
            Log::warning('[MANUAL-MARK] session.missing', [
                'debug_id' => $debugId,
                'course_id' => (int) $course->id,
                'week_id' => (int) $attendanceWeek->id,
                'rep_class_id' => $repClassId,
            ]);

            return back()->with('error', 'No attendance session exists for this week yet. Open a session first.');
        }

        $row = Attendance::query()
            ->where('student_id', $student->id)
            ->where('attendance_session_id', $session->id)
            ->first();
        $manualPayload = [
            'status' => $validated['status'],
            'marked_manually_by_id' => (int) $rep->id,
            'manual_reason' => $validated['reason'],
            'marked_manually_at' => now(),
            'device_ip' => (string) $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 480),
        ];

        if ($row) {
            $row->update($manualPayload);
        } else {
            $row = Attendance::create(array_merge($manualPayload, [
                'student_id' => $student->id,
                'course_id' => $course->id,
                'attendance_session_id' => $session->id,
                'attendance_week_id' => $session->attendance_week_id,
                'attendance_time' => now(),
                'synced' => true,
            ]));
        }

        AuditLogService::record(AuditLogService::MARK_MANUAL, [
            'request' => $request,
            'course_id' => (int) $course->id,
            'class_id' => $repClassId ? (int) $repClassId : null,
            'attendance_session_id' => (int) $session->id,
            'subject_type' => 'attendance',
            'subject_id' => (int) $row->id,
            'payload' => [
                'student_id' => (int) $student->id,
                'index_number' => $student->index_number,
                'status' => $validated['status'],
                'reason' => $validated['reason'],
                'week_number' => $attendanceWeek->week_number,
            ],
        ]);

        Log::info('[MANUAL-MARK] success', [
            'debug_id' => $debugId,
            'attendance_id' => (int) $row->id,
            'student_id' => (int) $student->id,
            'index_number' => $student->index_number,
            'status' => $validated['status'],
        ]);

        return back()->with('success', 'Marked '.$student->index_number.' as '.$validated['status'].' (manual entry).');
    }

    /**
     * Delete one attendance row. Gated by the super-admin toggle
     * `allow_rep_attendance_deletion`; logs the full deletion event so
     * disputes can be replayed later.
     */
    public function deleteAttendance(Request $request, Attendance $attendance): RedirectResponse
    {
        $rep = $this->requireClassRep($request);
        if ($rep instanceof RedirectResponse) {
            return $rep;
        }

        if (! SystemSetting::repsCanDeleteAttendance()) {
            return back()->with('error', 'Attendance deletion is currently disabled by the super admin.');
        }

        $course = Course::find($attendance->course_id);
        if (! $course || ! $this->repCanAccessCourse($rep, $course)) {
            abort(403, 'You can only delete attendance for your class courses.');
        }

        $validated = $request->validate([
            'reason' => 'required|string|min:3|max:500',
        ]);

        $snapshot = [
            'attendance_id' => (int) $attendance->id,
            'student_id' => (int) $attendance->student_id,
            'course_id' => (int) $attendance->course_id,
            'session_id' => (int) $attendance->attendance_session_id,
            'week_id' => $attendance->attendance_week_id ? (int) $attendance->attendance_week_id : null,
            'status' => $attendance->status,
            'attendance_time' => $attendance->attendance_time?->toIso8601String(),
        ];

        AuditLogService::record(AuditLogService::MARK_DELETED, [
            'request' => $request,
            'course_id' => (int) $attendance->course_id,
            'class_id' => $this->resolveRepClassId($rep, $course),
            'attendance_session_id' => (int) $attendance->attendance_session_id,
            'subject_type' => 'attendance',
            'subject_id' => (int) $attendance->id,
            'payload' => array_merge($snapshot, ['reason' => $validated['reason']]),
        ]);

        $attendance->delete();

        return back()->with('success', 'Attendance record deleted and logged.');
    }

    /**
     * Download attendance rows for this course as JSON (backup / restore).
     */
    public function exportAttendanceJson(Request $request, Course $course): JsonResponse|RedirectResponse
    {
        $rep = $this->requireClassRep($request);
        if ($rep instanceof RedirectResponse) {
            return $rep;
        }

        if (! $this->repCanAccessCourse($rep, $course)) {
            abort(403, 'You can only export attendance for your class courses.');
        }

        $records = RepCourseAccess::scopeAttendanceForRep(
            Attendance::query(),
            $rep,
            $course
        )->with([
            'student:id,index_number',
            'attendanceSession:id,session_index,course_id',
            'attendanceWeek:id,week_number',
        ])
            ->orderBy('id')
            ->get()
            ->map(function (Attendance $a) {
                return [
                    'id' => $a->id,
                    'student_id' => $a->student_id,
                    'index_number' => $a->student?->index_number,
                    'course_id' => $a->course_id,
                    'attendance_session_id' => $a->attendance_session_id,
                    'attendance_week_id' => $a->attendance_week_id,
                    'session_index' => $a->attendanceSession?->session_index,
                    'week_number' => $a->attendanceWeek?->week_number,
                    'attendance_time' => $a->attendance_time?->toIso8601String(),
                    'status' => $a->status,
                    'synced' => (bool) $a->synced,
                    'lat' => $a->lat !== null ? (float) $a->lat : null,
                    'lng' => $a->lng !== null ? (float) $a->lng : null,
                ];
            });

        $payload = [
            'format' => 'at-nda-attendance-backup',
            'version' => 1,
            'exported_at' => now()->toIso8601String(),
            'course_id' => $course->id,
            'course_code' => $course->course_code,
            'course_name' => $course->course_name,
            'records' => $records,
        ];

        $filename = 'attendance-course-'.$course->id.'-'.now()->format('Y-m-d_His').'.json';

        return response()->json($payload, 200, [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Restore attendance rows from a JSON backup exported via {@see exportAttendanceJson}.
     */
    public function importAttendanceJson(Request $request, Course $course): RedirectResponse
    {
        $rep = $this->requireClassRep($request);
        if ($rep instanceof RedirectResponse) {
            return $rep;
        }

        if (! $this->repCanAccessCourse($rep, $course)) {
            abort(403, 'You can only import attendance for your class courses.');
        }

        $request->validate([
            'backup' => 'required|file|max:51200',
        ]);

        $path = $request->file('backup')->getRealPath();
        if ($path === false) {
            return back()->with('error', 'Could not read the uploaded file.');
        }

        $raw = file_get_contents($path);
        $data = json_decode((string) $raw, true);
        if (! is_array($data) || ($data['format'] ?? '') !== 'at-nda-attendance-backup') {
            return back()->with('error', 'Invalid backup file (expected at-nda-attendance-backup JSON).');
        }

        if ((int) ($data['course_id'] ?? 0) !== (int) $course->id) {
            return back()->with('error', 'This backup is for a different course.');
        }

        $rows = $data['records'] ?? null;
        if (! is_array($rows) || $rows === []) {
            return back()->with('error', 'No records in file.');
        }

        $imported = 0;
        $skipped = 0;

        DB::transaction(function () use ($rep, $course, $rows, &$imported, &$skipped) {
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    $skipped++;

                    continue;
                }

                $studentId = isset($row['student_id']) ? (int) $row['student_id'] : 0;
                if ($studentId <= 0 && ! empty($row['index_number'])) {
                    $byIndex = Student::findByIndex((string) $row['index_number']);
                    $studentId = $byIndex?->id ?? 0;
                }

                $sessionId = isset($row['attendance_session_id']) ? (int) $row['attendance_session_id'] : 0;
                if ($studentId <= 0 || $sessionId <= 0) {
                    $skipped++;

                    continue;
                }

                $session = AttendanceSession::query()->find($sessionId);
                if (! $session || (int) $session->course_id !== (int) $course->id) {
                    $skipped++;

                    continue;
                }

                $student = Student::query()->find($studentId);
                $scopedClassIds = RepCourseAccess::scopedClassIdsForCourse($rep, $course);
                if (! $student || ! in_array((int) $student->class_id, $scopedClassIds, true)) {
                    $skipped++;

                    continue;
                }

                $weekId = isset($row['attendance_week_id']) ? (int) $row['attendance_week_id'] : (int) $session->attendance_week_id;
                if ($weekId <= 0) {
                    $skipped++;

                    continue;
                }

                Attendance::updateOrCreate(
                    [
                        'student_id' => $student->id,
                        'attendance_session_id' => $session->id,
                    ],
                    [
                        'course_id' => $course->id,
                        'attendance_week_id' => $weekId,
                        'attendance_time' => isset($row['attendance_time'])
                            ? Carbon::parse((string) $row['attendance_time'])
                            : now(),
                        'status' => isset($row['status']) ? (string) $row['status'] : 'present',
                        'synced' => array_key_exists('synced', $row) ? (bool) $row['synced'] : true,
                        'lat' => isset($row['lat']) ? $row['lat'] : null,
                        'lng' => isset($row['lng']) ? $row['lng'] : null,
                    ]
                );
                $imported++;
            }
        });

        return back()->with('success', "Import finished: {$imported} record(s) saved.".($skipped > 0 ? " {$skipped} row(s) skipped." : ''));
    }

    /**
     * Download attendance for a single teaching week as JSON.
     */
    public function exportAttendanceJsonWeek(Request $request, Course $course, AttendanceWeek $attendanceWeek): JsonResponse|RedirectResponse
    {
        $rep = $this->requireClassRep($request);
        if ($rep instanceof RedirectResponse) {
            return $rep;
        }

        if (! $this->repCanAccessCourse($rep, $course)) {
            abort(403, 'You can only export attendance for your class courses.');
        }

        if ((int) $attendanceWeek->course_id !== (int) $course->id) {
            abort(404);
        }

        $records = RepCourseAccess::scopeAttendanceForRep(
            Attendance::query(),
            $rep,
            $course
        )->where('attendance_week_id', $attendanceWeek->id)
            ->with([
                'student:id,index_number',
                'attendanceSession:id,session_index,course_id',
            ])
            ->orderBy('id')
            ->get()
            ->map(function (Attendance $a) {
                return [
                    'id' => $a->id,
                    'student_id' => $a->student_id,
                    'index_number' => $a->student?->index_number,
                    'course_id' => $a->course_id,
                    'attendance_session_id' => $a->attendance_session_id,
                    'attendance_week_id' => $a->attendance_week_id,
                    'session_index' => $a->attendanceSession?->session_index,
                    'week_number' => $a->attendanceWeek?->week_number,
                    'attendance_time' => $a->attendance_time?->toIso8601String(),
                    'status' => $a->status,
                    'synced' => (bool) $a->synced,
                    'lat' => $a->lat !== null ? (float) $a->lat : null,
                    'lng' => $a->lng !== null ? (float) $a->lng : null,
                ];
            });

        $payload = [
            'format' => 'at-nda-attendance-backup',
            'version' => 1,
            'scope' => 'week',
            'exported_at' => now()->toIso8601String(),
            'course_id' => $course->id,
            'course_code' => $course->course_code,
            'course_name' => $course->course_name,
            'attendance_week_id' => $attendanceWeek->id,
            'week_number' => $attendanceWeek->week_number,
            'week_date' => $attendanceWeek->week_date?->toDateString(),
            'records' => $records,
        ];

        $filename = 'attendance-course-'.$course->id.'-week-'.$attendanceWeek->week_number.'-'.now()->format('Y-m-d_His').'.json';

        return response()->json($payload, 200, [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Restore attendance for a single teaching week from a JSON backup.
     * Accepts both per-week exports and full-course exports (filters to this week).
     */
    public function importAttendanceJsonWeek(Request $request, Course $course, AttendanceWeek $attendanceWeek): RedirectResponse
    {
        $rep = $this->requireClassRep($request);
        if ($rep instanceof RedirectResponse) {
            return $rep;
        }

        if (! $this->repCanAccessCourse($rep, $course)) {
            abort(403, 'You can only import attendance for your class courses.');
        }

        if ((int) $attendanceWeek->course_id !== (int) $course->id) {
            abort(404);
        }

        $request->validate([
            'backup' => 'required|file|max:51200',
        ]);

        $path = $request->file('backup')->getRealPath();
        if ($path === false) {
            return back()->with('error', 'Could not read the uploaded file.');
        }

        $raw = file_get_contents($path);
        $data = json_decode((string) $raw, true);
        if (! is_array($data) || ($data['format'] ?? '') !== 'at-nda-attendance-backup') {
            return back()->with('error', 'Invalid backup file (expected at-nda-attendance-backup JSON).');
        }

        if ((int) ($data['course_id'] ?? 0) !== (int) $course->id) {
            return back()->with('error', 'This backup is for a different course.');
        }

        $rows = $data['records'] ?? null;
        if (! is_array($rows) || $rows === []) {
            return back()->with('error', 'No records in file.');
        }

        $imported = 0;
        $skipped = 0;
        $scopedClassIds = RepCourseAccess::scopedClassIdsForCourse($rep, $course);

        DB::transaction(function () use ($course, $attendanceWeek, $rows, $scopedClassIds, &$imported, &$skipped) {
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    $skipped++;

                    continue;
                }

                $studentId = isset($row['student_id']) ? (int) $row['student_id'] : 0;
                if ($studentId <= 0 && ! empty($row['index_number'])) {
                    $byIndex = Student::findByIndex((string) $row['index_number']);
                    $studentId = $byIndex?->id ?? 0;
                }

                $sessionId = isset($row['attendance_session_id']) ? (int) $row['attendance_session_id'] : 0;
                if ($studentId <= 0 || $sessionId <= 0) {
                    $skipped++;

                    continue;
                }

                $session = AttendanceSession::query()->find($sessionId);
                if (! $session || (int) $session->course_id !== (int) $course->id) {
                    $skipped++;

                    continue;
                }

                $rowWeekId = isset($row['attendance_week_id']) ? (int) $row['attendance_week_id'] : (int) $session->attendance_week_id;
                if ($rowWeekId !== (int) $attendanceWeek->id) {
                    // Backup is allowed to be a full-course export; rows outside this week are ignored.
                    $skipped++;

                    continue;
                }

                $student = Student::query()->find($studentId);
                if (! $student || ! in_array((int) $student->class_id, $scopedClassIds, true)) {
                    $skipped++;

                    continue;
                }

                Attendance::updateOrCreate(
                    [
                        'student_id' => $student->id,
                        'attendance_session_id' => $session->id,
                    ],
                    [
                        'course_id' => $course->id,
                        'attendance_week_id' => $attendanceWeek->id,
                        'attendance_time' => isset($row['attendance_time'])
                            ? Carbon::parse((string) $row['attendance_time'])
                            : now(),
                        'status' => isset($row['status']) ? (string) $row['status'] : 'present',
                        'synced' => array_key_exists('synced', $row) ? (bool) $row['synced'] : true,
                        'lat' => isset($row['lat']) ? $row['lat'] : null,
                        'lng' => isset($row['lng']) ? $row['lng'] : null,
                    ]
                );
                $imported++;
            }
        });

        return back()->with(
            'success',
            "Week {$attendanceWeek->week_number} import finished: {$imported} record(s) saved."
            .($skipped > 0 ? " {$skipped} row(s) skipped." : '')
        );
    }

    public function resetPassword(Request $request, Student $student): RedirectResponse
    {
        $rep = $this->requireClassRep($request);
        if ($rep instanceof RedirectResponse) {
            return $rep;
        }

        if (! $this->canAccessStudent($rep, $student)) {
            abort(403, 'You can only reset passwords for students in your classes.');
        }

        $password = Str::password(12);
        $student->update(['password' => Hash::make($password)]);

        return back()->with('success', 'Password generated for '.$student->index_number.'. New password: '.$password);
    }
}
