<?php

namespace App\Services\Api;

use App\Dto\ClassRep\ClassRepDashboardData;
use App\Dto\ClassRep\ClassRepStudentRow;
use App\Events\SessionLiveEvent;
use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\Course;
use App\Models\Student;
use App\Services\ActiveSessionListBuilder;
use App\Services\FcmNotificationService;
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

        return $student->load(['classReps', 'courseReps.course']);
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
        $student = Student::with(['classReps', 'courseReps.course'])
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
        if (! $course?->class_id) {
            return false;
        }
        $cr = $student->classReps()->where('class_id', $course->class_id)->first();
        if ($cr) {
            return $cr->isMainRep();
        }
        $courseRep = $student->courseReps()->where('course_id', $courseId)->first();

        return $courseRep?->isMainRep() ?? false;
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
                'attendanceSessions' => fn ($q) => $q->where('is_active', true)->orderByDesc('id'),
            ])
            ->whereIn('class_id', $classIds)
            ->orderBy('course_name')
            ->get();

        $items = [];
        foreach ($courses as $course) {
            $cr = $student->classReps()->where('class_id', $course->class_id)->first();
            $courseRep = $cr ? null : $student->courseReps()->where('course_id', $course->id)->first();
            $role = $cr?->role ?? $courseRep?->role ?? 'rep';
            $canOpen = $cr ? $cr->isMainRep() : ($courseRep?->isMainRep() ?? false);

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

        return new ClassRepDashboardData(
            role: 'class_rep',
            managedClassIds: $classIds,
            courses: $courses,
            hasActiveSession: $activeCount > 0,
            activeSessionsCount: $activeCount,
            studentsInClassesCount: $studentsCount,
            notice: is_string($notice) ? $notice : null,
        );
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
            );

            return $dto->toArray();
        })->values()->all();
    }

    public function openSession(Request $request, Student $student): JsonResponse
    {
        $validated = $request->validate([
            'course_id' => 'required|exists:courses,id',
            'mode' => 'required|in:location,qr,hybrid,wifi',
            'lecturer_status' => 'required|in:present,absent',
            'duration_minutes' => 'nullable|integer|min:5|max:480',
            'location_lat' => 'nullable|numeric',
            'location_lng' => 'nullable|numeric',
            'attendance_range_m' => 'nullable|integer|min:1|max:500',
            'allowed_wifi_ssid' => 'required_if:mode,wifi|nullable|string|max:128',
        ]);

        $course = Course::findOrFail($validated['course_id']);
        $classIds = $student->repManagedClassIds();
        if (! $course->class_id || ! $classIds->contains($course->class_id)) {
            return response()->json(['message' => 'You can only open sessions for courses in your class.'], 403);
        }
        if (! $this->isMainRepForCourse($student, $course->id)) {
            return response()->json(['message' => 'Only main reps can open sessions'], 403);
        }

        if (! $course->hasSchedule()) {
            return response()->json(['message' => 'Set day and time for this course first (timetable).'], 422);
        }

        $course->attendanceSessions()->where('is_active', true)->update(['is_active' => false]);

        $week = $course->createOrGetAttendanceWeekForToday();

        $duration = (int) ($validated['duration_minutes'] ?? 60);
        $expiresAt = $course->computeSessionExpiresAt($duration);
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
            'allowed_wifi_ssid' => $validated['mode'] === 'wifi' ? $wifiSsid : null,
            'is_active' => true,
            'session_token' => Str::random(32),
            'lecturer_id' => $course->lecturer_id,
            'venue_id' => $course->venue_id,
            'start_time' => now(),
            'end_time' => $expiresAt,
            'expires_at' => $expiresAt,
            'lecturer_status' => $validated['lecturer_status'],
            'location_lat' => $needsAnchor ? $lat : null,
            'location_lng' => $needsAnchor ? $lng : null,
            'attendance_range_m' => $needsAnchor ? $range : null,
        ]);

        app(FcmNotificationService::class)->sendSessionStartedToClass($course);

        $presentCount = Attendance::where('attendance_session_id', $sessionModel->id)
            ->where('status', 'present')
            ->count();
        event(new SessionLiveEvent($sessionModel->fresh(['course']), 'session_opened', ['present_count' => $presentCount]));

        $sessionModel->refresh();
        $sessionModel->loadMissing(['course.lecturer', 'course.venueRelation', 'lecturer', 'venue', 'attendanceWeek']);
        $rows = ActiveSessionListBuilder::buildRows(collect([$sessionModel]), $student);
        $row = $rows[0] ?? null;

        $activeMinutes = max(1, (int) ceil(($expiresAt->getTimestamp() - now()->getTimestamp()) / 60));

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
        if (! $course->class_id || ! $classIds->contains($course->class_id)) {
            return response()->json(['message' => 'You can only manage sessions for courses in your class.'], 403);
        }
        if (! $this->isMainRepForCourse($student, $session->course_id)) {
            return response()->json(['message' => 'Only main reps can close sessions'], 403);
        }

        $session->update(['is_active' => false]);
        $session->refresh();
        $session->load('course');
        $presentCount = Attendance::where('attendance_session_id', $session->id)
            ->where('status', 'present')
            ->count();
        event(new SessionLiveEvent($session, 'session_closed', ['present_count' => $presentCount]));

        return response()->json([
            'success' => true,
            'message' => 'Session closed.',
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
}
