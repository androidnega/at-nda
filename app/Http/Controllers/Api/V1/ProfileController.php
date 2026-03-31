<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\StudentResource;
use App\Models\Student;
use App\Support\ApiEnvelope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    /**
     * GET /api/v1/profile — current student (Bearer token).
     */
    public function show(Request $request): JsonResponse
    {
        /** @var Student $student */
        $student = $request->user();

        return response()->json(ApiEnvelope::success(
            ['user' => new StudentResource($student)],
            'Profile loaded'
        ));
    }
}
