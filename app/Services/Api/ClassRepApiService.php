<?php

namespace App\Services\Api;

use App\Dto\ClassRep\ClassRepDashboardData;
use App\Dto\ClassRep\ClassRepStudentRow;
use App\Events\SessionLiveEvent;
use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\AttendanceWeek;
use App\Models\Course;
use App\Models\Student;
use App\Models\SystemSetting;
use App\Services\ActiveSessionListBuilder;
use App\Services\AttendanceInsightsService;
use App\Services\ClassSessionScopeService;
use App\Services\FcmNotificationService;
use App\Support\RepCourseAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Centralized class-rep API rules (web parity). Used by legacy /rep/* and REST /class-rep/* routes.
 */
class ClassRepApiService
{
    /**
     * Resolve student from Authorization: Bearer <Sanctum token> (mobile ability).
     */
    private function studentFromBearer(Request $request): Student|JsonResponse|null
    {
        $bearer = $request->bearerToken();
        if ($bearer === null || $bearer === '') {
            return null;
        }

        $pat = PersonalAccessToken::findToken($bearer);
        if (! $pat || ! $pat->tokenable instanceof Student) {
            return response()->json(['message' => 'Invalid or expired token'], 401);
        }

        /** @var Student $student */
        $student = $pat->tokenable;

        // Load class reps only.
        return $student->load(['classReps']);
    }

    public function authenticate(Request $request): Student|JsonResponse
    {
        $fromBearer = $this->studentFromBearer($request);
        if ($fromBearer instanceof JsonResponse) {
            return $fromBearer;
        }
        if ($fromBearer instanceof Student) {
            if (! $fromBearer->isClassRep()) {
                return response()->json(['message' => 'This account is not a class rep'], 403);
            }

            return $fromBearer;
        }

        $validated = $request->validate([
            'index_number' => 'required|string',
            'password' => 'required|string',
        ]);

        $indexUpper = strtoupper(trim($validated['index_number']));
        $student = Student::with(['classReps'])
            ->whereRaw('UPPER(TRIM(index_number)) = ?', [$indexUpper])
            ->first();
        if (! $student || ! $this->validateApiPassword($validated['password'], $student->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        if (! $student->isClassRep()) {
            return response()->json(['message' => 'This account is not a class rep'], 403);
        }

        return $student;
    }

    /**
     * Merge credentials from JSON body or query (GET dashboard/students).
     */
    public function authenticateFlexible(Request $request): Student|JsonResponse
    {
        if ($request->bearerToken()) {
            return $this->authenticate($request);
        }

        $index = $request->input('index_number') ?? $request->query('index_number');
        $password = $request->input('password') ?? $request->query('password');
        if ($index === null || $index === '' || $password === null || $password === '') {
            return response()->json(['message' => 'index_number and password are required'], 422);
        }
        $request->merge([
            'index_number' => $index,
            'password' => $password,
        ]);

        return $this->authenticate($request);
    }

    public function validateApiPassword(string $input, ?string $stored): bool
    {
        if (empty($stored)) {
            return false;
        }
        if (str_starts_with($stored, '$2y$') || str_starts_with($stored, '$2a$')) {
            return Hash::check($input, $stored);
        }

        return $input === $stored;
    }

    public function isMainRepForCourse(Student $student, int $courseId): bool
    {
        $course = Course::find($courseId);

        return $course && RepCourseAccess::isMainRepForCourse($student, $course);
    }

    /**
     * Legacy shape for POST /api/rep/courses (unchanged).
     *
     * @return array{is_class_rep: true, courses: list<array<string, mixed>>}|array{is_class_rep: true, courses: array<int, never>, message: string}
     */
    public function legacyCoursesPayload(Student $student): array
    {
        $classIds = $student->repManagedClassIds();
        if ($classIds->isEmpty()) {
            return [
                'is_class_rep' => true,
                'courses' => [],
                'message' => 'Rep role has no linked class courses yet. Ask admin to verify class rep assignment.',
            ];
        }

        $courses = Course::query()
            ->with([
                'schoolClass',
                'schoolClasses',
                'attendanceSessions' => fn ($q) => $q->where('is_active', true)->orderByDesc('id'),
            ])
            ->forManagedClasses($classIds)
            ->orderBy('course_name')
            ->get();

        $items = [];
        foreach ($courses as $course) {
            $cr = RepCourseAccess::classRepForCourse($student, $course);
            $role = $cr?->role ?? 'rep';
            $canOpen = $cr?->isMainRep() ?? false;

            $activeSession = null;
            foreach ($course->attendanceSessions as $s) {
                if ($s->is_active && $s->isValid()) {
                    $activeSession = $s;
                    break;
                }
            }

            $activeRow = null;
            if ($activeSession !== null) {
                $activeSession->loadMissing(['course.lecturer', 'course.venueRelation', 'lecturer', 'venue', 'attendanceWeek']);
                $rows = ActiveSessionListBuilder::buildRows(collect([$activeSession]), $student);
                $activeRow = $rows[0] ?? null;
            }

            $items[] = [
                'course_id' => $course->id,
                'course_name' => $course->course_name,
                'course_code' => $course->course_code,
                'class_id' => $course->class_id,
                'rep_role' => $role,
                'can_open_session' => $canOpen,
                'has_schedule' => $course->hasSchedule(),
                'active_session' => $activeRow,
            ];
        }

        return [
            'is_class_rep' => true,
            'courses' => $items,
        ];
    }

    public function buildDashboard(Student $student): ClassRepDashboardData
    {
        $legacy = $this->legacyCoursesPayload($student);
        $courses = $legacy['courses'] ?? [];
        $notice = $legacy['message'] ?? null;
        $activeCount = 0;
        foreach ($courses as $row) {
            if (! empty($row['active_session'])) {
                $activeCount++;
            }
        }
        $managed = $student->repManagedClassIds();
        $classIds = $managed->values()->map(fn ($id) => (int) $id)->all();
        $studentsCount = $managed->isEmpty()
            ? 0
            : (int) Student::query()->whereIn('class_id', $managed)->count();

        $trend = [];
        $insights = ['delta_pct' => 0.0, 'direction' => 'flat', 'weekly_trend_label' => '—'];
        $flagged = [];
        if (! $managed->isEmpty()) {
            $courseIds = Course::query()->forManagedClasses($managed)->pluck('id')->map(fn ($id) => (int) $id)->all();
            $svc = app(AttendanceInsightsService::class);
            $trend = $svc->weeklyAttendanceTrend($courseIds, 8);
            $insights = array_merge(
                $svc->trendInsights($trend),
                ['weekly_trend_label' => $trend === [] ? 'No completed sessions yet' : 'Last '.$this->trendWeeksLabel(count($trend)).' weeks'],
            );
            $flagged = $svc->flaggedStudents($managed, 3);
        }

        return new ClassRepDashboardData(
            role: 'class_rep',
            managedClassIds: $classIds,
            courses: $courses,
            hasActiveSession: $activeCount > 0,
            activeSessionsCount: $activeCount,
            studentsInClassesCount: $studentsCount,
            notice: is_string($notice) ? $notice : null,
            attendanceTrend: $trend,
            insights: $insights,
            flaggedStudents: $flagged,
        );
    }

    private function trendWeeksLabel(int $n): string
    {
        return $n <= 1 ? '1 week' : (string) $n.' weeks';
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function studentsPayload(Student $student): array
    {
        $classIds = $student->repManagedClassIds();
        if ($classIds->isEmpty()) {
            return [];
        }

        $rows = Student::query()
            ->with('schoolClass')
            ->whereIn('class_id', $classIds)
            ->orderBy('index_number')
            ->get();

        return $rows->map(function (Student $s) {
            $dto = new ClassRepStudentRow(
                id: (int) $s->id,
                indexNumber: (string) $s->index_number,
                name: $s->getDisplayNameOrIndex(),
                classId: $s->class_id ? (int) $s->class_id : null,
                className: $s->schoolClass?->name,
                profileImage: $s->profile_image ? (string) $s->profile_image : null,
            );

            return $dto->toArray();
        })->values()->all();
    }

    public function openSession(Request $request, Student $student): JsonResponse
    {
        $system = SystemSetting::get();
        $globalAttendanceMode = SystemSetting::hasAttendanceModeColumns()
            ? (string) ($system->attendance_mode ?: SystemSetting::ATTENDANCE_MODE_INSTANT)
            : SystemSetting::ATTENDANCE_MODE_INSTANT;
        $forcedMode = null;
        if ($globalAttendanceMode === SystemSetting::ATTENDANCE_MODE_CHECKIN_CHECKOUT) {
            $forcedMode = 'location';
        } else {
            $instantType = SystemSetting::hasAttendanceModeColumns()
                ? (string) ($system->instant_mode_type ?: SystemSetting::INSTANT_MODE_LOCATION_QR)
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
            // Optional override: rep can choose a week number; otherwise use the course's default "next_week_number".
            'week_number' => 'nullable|integer|min:1|max:500',
            'location_lat' => 'nullable|numeric',
            'location_lng' => 'nullable|numeric',
            'attendance_range_m' => 'nullable|integer|min:1|max:500',
            'allowed_wifi_ssid' => 'required_if:mode,wifi|nullable|string|max:128',
        ]);
        $validated['mode'] = $forcedMode;

        $course = Course::findOrFail($validated['course_id']);
        $classIds = $student->repManagedClassIds();
        if (! $course->overlapsClassIds($classIds)) {
            return response()->json(['message' => 'You can only open sessions for courses in your class.'], 403);
        }
        if (! $this->isMainRepForCourse($student, $course->id)) {
            return response()->json(['message' => 'Only main reps can open sessions'], 403);
        }

        if (! $course->hasSchedule()) {
            return response()->json(['message' => 'Set day and time for this course first (timetable).'], 422);
        }

        RepCourseAccess::deactivateSessionsForCourse($rep, $course);

        $week = null;
        $weekNumber = $validated['week_number'] ?? null;
        if ($weekNumber !== null) {
            $today = now()->toDateString();
            $existing = AttendanceWeek::query()
                ->where('course_id', $course->id)
                ->where('week_date', $today)
                ->first();

            if ($existing) {
                $existing->update(['week_number' => (int) $weekNumber]);
                $week = $existing;
            } else {
                $week = AttendanceWeek::create([
                    'course_id' => $course->id,
                    'week_number' => (int) $weekNumber,
                    'week_date' => $today,
                ]);
            }

            // Advance default week seed so the next auto week doesn't re-use the same number.
            $maxWeek = (int) ($course->attendanceWeeks()->max('week_number') ?? $weekNumber);
            $currentNext = (int) ($course->next_week_number ?? 0);
            $course->update([
                'next_week_number' => (int) max($currentNext, $maxWeek + 1, ((int) $weekNumber) + 1),
            ]);
        } else {
            $week = $course->createOrGetAttendanceWeekForToday();
        }

        $duration = (int) ($validated['duration_minutes'] ?? 60);
        $expectedEnd = $course->computeSessionExpiresAt($duration);
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
                return response()->json([
                    'message' => 'Set a default location on the course (admin) or send location_lat, location_lng, and attendance_range_m from the device.',
                ], 422);
            }
        }

        $sessionModel = AttendanceSession::create([
            'course_id' => $course->id,
            'session_index' => AttendanceSession::nextIndexForCourse($course->id),
            'attendance_week_id' => $week->id,
            'mode' => $validated['mode'],
            'attendance_mode' => $globalAttendanceMode,
            'allowed_wifi_ssid' => $validated['mode'] === 'wifi' ? $wifiSsid : null,
            'is_active' => true,
            'checkout_enabled' => false,
            'session_token' => Str::random(32),
            'lecturer_id' => $course->lecturer_id,
            'venue_id' => $course->venue_id,
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

        $sessionModel->refresh();
        $sessionModel->loadMissing(['course.lecturer', 'course.venueRelation', 'lecturer', 'venue', 'attendanceWeek']);
        $rows = ActiveSessionListBuilder::buildRows(collect([$sessionModel]), $student);
        $row = $rows[0] ?? null;

        $activeMinutes = max(1, (int) ceil(($expectedEnd->getTimestamp() - now()->getTimestamp()) / 60));

        return response()->json([
            'success' => true,
            'message' => 'Session opened. Week '.$week->week_number.'. Active for ~'.$activeMinutes.' min.',
            'week_number' => $week->week_number,
            'session' => $row,
        ]);
    }

    public function closeSession(Request $request, Student $student, AttendanceSession $session): JsonResponse
    {
        $course = $session->course;
        if (! $course) {
            return response()->json(['message' => 'Course not found'], 404);
        }
        $classIds = $student->repManagedClassIds();
        if (! $course->overlapsClassIds($classIds)) {
            return response()->json(['message' => 'You can only manage sessions for courses in your class.'], 403);
        }
        if (! $this->isMainRepForCourse($student, $session->course_id)) {
            return response()->json(['message' => 'Only main reps can close sessions'], 403);
        }

        if ($session->isCheckInCheckoutMode()) {
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

        return response()->json([
            'success' => true,
            'message' => $session->isCheckInCheckoutMode()
                ? 'Class ended. Checkout is now enabled.'
                : 'Session closed.',
        ]);
    }

    /**
     * Close by session id (POST body) for /api/attendance/close.
     */
    public function closeSessionById(Request $request, Student $student): JsonResponse
    {
        $validated = $request->validate([
            'session_id' => 'required|integer|exists:attendance_sessions,id',
        ]);
        $session = AttendanceSession::findOrFail((int) $validated['session_id']);

        return $this->closeSession($request, $student, $session);
    }

    /**
     * Extend an active session's marking time by increasing end_time/expires_at.
     */
    public function extendSessionById(Request $request, Student $student): JsonResponse
    {
        $validated = $request->validate([
            'session_id' => 'required|integer|exists:attendance_sessions,id',
            'additional_minutes' => 'required|integer|min:5|max:480',
        ]);

        $session = AttendanceSession::query()->with(['course'])->findOrFail((int) $validated['session_id']);
        $course = $session->course;

        if (! $course) {
            return response()->json(['message' => 'Course not found'], 404);
        }
        $classIds = $student->repManagedClassIds();
        if (! $course->overlapsClassIds($classIds)) {
            return response()->json(['message' => 'You can only manage sessions for your class courses.'], 403);
        }
        if (! $this->isMainRepForCourse($student, (int) $session->course_id)) {
            return response()->json(['message' => 'Only main reps can extend sessions'], 403);
        }

        // Ensure the session is still "active" in the time window.
        AttendanceSession::deactivateExpiredSessions();
        $session->refresh();
        if (! $session->isValid()) {
            return response()->json(['message' => 'Session is not active/expired'], 422);
        }

        $additionalMinutes = (int) $validated['additional_minutes'];

        $currentEnd = $session->end_time ?? $session->expires_at;
        if (! $currentEnd) {
            $currentEnd = now();
        }

        $newEnd = $currentEnd->copy()->addMinutes($additionalMinutes);

        // If the course is on its scheduled weekday, cap to the timetable end time so the
        // "extended" window never runs past the official slot.
        if ($course->hasSchedule()) {
            $todayName = now()->format('l');
            if (strcasecmp(trim((string) $course->day_of_week), $todayName) === 0) {
                $slotEnd = now()->copy()->setTimeFromTimeString(
                    \Carbon\Carbon::parse($course->end_time)->format('H:i:s')
                );
                if (! $slotEnd->isPast()) {
                    $newEnd = $newEnd->lessThanOrEqualTo($slotEnd) ? $newEnd : $slotEnd;
                }
            }
        }

        // Persist the new window.
        $session->update([
            'end_time' => $newEnd,
            'expires_at' => $newEnd,
        ]);
        $session->refresh();

        $presentCount = Attendance::where('attendance_session_id', $session->id)
            ->where('status', 'present')
            ->count();
        event(new SessionLiveEvent($session->fresh(['course']), 'session_extended', ['present_count' => $presentCount]));

        return response()->json([
            'success' => true,
            'message' => 'Session extended.',
            'session_id' => $session->id,
            'end_time' => $session->end_time?->toIso8601String(),
        ]);
    }

    /**
     * Class-scoped student profile + attendance summary for class-rep mobile detail screen.
     *
     * @return array<string, mixed>
     */
    public function buildStudentDetailPayload(Student $target): array
    {
        $target->loadMissing(['schoolClass.faculty', 'schoolClass.department', 'department.faculty']);

        $classId = $target->class_id ? (int) $target->class_id : null;
        $courseIds = $classId
            ? Course::query()->forManagedClasses([$classId])->pluck('id')->all()
            : [];

        $present = 0;
        $total = 0;
        if ($courseIds !== []) {
            $present = (int) Attendance::query()
                ->where('student_id', $target->id)
                ->whereIn('course_id', $courseIds)
                ->where('status', 'present')
                ->count();
            $total = (int) Attendance::query()
                ->where('student_id', $target->id)
                ->whereIn('course_id', $courseIds)
                ->count();
        }

        $rate = $total > 0 ? round(100.0 * $present / $total, 1) : null;

        $recent = [];
        if ($courseIds !== []) {
            $recent = Attendance::query()
                ->where('student_id', $target->id)
                ->whereIn('course_id', $courseIds)
                ->with(['course', 'attendanceWeek'])
                ->latest('attendance_time')
                ->limit(25)
                ->get()
                ->map(static function (Attendance $a): array {
                    return [
                        'course_name' => $a->course?->course_name,
                        'course_code' => $a->course?->course_code,
                        'week_number' => $a->attendanceWeek?->week_number,
                        'status' => $a->status,
                        'attendance_time' => $a->attendance_time?->toIso8601String(),
                    ];
                })->values()->all();
        }

        $facultyName = $target->schoolClass?->faculty?->name
            ?? $target->department?->faculty?->name;
        $deptName = $target->schoolClass?->department?->name
            ?? $target->department?->name;

        return [
            'student' => [
                'id' => (int) $target->id,
                'index_number' => (string) $target->index_number,
                'name' => $target->getDisplayNameOrIndex(),
                'class_name' => $target->schoolClass?->name,
                'class_id' => $classId,
                'phone_number' => $target->phone_number,
                'faculty_name' => $facultyName,
                'department_name' => $deptName,
                'profile_image' => $target->profile_image ? (string) $target->profile_image : null,
            ],
            'stats' => [
                'present_count' => $present,
                'total_marked' => $total,
                'attendance_rate_pct' => $rate,
            ],
            'recent_attendance' => $recent,
        ];
    }
}
