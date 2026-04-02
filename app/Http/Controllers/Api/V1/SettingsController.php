<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Support\ApiEnvelope;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * Public settings — cached briefly (safe: not user-specific).
 */
class SettingsController extends Controller
{
    public function index(): JsonResponse
    {
        $data = Cache::remember('api_v1_settings', 60, function () {
            $settings = SystemSetting::get();

            return [
                'face_verification_enabled' => $settings->enable_face_verification ?? true,
                'qr_code_enabled' => $settings->enable_qr ?? true,
                'ip_binding_enabled' => $settings->enable_ip_binding,
                'require_password_on_first_login' => $settings->require_password_on_first_login ?? true,
                'require_profile_image_on_onboarding' => $settings->require_profile_image_on_onboarding ?? true,
                'allow_multiple_index' => $settings->allow_multiple_index_on_device,
                'face_match_threshold' => (float) ($settings->face_match_threshold ?? 0.5),
                'attendance_data_version' => (int) ($settings->attendance_data_version ?? 0),
                'last_attendance_reset_at' => $settings->last_attendance_reset_at?->toIso8601String(),
            ];
        });

        return response()->json(ApiEnvelope::success($data, 'Settings loaded', [
            'cached_seconds' => 60,
        ]));
    }
}
