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
        $dynamicUi = null;
        if (SystemSetting::hasDynamicUiColumn()) {
            // Casted to array by SystemSetting; keep null safe if older rows have null.
            $dynamicUi = is_array($settings->dynamic_ui ?? null) ? $settings->dynamic_ui : [];
        }

        return response()->json([
            'face_verification_enabled' => (bool) ($settings->enable_face_verification ?? false),
            'qr_code_enabled' => $settings->enable_qr ?? true,
            'ip_binding_enabled' => $settings->enable_ip_binding,
            'require_password_on_first_login' => $settings->require_password_on_first_login ?? true,
            'require_profile_image_on_onboarding' => $settings->require_profile_image_on_onboarding ?? true,
            'allow_multiple_index' => $settings->allow_multiple_index_on_device,
            'face_match_threshold' => (float) ($settings->face_match_threshold ?? 0.5),
            'attendance_data_version' => (int) ($settings->attendance_data_version ?? 0),
            'last_attendance_reset_at' => $settings->last_attendance_reset_at?->toIso8601String(),
            'enable_sms_call_logging' => SystemSetting::hasSmsCallLoggingColumn()
                && (bool) ($settings->enable_sms_call_logging ?? false),
            // Optional, safe: Flutter only renders when present and correctly shaped.
            'dynamic_ui' => $dynamicUi,
            'rep_dashboard_theme' => SystemSetting::hasRepDashboardThemeColumn()
                ? (string) ($settings->rep_dashboard_theme ?: 'classic')
                : 'classic',
            'student_dashboard_theme' => SystemSetting::hasStudentDashboardThemeColumn()
                ? (string) ($settings->student_dashboard_theme ?: 'classic')
                : 'classic',
            'mobile_app_theme_seed' => SystemSetting::hasMobileAppThemeSeedColumn()
                ? (string) ($settings->mobile_app_theme_seed ?: 'teal')
                : 'teal',
        ]);
    }
}
