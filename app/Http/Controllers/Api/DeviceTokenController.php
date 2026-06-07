<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\StudentDeviceToken;
use App\Support\PasswordPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceTokenController extends Controller
{
    /**
     * POST /api/device-token — store Firebase token for push (authenticated by index + password).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'index_number' => 'required|string',
            'password' => 'required|string',
            'firebase_token' => 'required|string',
            'student_id' => 'nullable|integer|exists:students,id',
        ]);

        $student = Student::findByIndex($validated['index_number']);
        if (! $student || ! PasswordPolicy::matches($validated['password'], $student->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        if (isset($validated['student_id']) && (int) $validated['student_id'] !== (int) $student->id) {
            return response()->json(['message' => 'student_id does not match account'], 403);
        }

        StudentDeviceToken::updateOrCreate(
            ['student_id' => $student->id],
            ['firebase_token' => $validated['firebase_token']]
        );

        return response()->json([
            'message' => 'Device token saved',
            'student_id' => $student->id,
        ]);
    }

}
