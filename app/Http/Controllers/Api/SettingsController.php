<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;

class SettingsController extends Controller
{
    public function index(): JsonResponse
    {
        $settings = SystemSetting::get();

        return response()->json([
            'qr_code_enabled' => $settings->enable_qr ?? true,
            'ip_binding_enabled' => $settings->enable_ip_binding,
            'require_password_on_first_login' => $settings->require_password_on_first_login ?? true,
            'allow_multiple_index' => $settings->allow_multiple_index_on_device,
            'face_match_threshold' => (float) ($settings->face_match_threshold ?? 0.5),
            'attendance_data_version' => (int) ($settings->attendance_data_version ?? 0),
            'last_attendance_reset_at' => $settings->last_attendance_reset_at?->toIso8601String(),
        ]);
    }
}
