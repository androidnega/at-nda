<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentOnboardingController extends Controller
{
    public function check(Request $request): JsonResponse
    {
        $indexNumber = $request->query('index_number');
        if (!$indexNumber) {
            return response()->json(['onboarded' => false, 'student' => null]);
        }

        $student = Student::findByIndex($indexNumber);
        if (!$student) {
            return response()->json(['onboarded' => false, 'student' => null]);
        }

        return response()->json([
            'onboarded' => $student->isOnboarded(),
            'student' => $student->isOnboarded() ? null : [
                'id' => $student->id,
                'index_number' => $student->index_number,
                'first_name' => $student->first_name,
                'last_name' => $student->last_name,
            ],
        ]);
    }

    public function complete(Request $request): JsonResponse
    {
        $settings = SystemSetting::get();
        $rules = [
            'index_number' => 'required|string',
        ];
        if ($settings->require_profile_image_on_onboarding ?? true) {
            $rules['phone_number'] = 'required|string|min:10|max:20';
        } else {
            $rules['phone_number'] = 'nullable|string|min:10|max:20';
        }
        if ($settings->require_profile_image_on_onboarding ?? true) {
            $rules['profile_image'] = 'required|string';
        }
        $validated = $request->validate($rules);

        $student = Student::findByIndex($validated['index_number']);
        if (!$student) {
            return response()->json(['success' => false, 'message' => 'Student not found'], 404);
        }

        if ($student->isOnboarded(false)) {
            return response()->json(['success' => true, 'message' => 'Already onboarded']);
        }

        $ip = $request->ip();

        if ($settings->enable_ip_binding && !$settings->allow_multiple_index_on_device && $student->bound_ip && $student->bound_ip !== $ip) {
            return response()->json(['success' => false, 'message' => 'Device mismatch. Contact admin.'], 403);
        }

        if ($settings->enable_ip_binding && !$settings->allow_multiple_index_on_device && !$student->bound_ip) {
            $existing = Student::where('bound_ip', $ip)
                ->where('id', '!=', $student->id)
                ->exists();
            if ($existing) {
                return response()->json(['success' => false, 'message' => 'This device is already linked to another student.'], 403);
            }
        }

        if (($settings->require_profile_image_on_onboarding ?? true) && !empty($validated['profile_image'])) {
            if (!$student->saveProfileImageFromBase64($validated['profile_image'])) {
                return response()->json(['success' => false, 'message' => 'Invalid or corrupt profile image'], 422);
            }
        }


        if (!empty($validated['phone_number'])) {
            $student->phone_number = preg_replace('/[^0-9+]/', '', $validated['phone_number']);
        }
        if ($settings->enable_ip_binding && !$student->bound_ip) {
            $student->bound_ip = $ip;
        }
        $student->save();

        return response()->json(['success' => true, 'message' => 'Onboarding complete']);
    }

}
