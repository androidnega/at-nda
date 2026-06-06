<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceSession;
use App\Models\Course;
use App\Models\InAppNotification;
use App\Models\Lecturer;
use App\Models\Student;
use App\Services\AttendanceInsightsService;
use App\Services\FcmNotificationService;
use App\Support\ApiEnvelope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

class LecturerMobileApiController extends Controller
{
    public function dashboard(Request $request): JsonResponse
    {
        $lecturer = $this->lecturerFromBearer($request);
        if ($lecturer instanceof JsonResponse) {
            return $lecturer;
        }

        $courseIds = $lecturer->courses()->pluck('id')->map(fn ($id) => (int) $id)->all();
        $svc = app(AttendanceInsightsService::class);
        $summary = $svc->lecturerSummary((int) $lecturer->id, $courseIds);
        $classes = $svc->lecturerClassRows($courseIds);
        $trend = $svc->weeklyAttendanceTrend($courseIds, 8);

        return ApiEnvelope::success([
            'lecturer_id' => (int) $lecturer->id,
            'lecturer_name' => $lecturer->name,
            ...$summary,
            'classes' => $classes,
            'attendance_trend' => $trend,
            'insights' => $svc->trendInsights($trend),
        ], 'Lecturer dashboard loaded');
    }

    public function courseDetail(Request $request, int $course): JsonResponse
    {
        $lecturer = $this->lecturerFromBearer($request);
        if ($lecturer instanceof JsonResponse) {
            return $lecturer;
        }

        $c = Course::query()->where('id', $course)->where('lecturer_id', $lecturer->id)->first();
        if (! $c) {
            return ApiEnvelope::error('Course not found or not assigned to you', 404);
        }

        $svc = app(AttendanceInsightsService::class);
        $trend = $svc->weeklyAttendanceTrend([(int) $c->id], 12);
        $classIds = $c->class_id ? collect([(int) $c->class_id]) : collect();
        $flagged = $classIds->isNotEmpty() ? $svc->flaggedStudents($classIds, 3) : [];

        $sessions = AttendanceSession::query()
            ->where('course_id', $c->id)
            ->orderByDesc('start_time')
            ->limit(30)
            ->get(['id', 'start_time', 'end_time', 'is_active', 'session_code', 'mode'])
            ->map(fn ($s) => [
                'id' => $s->id,
                'start_time' => $s->start_time?->toIso8601String(),
                'end_time' => $s->end_time?->toIso8601String(),
                'is_active' => (bool) $s->is_active,
                'session_code' => $s->session_code,
                'mode' => $s->mode,
            ])
            ->values()
            ->all();

        return ApiEnvelope::success([
            'course' => [
                'course_id' => (int) $c->id,
                'course_name' => $c->course_name,
                'course_code' => $c->course_code,
                'student_count' => $c->class_id
                    ? (int) Student::query()->where('class_id', $c->class_id)->count()
                    : 0,
            ],
            'attendance_trend' => $trend,
            'sessions' => $sessions,
            'flagged_students' => $flagged,
        ], 'Course detail loaded');
    }

    public function sendDirectMessage(Request $request): JsonResponse
    {
        $lecturer = $this->lecturerFromBearer($request);
        if ($lecturer instanceof JsonResponse) {
            return $lecturer;
        }

        $validated = $request->validate([
            'course_id' => 'required|integer|min:1',
            'title' => 'required|string|max:120',
            'body' => 'required|string|max:500',
            'student_index_number' => 'nullable|string|max:80',
        ]);

        $course = Course::query()
            ->where('id', (int) $validated['course_id'])
            ->where('lecturer_id', $lecturer->id)
            ->first();
        if (! $course) {
            return ApiEnvelope::error('Course not found or not assigned to you', 404);
        }
        if (! $course->class_id) {
            return ApiEnvelope::error('This course has no class assigned yet', 422);
        }

        $title = trim((string) $validated['title']);
        $body = trim((string) $validated['body']);
        if ($title === '' || $body === '') {
            return ApiEnvelope::error('Title and message are required', 422);
        }

        $targetIndex = strtoupper(trim((string) ($validated['student_index_number'] ?? '')));
        $studentsQuery = Student::query()
            ->where('class_id', $course->class_id)
            ->select(['id', 'index_number']);
        if ($targetIndex !== '') {
            // Sargable equality against the UNIQUE index on `index_number`
            // (rows are normalised on save via the model's attribute mutator).
            $studentsQuery->where('index_number', $targetIndex);
        }
        $students = $studentsQuery->get();
        if ($students->isEmpty()) {
            return ApiEnvelope::error('No matching students found for this class', 422);
        }

        $now = now();
        $sendKey = (string) Str::uuid();
        $table = (new InAppNotification)->getTable();
        $rows = $students->map(function (Student $student) use ($course, $lecturer, $title, $body, $now, $sendKey) {
            return [
                'student_id' => (int) $student->id,
                'course_id' => (int) $course->id,
                'kind' => 'lecturer_direct_message',
                'title' => $title,
                'body' => $body,
                'starts_at' => $now,
                'delivery_key' => "lecturer-direct:{$lecturer->id}:{$course->id}:{$sendKey}:{$student->id}",
                'read_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        })->values()->all();

        DB::table($table)->insert($rows);

        app(FcmNotificationService::class)->sendDirectMessageToStudents(
            $students->pluck('id')->map(fn ($id) => (int) $id)->all(),
            $title,
            $body
        );

        return ApiEnvelope::success([
            'course_id' => (int) $course->id,
            'course_name' => (string) $course->course_name,
            'recipient_count' => $students->count(),
            'target_index_number' => $targetIndex !== '' ? $targetIndex : null,
        ], 'Message sent to students');
    }

    private function lecturerFromBearer(Request $request): Lecturer|JsonResponse
    {
        $bearer = $request->bearerToken();
        if ($bearer === null || $bearer === '') {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        $pat = PersonalAccessToken::findToken($bearer);
        if (! $pat || ! $pat->tokenable instanceof Lecturer) {
            return response()->json(['message' => 'Lecturer session required'], 403);
        }

        return $pat->tokenable;
    }
}
