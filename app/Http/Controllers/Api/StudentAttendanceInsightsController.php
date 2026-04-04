<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Student;
use App\Services\AttendanceInsightsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class StudentAttendanceInsightsController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $student = $this->studentFromBearer($request);
        if ($student instanceof JsonResponse) {
            return $student;
        }

        $courseIds = Course::query()
            ->where('class_id', $student->class_id)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $svc = app(AttendanceInsightsService::class);
        $trend = $svc->weeklyAttendanceTrend($courseIds, 8);
        $insights = $svc->trendInsights($trend);
        $consecutive = $svc->studentMaxConsecutiveMiss($student);

        return response()->json([
            'success' => true,
            'data' => [
                'attendance_trend' => $trend,
                'insights' => array_merge($insights, [
                    'consecutive_missed_sessions' => $consecutive,
                    'at_risk' => $consecutive >= 3,
                ]),
            ],
        ]);
    }

    private function studentFromBearer(Request $request): Student|JsonResponse
    {
        $bearer = $request->bearerToken();
        if ($bearer === null || $bearer === '') {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        $pat = PersonalAccessToken::findToken($bearer);
        if (! $pat || ! $pat->tokenable instanceof Student) {
            return response()->json(['message' => 'Invalid or expired token'], 401);
        }

        return $pat->tokenable;
    }
}
