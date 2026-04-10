<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ActiveSessionResource;
use App\Models\AttendanceSession;
use App\Models\Course;
use App\Models\Student;
use App\Services\ActiveSessionListBuilder;
use App\Services\MissedSessionWarningService;
use App\Support\ApiEnvelope;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Versioned active sessions — same session rows as legacy; wrapped in ApiEnvelope.
 * Identity: Bearer token (no index_number required for already_marked).
 */
class SessionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Student $auth */
        $auth = $request->user();
        $perPage = min(100, max(1, (int) $request->query('per_page', 20)));
        $page = max(1, (int) $request->query('page', 1));
        $courseId = $request->query('course_id');

        $cacheKey = sprintf(
            'api_v1_sessions:%d:%d:%d:%s',
            $auth->id,
            $page,
            $perPage,
            (string) ($courseId ?? 'all')
        );

        $cached = Cache::remember($cacheKey, 30, function () use ($auth, $perPage, $courseId) {
            $query = AttendanceSession::query()
                ->with(['course.lecturer', 'course.venueRelation', 'lecturer', 'venue', 'attendanceWeek'])
                ->activeWithinTimeWindow()
                ->whereHas('course', fn ($q) => $q->where('class_id', $auth->class_id))
                ->latest('id');

            if ($courseId) {
                $query->where('course_id', (int) $courseId);
            }

            return $query->paginate($perPage);
        });

        $rows = ActiveSessionListBuilder::buildRows($cached->getCollection(), $auth);

        return response()->json(ApiEnvelope::success(
            [
                'sessions' => ActiveSessionResource::collection(collect($rows)),
            ],
            'Sessions fetched',
            [
                'pagination' => [
                    'current_page' => $cached->currentPage(),
                    'per_page' => $cached->perPage(),
                    'total' => $cached->total(),
                    'last_page' => $cached->lastPage(),
                ],
                'cached_seconds' => 30,
            ]
        ));
    }

    public function active(Request $request): JsonResponse
    {
        try {
            AttendanceSession::deactivateExpiredSessions();
            AttendanceSession::query()
                ->where('attendance_mode', 'checkin_checkout')
                ->where('checkout_enabled', false)
                ->whereNotNull('expected_end_time')
                ->where('expected_end_time', '<=', now())
                ->update(['checkout_enabled' => true]);

            $now = Carbon::now();

            /** @var Student $auth */
            $auth = $request->user();

            $courseId = $request->query('course_id');
            $classId = $request->query('class_id');

            $classIdFilter = $classId ?: $auth->class_id;

            if ($classId && $auth->class_id !== null && (int) $classId !== (int) $auth->class_id) {
                return ApiEnvelope::errorResponse('Class does not match student record', 403);
            }

            $missedExtras = [];
            if ($request->boolean('include_missed_warnings')) {
                $minMissed = $request->query('min_missed');
                $lookback = $request->query('lookback_days');
                $payload = MissedSessionWarningService::buildPayload(
                    $auth,
                    $minMissed !== null && $minMissed !== '' ? (int) $minMissed : null,
                    $lookback !== null && $lookback !== '' ? (int) $lookback : null
                );
                $missedExtras = [
                    'warnings' => $payload['warnings'],
                    'warnings_map' => $payload['warnings_map'],
                ];
            }

            if ($courseId) {
                $course = Course::find($courseId);
                if (! $course) {
                    return ApiEnvelope::errorResponse('Course not found', 404);
                }
                if ($classIdFilter && $course->class_id !== (int) $classIdFilter) {
                    $body = array_merge([
                        'sessions' => ActiveSessionResource::collection(collect()),
                    ], $missedExtras);

                    return response()->json(ApiEnvelope::success($body, 'Sessions fetched'));
                }

                $sessions = $course->activeSessions();
                foreach ($sessions as $s) {
                    $s->loadMissing(['course.lecturer', 'course.venueRelation', 'lecturer', 'venue', 'attendanceWeek']);
                }

                $sessions = ActiveSessionListBuilder::mergePendingCheckoutSessions($sessions, $auth, (int) $course->id);

                Log::info('api.v1.ACTIVE_SESSIONS', ['count' => $sessions->count(), 'course_id' => $course->id, 'now' => $now]);

                $body = array_merge([
                    'sessions' => ActiveSessionResource::collection(
                        collect(ActiveSessionListBuilder::buildRows($sessions, $auth))
                    ),
                ], $missedExtras);

                return response()->json(ApiEnvelope::success($body, 'Sessions fetched'));
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

            $sessions = ActiveSessionListBuilder::mergePendingCheckoutSessions($sessions, $auth, null);

            Log::info('api.v1.ACTIVE_SESSIONS', ['count' => $sessions->count(), 'now' => $now]);

            $body = array_merge([
                'sessions' => ActiveSessionResource::collection(
                    collect(ActiveSessionListBuilder::buildRows($sessions, $auth))
                ),
            ], $missedExtras);

            return response()->json(ApiEnvelope::success($body, 'Sessions fetched'));
        } catch (\Throwable $e) {
            Log::error('api.v1.sessions/active failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiEnvelope::errorResponse('Unable to load active sessions', 500);
        }
    }
}
