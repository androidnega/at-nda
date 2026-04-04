<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceSession;
use App\Models\Course;
use App\Models\Lecturer;
use App\Models\Student;
use App\Services\AttendanceInsightsService;
use App\Support\ApiEnvelope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
