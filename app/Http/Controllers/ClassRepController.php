<?php

namespace App\Http\Controllers;

use App\Events\SessionLiveEvent;
use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\AttendanceWeek;
use App\Models\Course;
use Carbon\Carbon;
use App\Support\SessionQrPng;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\SystemSetting;
use App\Services\ClassSessionScopeService;
use App\Services\FcmNotificationService;
use App\Support\RepCourseAccess;
use App\Support\SchemaFeatures;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClassRepController extends Controller
{
    private function getStudent(Request $request): ?Student
    {
        $id = $request->session()->get('student_id');
        if (!$id) return null;
        return Student::find($id);
    }

    private function requireClassRep(Request $request): Student|\Illuminate\Http\RedirectResponse
    {
        $student = $this->getStudent($request);
        if (!$student) {
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

    private function getRepClassIds(Student $rep): \Illuminate\Support\Collection
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

        $classIds = $this->getRepClassIds($student);
        $classIdsArr = $classIds->map(fn ($id) => (int) $id)->all();
        $studentsCount = Student::whereIn('class_id', $classIds)->count();

        // The rep dashboard now mirrors the per-class timetable: a course is
        // "assigned" only once the rep has added it to their own class
        // timetable. This keeps the Courses tile and Today's schedule in
        // sync with /dashboard/timetable.
        $useClassTimetable = SchemaFeatures::hasClassTimetables() && $classIdsArr !== [];
        $timetableCourseIds = $useClassTimetable
            ? \App\Models\ClassTimetable::query()
                ->whereIn('class_id', $classIdsArr)
                ->whereNotNull('course_id')
                ->pluck('course_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
            : collect();

        $coursesCount = $useClassTimetable
            ? $timetableCourseIds->count()
            : (clone RepCourseAccess::coursesQueryForRep($student))->count();

        // Attendance counters stay scoped to every course assigned to the
        // rep's classes so historical marks aren't hidden if a slot was
        // later removed from the timetable.
        $courseIds = (clone RepCourseAccess::coursesQueryForRep($student))->pluck('id');

        $totalAttendanceMarks = $courseIds->isEmpty()
            ? 0
            : Attendance::query()
                ->whereIn('course_id', $courseIds)
                ->whereHas('student', fn ($q) => $q->whereIn('class_id', $classIds))
                ->count();

        $todayAttendanceMarks = $courseIds->isEmpty()
            ? 0
            : Attendance::query()
                ->whereIn('course_id', $courseIds)
                ->whereHas('student', fn ($q) => $q->whereIn('class_id', $classIds))
                ->whereDate('attendance_time', today())
                ->count();

        $weekAttendanceMarks = $courseIds->isEmpty()
            ? 0
            : Attendance::query()
                ->whereIn('course_id', $courseIds)
                ->whereHas('student', fn ($q) => $q->whereIn('class_id', $classIds))
                ->where('attendance_time', '>=', now()->subDays(7)->startOfDay())
                ->count();

        $todayCourses = $this->buildRepTodayCourses($student, $classIdsArr, $useClassTimetable);

        return view('classrep.overview', [
            'student' => $student,
            'studentsCount' => $studentsCount,
            'coursesCount' => $coursesCount,
            'totalAttendanceMarks' => $totalAttendanceMarks,
            'todayAttendanceMarks' => $todayAttendanceMarks,
            'weekAttendanceMarks' => $weekAttendanceMarks,
            'todayCourses' => $todayCourses,
            'dashboardRole' => 'classrep',
        ]);
    }

    /**
     * Build a normalized list of today's slots for the rep's dashboard,
     * preferring per-class timetable entries and falling back to the legacy
     * course-level day/time columns when no per-class entries exist.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function buildRepTodayCourses(Student $student, array $classIdsArr, bool $useClassTimetable): \Illuminate\Support\Collection
    {
        $todayName = strtolower(now()->format('l'));

        if ($useClassTimetable && $classIdsArr !== []) {
            $slots = \App\Models\ClassTimetable::query()
                ->whereIn('class_id', $classIdsArr)
                ->whereRaw('LOWER(TRIM(day_of_week)) = ?', [$todayName])
                ->with(['course', 'venueRelation', 'lecturer'])
                ->orderBy('start_time')
                ->limit(10)
                ->get();

            if ($slots->isNotEmpty()) {
                return $slots->map(function (\App\Models\ClassTimetable $slot) {
                    $course = $slot->course;
                    $scheduleParts = [];
                    try {
                        $start = \Carbon\Carbon::parse($slot->start_time)->format('H:i');
                        $end = \Carbon\Carbon::parse($slot->end_time)->format('H:i');
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
                        'has_active_session' => $course?->activeSession() !== null,
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
                'has_active_session' => $c->activeSession() !== null,
            ]);
    }

    public function dashboard(Request $request): View|RedirectResponse
    {
        $student = $this->requireClassRep($request);
        if ($student instanceof RedirectResponse) return $student;

        $classIds = $this->getRepClassIds($student);
        $courses = RepCourseAccess::coursesQueryForRep($student)
            ->with([
                'schoolClass.faculty.university',
                'schoolClasses',
                'attendanceSessions' => fn ($q) => $q->where('is_active', true),
            ])
            ->orderBy('course_name')
            ->get()
            ->map(fn ($c) => (object) [
                'course' => $c,
                'role' => RepCourseAccess::classRepForCourse($student, $c)?->role ?? 'rep',
                'canOpenSession' => $this->requireMainRep($student, $c->id),
            ]);

        $settings = SystemSetting::get();
        $attendanceMode = SystemSetting::hasAttendanceModeColumns()
            ? (string) ($settings->attendance_mode ?: SystemSetting::ATTENDANCE_MODE_INSTANT)
            : SystemSetting::ATTENDANCE_MODE_INSTANT;
        $instantModeType = SystemSetting::hasAttendanceModeColumns()
            ? (string) ($settings->instant_mode_type ?: SystemSetting::INSTANT_MODE_LOCATION_QR)
            : SystemSetting::INSTANT_MODE_LOCATION_QR;

        // Venues let the rep override the timetable default for one session
        // (e.g. when class meets in a different room today).
        $venues = \App\Models\Venue::query()->orderBy('name')->get();

        return view('classrep.dashboard', [
            'student' => $student,
            'courses' => $courses,
            'dashboardRole' => 'classrep',
            'attendanceMode' => $attendanceMode,
            'instantModeType' => $instantModeType,
            'venues' => $venues,
        ]);
    }

    public function openSession(Request $request): RedirectResponse
    {
        $student = $this->requireClassRep($request);
        if ($student instanceof RedirectResponse) return $student;

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
        if (!$this->requireMainRep($student, $course->id)) {
            return back()->with('error', 'Only main reps can open sessions');
        }

        $repClassId = $this->resolveRepClassId($student, $course);
        if (!$course->hasScheduleForClass($repClassId)) {
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

        $snapshot = \App\Support\ClassTimetableAccess::resolveScheduleSnapshot($course, $repClassId);
        $sessionLecturerId = $snapshot['lecturer_id'] ?? $course->lecturer_id;
        // Optional venue override from the rep wins over the timetable default
        // for this single session (does not rewrite the timetable row).
        $overrideVenueId = isset($validated['venue_id']) && $validated['venue_id'] !== null
            ? (int) $validated['venue_id']
            : null;
        $sessionVenueId = $overrideVenueId ?? ($snapshot['venue_id'] ?? $course->venue_id);

        $sessionModel = AttendanceSession::create([
            'course_id' => $course->id,
            'session_index' => AttendanceSession::nextIndexForCourse($course->id),
            'attendance_week_id' => $week->id,
            'mode' => $validated['mode'],
            'attendance_mode' => $attendanceMode,
            'allowed_wifi_ssid' => $validated['mode'] === 'wifi' ? $wifiSsid : null,
            'is_active' => true,
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
        ]);

        ClassSessionScopeService::autoMarkClassRepsForSession($sessionModel, $course);

        app(FcmNotificationService::class)->sendSessionStartedToClass($course);

        $presentCount = Attendance::where('attendance_session_id', $sessionModel->id)
            ->where('status', 'present')
            ->count();
        event(new SessionLiveEvent($sessionModel->fresh(['course']), 'session_opened', ['present_count' => $presentCount]));

        $activeMinutes = max(1, (int) ceil(($expectedEnd->getTimestamp() - now()->getTimestamp()) / 60));

        $msg = 'Session opened. Week '.$week->week_number.'. Active for ~'.$activeMinutes.' min.';
        if ($overrideVenueId !== null) {
            $venueName = optional(\App\Models\Venue::find($overrideVenueId))->name;
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
        if ($student instanceof RedirectResponse) return $student;

        $course = $session->course;
        $classIds = $this->getRepClassIds($student);
        if (! $this->repCanAccessCourse($student, $course)) {
            abort(403, 'You can only manage sessions for courses in your class.');
        }
        if (!$this->requireMainRep($student, $session->course_id)) {
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

        return back()->with('success', $session->isCheckInCheckoutMode()
            ? 'Class ended. Checkout is now enabled.'
            : 'Session closed.');
    }

    public function qr(AttendanceSession $session, Request $request)
    {
        $student = $this->requireClassRep($request);
        if ($student instanceof RedirectResponse) return $student;

        $course = $session->course;
        if (! $this->repCanAccessCourse($student, $course)) {
            abort(403, 'You can only view QR for courses in your class.');
        }
        if (!$session->isValid()) {
            return back()->with('error', 'Session expired');
        }
        $session->load(['course', 'attendanceWeek']);
        $payload = json_encode($session->getQrPayload());
        $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=400x400&data=' . urlencode($payload);
        $scopedClassIds = RepCourseAccess::scopedClassIdsForCourse($student, $course);
        $scannedCount = $session->attendances()
            ->whereHas('student', fn ($q) => $q->whereIn('class_id', $scopedClassIds))
            ->whereIn('status', ['present', 'late'])
            ->count();

        return view('classrep.qr-display', [
            'session' => $session,
            'qrUrl' => $qrUrl,
            'scannedCount' => $scannedCount,
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
     * JSON payload for QR image (static per session).
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
        if (!$session->isValid()) {
            return response()->json(['message' => 'Session expired'], 410);
        }

        return response()->json([
            'payload' => $session->getQrPayload(),
        ]);
    }

    public function studentsIndex(Request $request): View|RedirectResponse
    {
        $rep = $this->requireClassRep($request);
        if ($rep instanceof RedirectResponse) return $rep;

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

    public function studentShow(Request $request, Student $student): View|RedirectResponse
    {
        $rep = $this->requireClassRep($request);
        if ($rep instanceof RedirectResponse) return $rep;

        if (!$this->canAccessStudent($rep, $student)) {
            abort(403, 'You can only view students in your classes.');
        }

        $student->load([
            'schoolClass.faculty',
            'schoolClass.department',
            'department.faculty',
            'classReps.schoolClass',
        ]);

        $coursesInClass = $student->class_id
            ? Course::query()
                ->forManagedClasses([(int) $student->class_id])
                ->orderBy('course_name')
                ->get()
            : collect();

        $coursesCount = $coursesInClass->count();
        $attendanceRecordsCount = $student->attendances()->count();

        $countsByCourseId = $coursesInClass->isNotEmpty()
            ? Attendance::query()
                ->where('student_id', $student->id)
                ->whereIn('course_id', $coursesInClass->pluck('id'))
                ->selectRaw('course_id, COUNT(*) as cnt')
                ->groupBy('course_id')
                ->pluck('cnt', 'course_id')
            : collect();

        $attendanceByCourse = [];
        foreach ($coursesInClass as $course) {
            $attendanceByCourse[] = [
                'course' => $course,
                'count' => (int) ($countsByCourseId[$course->id] ?? 0),
            ];
        }

        $coursesWithMarks = collect($attendanceByCourse)->where('count', '>', 0)->count();

        $courseIds = $coursesInClass->pluck('id')->all();
        $scheduledWeekRows = $courseIds !== []
            ? (int) \DB::table('attendance_weeks')->whereIn('course_id', $courseIds)->count()
            : 0;

        $recentAttendances = Attendance::query()
            ->where('student_id', $student->id)
            ->with(['course', 'attendanceWeek'])
            ->latest('attendance_time')
            ->limit(15)
            ->get();

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
        if ($rep instanceof RedirectResponse) return $rep;

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

        $course->loadMissing(['schoolClass', 'lecturer', 'venueRelation']);
        $recentSessions = AttendanceSession::query()
            ->where('course_id', $course->id)
            ->latest('id')
            ->limit(20)
            ->get(['id', 'attendance_week_id', 'lecturer_status', 'start_time', 'created_at']);

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
            $term = '%' . str_replace(['%', '_'], ['\\%', '\\_'], trim((string) $request->query('search'))) . '%';
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
        $repClassIds = RepCourseAccess::scopedClassIdsForCourse($rep, $course);
        $weeksQuery = $course->attendanceWeeks()->orderBy('week_number');
        if (\App\Support\SchemaFeatures::hasAttendanceWeeksClassId() && $repClassIds !== []) {
            $weeksQuery->where(function ($q) use ($repClassIds) {
                $q->whereIn('class_id', $repClassIds)->orWhereNull('class_id');
            });
        }
        $attendanceWeeks = $weeksQuery->get();

        // The page now drives its visual layout from $weeklyAttendees; the
        // legacy $dailyStats grid was removed in the per-week redesign, so we
        // no longer pay for that aggregation on every page load.
        $weeklyAttendees = $this->buildWeeklyAttendees($rep, $course, $attendanceWeeks, $repClassIds);
        $enrolledCount = $repClassIds === []
            ? 0
            : Student::query()->whereIn('class_id', $repClassIds)->count();

        return view('classrep.attendance-course', [
            'course' => $course,
            'attendances' => $attendances,
            'recentSessions' => $recentSessions,
            'attendanceWeeks' => $attendanceWeeks,
            'weeklyAttendees' => $weeklyAttendees,
            'enrolledCount' => $enrolledCount,
            'dashboardRole' => 'classrep',
        ]);
    }

    /**
     * For each teaching week, build the list of students from the rep's class
     * who marked attendance, plus the absent count derived from class size.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, AttendanceWeek>  $weeks
     * @param  list<int>  $repClassIds
     * @return \Illuminate\Support\Collection<int, array{week: AttendanceWeek, present: \Illuminate\Support\Collection<int, Attendance>, present_count: int, absent_count: int}>
     */
    private function buildWeeklyAttendees(Student $rep, Course $course, $weeks, array $repClassIds): \Illuminate\Support\Collection
    {
        if ($weeks->isEmpty() || $repClassIds === []) {
            return collect();
        }
        $enrolled = Student::query()->whereIn('class_id', $repClassIds)->count();
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
        if ($rep instanceof RedirectResponse) return $rep;

        if (!$this->canAccessStudent($rep, $student)) {
            abort(403, 'You can only reset passwords for students in your classes.');
        }

        $password = \Illuminate\Support\Str::password(12);
        $student->update(['password' => Hash::make($password)]);
        return back()->with('success', 'Password generated for ' . $student->index_number . '. New password: ' . $password);
    }
}
