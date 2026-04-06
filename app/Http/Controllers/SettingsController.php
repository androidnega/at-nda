<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(): View
    {
        $settings = SystemSetting::get();

        return view('admin.settings', compact('settings'));
    }

    public function update(Request $request): RedirectResponse
    {
        $rules = [
            'enable_face_verification' => 'nullable|boolean',
            'enable_ip_binding' => 'nullable|boolean',
            'enable_qr' => 'nullable|boolean',
            'require_password_on_first_login' => 'nullable|boolean',
            'require_profile_image_on_onboarding' => 'nullable|boolean',
            'allow_multiple_index_on_device' => 'nullable|boolean',
            'face_match_threshold' => 'nullable|numeric|min:0.2|max:1.0',
        ];
        if ($request->session()->has('admin_id')) {
            $rules['rep_dashboard_theme'] = 'nullable|in:classic,pastel_analytics';
            $rules['student_dashboard_theme'] = 'nullable|in:classic,pastel_profile';
        }
        $validated = $request->validate($rules);

        $settings = SystemSetting::get();
        $payload = [
            'enable_face_verification' => $request->boolean('enable_face_verification'),
            'enable_ip_binding' => $request->boolean('enable_ip_binding'),
            'enable_qr' => $request->boolean('enable_qr'),
            'require_password_on_first_login' => $request->boolean('require_password_on_first_login'),
            'allow_multiple_index_on_device' => $request->boolean('allow_multiple_index_on_device'),
            'face_match_threshold' => (float) ($validated['face_match_threshold'] ?? 0.5),
        ];
        if (SystemSetting::hasRequireProfileImageColumn()) {
            $payload['require_profile_image_on_onboarding'] = $request->boolean('require_profile_image_on_onboarding');
        }
        if (SystemSetting::hasSmsCallLoggingColumn() && $request->session()->has('admin_id')) {
            $payload['enable_sms_call_logging'] = $request->boolean('enable_sms_call_logging');
        }
        if ($request->session()->has('admin_id')) {
            if (SystemSetting::hasRepDashboardThemeColumn() && $request->has('rep_dashboard_theme')) {
                $v = $validated['rep_dashboard_theme'] ?? 'classic';
                $payload['rep_dashboard_theme'] = $v ?: 'classic';
            }
            if (SystemSetting::hasStudentDashboardThemeColumn() && $request->has('student_dashboard_theme')) {
                $v = $validated['student_dashboard_theme'] ?? 'classic';
                $payload['student_dashboard_theme'] = $v ?: 'classic';
            }
        }

        $settings->update($payload);
        Cache::forget('api_v1_settings');

        return back()->with('success', 'Settings updated');
    }
}
