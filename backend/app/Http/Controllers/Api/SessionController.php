<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceSession;
use App\Models\Course;
use App\Models\Student;
use App\Services\ActiveSessionListBuilder;
use App\Services\MissedSessionWarningService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class SessionController extends Controller
{
    /**
     * Return all currently active sessions (time window + is_active), each with per-session already_marked.
     *
     * Response shape: { "sessions": [ ... ] } only (no legacy single-session keys).
     */
    public function active(Request $request): JsonResponse
    {
        try {
            AttendanceSession::deactivateExpiredSessions();

            $now = Carbon::now();

            $courseId = $request->query('course_id');
            $indexNumber = $request->query('index_number');
            $classId = $request->query('class_id');

            $student = $indexNumber ? Student::findByIndex($indexNumber) : null;

            $missedExtras = $this->missedWarningExtras($request, $indexNumber);

            if ($classId && $student && $student->class_id !== null && (int) $classId !== (int) $student->class_id) {
                return response()->json([
                    'sessions' => [],
                    'message' => 'Class does not match student record',
                ], 403);
            }

            $classIdFilter = $classId;
            if (!$classIdFilter && $indexNumber) {
                $classIdFilter = $student?->class_id;
            }

            if ($courseId) {
                $course = Course::find($courseId);
                if (!$course) {
                    return response()->json([
                        'sessions' => [],
                        'message' => 'Course not found',
                    ], 404);
                }
                if ($classIdFilter && $course->class_id !== (int) $classIdFilter) {
                    return response()->json($this->sessionsListPayload(collect(), $student, $missedExtras));
                }

                $sessions = $course->activeSessions();
                foreach ($sessions as $s) {
                    $s->loadMissing(['course.lecturer', 'course.venueRelation', 'lecturer', 'venue', 'attendanceWeek']);
                }

                Log::info('ACTIVE SESSIONS:', ['count' => $sessions->count(), 'course_id' => $course->id, 'now' => $now]);

                return response()->json($this->sessionsListPayload($sessions, $student, $missedExtras));
            }

            $query = AttendanceSession::with(['course.lecturer', 'course.venueRelation', 'lecturer', 'venue', 'attendanceWeek'])
                ->activeWithinTimeWindow();

            if ($classIdFilter) {
                $query->whereHas('course', fn ($q) => $q->where('class_id', $classIdFilter));
            }

            $sessions = $query->latest('id')->get();

            foreach ($sessions as $s) {
                $s->loadMissing(['course.lecturer', 'course.venueRelation', 'lecturer', 'venue', 'attendanceWeek']);
            }

            Log::info('ACTIVE SESSIONS:', ['count' => $sessions->count(), 'now' => $now]);

            return response()->json($this->sessionsListPayload($sessions, $student, $missedExtras));
        } catch (\Throwable $e) {
            Log::error('sessions/active failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'sessions' => [],
                'message' => 'Unable to load active session',
            ], 500);
        }
    }

    /**
     * @param \Illuminate\Support\Collection<int, AttendanceSession>|\Illuminate\Database\Eloquent\Collection<int, AttendanceSession> $sessions
     *
     * @param  array<string, mixed>  $extras  e.g. warnings + warnings_map when include_missed_warnings + password
     * @return array<string, mixed>
     */
    private function sessionsListPayload($sessions, ?Student $student, array $extras = []): array
    {
        return array_merge([
            'sessions' => ActiveSessionListBuilder::buildRows($sessions, $student),
        ], $extras);
    }

    /**
     * When include_missed_warnings=1 and password match, add warnings + warnings_map (missed ended sessions per course).
     *
     * @return array<string, mixed>
     */
    private function missedWarningExtras(Request $request, ?string $indexNumber): array
    {
        if (! $request->boolean('include_missed_warnings') || ! $indexNumber || ! $request->filled('password')) {
            return [];
        }

        $stu = Student::findByIndex($indexNumber);
        if (! $stu || ! $this->validateApiPassword((string) $request->query('password'), $stu->password)) {
            return [];
        }

        $minMissed = $request->query('min_missed');
        $lookback = $request->query('lookback_days');
        $payload = MissedSessionWarningService::buildPayload(
            $stu,
            $minMissed !== null && $minMissed !== '' ? (int) $minMissed : null,
            $lookback !== null && $lookback !== '' ? (int) $lookback : null
        );

        return [
            'warnings' => $payload['warnings'],
            'warnings_map' => $payload['warnings_map'],
        ];
    }

    /**
     * POST /api/sessions/{session}/location — main course rep updates session anchor (lat/lng) from device GPS.
     *
     * Body: lat, lng, accuracy (optional), index_number, password
     */
    public function updateLocation(Request $request, AttendanceSession $session): JsonResponse
    {
        $validated = $request->validate([
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
            'accuracy' => 'nullable|numeric|min:0',
            'index_number' => 'required|string',
            'password' => 'required|string',
        ]);

        AttendanceSession::deactivateExpiredSessions();
        $session->refresh();

        if (! $session->is_active || ! $session->isValid()) {
            return response()->json(['message' => 'Session is not active'], 422);
        }

        $indexUpper = strtoupper(trim($validated['index_number']));
        $student = Student::whereRaw('UPPER(TRIM(index_number)) = ?', [$indexUpper])->first();
        if (! $student) {
            return response()->json(['message' => 'Student not found'], 404);
        }

        if (! $this->validateApiPassword($validated['password'], $student->password)) {
            return response()->json(['message' => 'Wrong password'], 401);
        }

        if (! $this->isMainRepForCourse($student, (int) $session->course_id)) {
            return response()->json(['message' => 'Only the main course rep can update session location'], 403);
        }

        $session->location_lat = $validated['lat'];
        $session->location_lng = $validated['lng'];
        if (array_key_exists('accuracy', $validated) && $validated['accuracy'] !== null) {
            $session->gps_accuracy = (float) $validated['accuracy'];
        }
        $session->save();

        return response()->json([
            'message' => 'Session location updated',
            'session_id' => $session->id,
            'lat' => (float) $session->location_lat,
            'lng' => (float) $session->location_lng,
            'gps_accuracy' => $session->gps_accuracy !== null ? (float) $session->gps_accuracy : null,
            'updated_at' => $session->updated_at?->toIso8601String(),
        ]);
    }

    private function validateApiPassword(string $input, ?string $stored): bool
    {
        if (empty($stored)) {
            return false;
        }
        if (str_starts_with($stored, '$2y$') || str_starts_with($stored, '$2a$')) {
            return Hash::check($input, $stored);
        }

        return $input === $stored;
    }

    /**
     * Same rule as web ClassRepController::requireMainRep.
     */
    private function isMainRepForCourse(Student $student, int $courseId): bool
    {
        $course = Course::find($courseId);
        if (! $course?->class_id) {
            return false;
        }
        $cr = $student->classReps()->where('class_id', $course->class_id)->first();

        return $cr?->isMainRep() ?? false;
    }

    /**
     * GET /api/sessions/current-qr/{session} — static qr_token for this session (same as /sessions/active).
     */
    public function currentQr(AttendanceSession $session): JsonResponse
    {
        AttendanceSession::deactivateExpiredSessions();
        $session->refresh();

        if (! $session->is_active || ! $session->isValid() || ! ActiveSessionListBuilder::isUsableActiveSession($session)) {
            return response()->json(['message' => 'Session not found'], 404);
        }

        $session->ensureSignedQrTokenFresh();

        $session->loadMissing('attendanceWeek');

        return response()->json([
            'qr_token' => $session->qr_token,
            'session_id' => $session->id,
            'session_index' => (int) ($session->session_index ?? 1),
            'week_number' => $session->attendanceWeek?->week_number,
        ]);
    }

}
