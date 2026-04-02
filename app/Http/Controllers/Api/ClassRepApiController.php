<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceSession;
use App\Services\Api\ClassRepApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Legacy class-rep JSON routes for Flutter (unchanged response shapes).
 */
class ClassRepApiController extends Controller
{
    public function __construct(
        private readonly ClassRepApiService $classRepApi,
    ) {}

    /**
     * POST /api/rep/courses — courses the rep can see + optional active session row (Flutter shape).
     */
    public function courses(Request $request): JsonResponse
    {
        $student = $this->classRepApi->authenticate($request);
        if ($student instanceof JsonResponse) {
            return $student;
        }

        return response()->json($this->classRepApi->legacyCoursesPayload($student));
    }

    /**
     * POST /api/rep/sessions/open — same rules as web ClassRepController::openSession.
     */
    public function openSession(Request $request): JsonResponse
    {
        $student = $this->classRepApi->authenticate($request);
        if ($student instanceof JsonResponse) {
            return $student;
        }

        return $this->classRepApi->openSession($request, $student);
    }

    /**
     * POST /api/rep/sessions/{session}/close
     */
    public function closeSession(Request $request, AttendanceSession $session): JsonResponse
    {
        $student = $this->classRepApi->authenticate($request);
        if ($student instanceof JsonResponse) {
            return $student;
        }

        return $this->classRepApi->closeSession($request, $student, $session);
    }
}
