<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\StudentDeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

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
        if (!$student || !$this->validatePassword($validated['password'], $student->password)) {
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

    private function validatePassword(string $input, ?string $stored): bool
    {
        if (empty($stored)) {
            return false;
        }
        if (str_starts_with($stored, '$2y$') || str_starts_with($stored, '$2a$')) {
            return Hash::check($input, $stored);
        }

        return $input === $stored;
    }
}
