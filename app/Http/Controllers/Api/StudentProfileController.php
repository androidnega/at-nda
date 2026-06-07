<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Support\PasswordPolicy;
use App\Support\StudentApiPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentProfileController extends Controller
{
    /**
     * Update student profile from the mobile app (syncs to web — same DB as /student/profile).
     * POST /api/student/profile
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'index_number' => 'required|string',
            'password' => 'required|string',
            'phone_number' => 'nullable|string|max:30',
            'profile_image' => 'nullable|string',
            'first_name' => 'nullable|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
        ]);

        $student = Student::findByIndex($validated['index_number']);
        if (! $student || ! PasswordPolicy::matches($validated['password'], $student->password)) {
            return response()->json(['success' => false, 'message' => 'Invalid index number or password'], 401);
        }

        if (!empty($validated['first_name'])) {
            $student->first_name = $validated['first_name'];
        }
        if (array_key_exists('middle_name', $validated)) {
            $student->middle_name = $validated['middle_name'] !== null && $validated['middle_name'] !== ''
                ? $validated['middle_name']
                : null;
        }
        if (!empty($validated['last_name'])) {
            $student->last_name = $validated['last_name'];
        }
        if (!empty($validated['phone_number'])) {
            $student->phone_number = preg_replace('/[^0-9+]/', '', $validated['phone_number']);
        }
        if (!empty($validated['profile_image'])) {
            if (!$student->saveProfileImageFromBase64($validated['profile_image'])) {
                return response()->json(['success' => false, 'message' => 'Invalid profile_image (expect data:image/...;base64,... )'], 422);
            }
        }

        $student->save();

        $user = StudentApiPayload::forUser($student);

        return response()->json([
            'success' => true,
            'message' => 'Profile updated',
            'user' => $user,
            'student' => $user,
        ]);
    }

    /**
     * POST /api/update-profile — first, middle, last name (same auth as /api/student/profile).
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'index_number' => 'required|string',
            'password' => 'required|string',
            'first_name' => 'sometimes|nullable|string|max:255',
            'middle_name' => 'sometimes|nullable|string|max:255',
            'last_name' => 'sometimes|nullable|string|max:255',
        ]);

        $student = Student::findByIndex($validated['index_number']);
        if (! $student || ! PasswordPolicy::matches($validated['password'], $student->password)) {
            return response()->json(['success' => false, 'message' => 'Invalid index number or password'], 401);
        }

        if (array_key_exists('first_name', $validated)) {
            $student->first_name = $validated['first_name'];
        }
        if (array_key_exists('middle_name', $validated)) {
            $m = $validated['middle_name'];
            $student->middle_name = ($m !== null && $m !== '') ? $m : null;
        }
        if (array_key_exists('last_name', $validated)) {
            $student->last_name = $validated['last_name'];
        }

        $student->save();

        $user = StudentApiPayload::forUser($student);

        return response()->json([
            'success' => true,
            'message' => 'Profile updated',
            'user' => $user,
            'student' => $user,
        ]);
    }

}
