<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\AttendanceController as LegacyAttendanceController;
use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Support\ApiEnvelope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Wraps legacy POST /api/attendance in the v1 envelope (does not change legacy handler).
 */
class AttendanceController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        /** @var Student $student */
        $student = $request->user();

        $dup = $request->duplicate(null, array_merge($request->all(), [
            'index_number' => $student->index_number,
        ]));

        $legacy = app(LegacyAttendanceController::class)->markAttendance($dup);
        $payload = json_decode($legacy->getContent(), true) ?? [];
        $status = $legacy->getStatusCode();

        if ($status >= 200 && $status < 300) {
            $msg = is_array($payload) ? ($payload['message'] ?? 'Attendance recorded') : 'OK';

            return response()->json(ApiEnvelope::success($payload, $msg), $status);
        }

        $msg = is_array($payload) ? ($payload['message'] ?? 'Request failed') : 'Request failed';

        return response()->json([
            'status' => false,
            'message' => $msg,
            'data' => $payload,
            'errors' => null,
            'meta' => null,
        ], $status);
    }
}
