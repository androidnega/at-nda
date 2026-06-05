<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use App\Services\StudentPasswordResetService;
use App\Support\AuthHeroImage;
use App\Support\MailRuntimeConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(): View
    {
        $settings = SystemSetting::get();
        AuthHeroImage::ensureColumn();
        $authHeroPreviewUrl = AuthHeroImage::previewUrl();
        $authHeroUsingCustom = AuthHeroImage::isUsingCustomUpload();

        return view('admin.settings', compact('settings', 'authHeroPreviewUrl', 'authHeroUsingCustom'));
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
            'attendance_mode' => 'nullable|in:instant,checkin_checkout',
            'instant_mode_type' => 'nullable|in:location,location_qr,wifi',
        ];
        if ($request->session()->has('admin_id')) {
            $rules['rep_dashboard_theme'] = 'nullable|in:classic,pastel_analytics,noir_task,team_reach,violet_calendar,midnight_control';
            $rules['student_dashboard_theme'] = 'nullable|in:classic,pastel_profile,noir_task,team_reach,violet_calendar,midnight_control';
            $rules['mobile_app_theme_seed'] = 'nullable|in:teal,blue,indigo,emerald,rose,amber';
            $rules['enforce_student_logout_lock'] = 'nullable|boolean';
            $rules['auth_hero_image'] = 'nullable|image|mimes:jpeg,jpg,png,webp|max:8192';
            $rules['remove_auth_hero_image'] = 'nullable|boolean';

            if (SystemSetting::hasMailColumns()) {
                $rules['mail_enabled'] = 'nullable|boolean';
                $rules['mail_host'] = 'nullable|string|max:255';
                $rules['mail_port'] = 'nullable|integer|min:1|max:65535';
                $rules['mail_encryption'] = 'nullable|in:tls,ssl,starttls,';
                $rules['mail_username'] = 'nullable|string|max:255';
                $rules['mail_password'] = 'nullable|string|max:255';
                $rules['mail_from_address'] = 'nullable|email|max:255';
                $rules['mail_from_name'] = 'nullable|string|max:120';
                $rules['mail_action'] = 'nullable|in:save,test';
                $rules['mail_test_to'] = 'nullable|email|max:255';
            }

            if (SystemSetting::hasAllowRepDeletionColumn()) {
                $rules['allow_rep_attendance_deletion'] = 'nullable|boolean';
            }

            if (\App\Support\SchemaFeatures::hasRedisSettings()) {
                $rules['cache_driver'] = 'nullable|in:database,redis,file,array';
                $rules['redis_host'] = 'nullable|string|max:255';
                $rules['redis_port'] = 'nullable|integer|min:1|max:65535';
                $rules['redis_database'] = 'nullable|integer|min:0|max:15';
                $rules['redis_password'] = 'nullable|string|max:255';
                $rules['redis_prefix'] = 'nullable|string|max:64';
                $rules['redis_action'] = 'nullable|in:save,test';
            }
        }
        $validated = $request->validate($rules);
        if (($validated['attendance_mode'] ?? null) === SystemSetting::ATTENDANCE_MODE_CHECKIN_CHECKOUT) {
            $validated['instant_mode_type'] = SystemSetting::INSTANT_MODE_LOCATION;
        }

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
        if ($request->session()->has('admin_id')) {
            if (SystemSetting::hasEnforceStudentLogoutLockColumn()) {
                $payload['enforce_student_logout_lock'] = $request->boolean('enforce_student_logout_lock');
            }
            if (SystemSetting::hasRepDashboardThemeColumn() && $request->has('rep_dashboard_theme')) {
                $v = $validated['rep_dashboard_theme'] ?? 'classic';
                $payload['rep_dashboard_theme'] = $v ?: 'classic';
            }
            if (SystemSetting::hasStudentDashboardThemeColumn() && $request->has('student_dashboard_theme')) {
                $v = $validated['student_dashboard_theme'] ?? 'classic';
                $payload['student_dashboard_theme'] = $v ?: 'classic';
            }
            if (SystemSetting::hasMobileAppThemeSeedColumn() && $request->has('mobile_app_theme_seed')) {
                $v = $validated['mobile_app_theme_seed'] ?? 'teal';
                $payload['mobile_app_theme_seed'] = $v ?: 'teal';
            }
        }
        if (SystemSetting::hasAttendanceModeColumns()) {
            $mode = $validated['attendance_mode'] ?? SystemSetting::ATTENDANCE_MODE_INSTANT;
            $instant = $validated['instant_mode_type'] ?? SystemSetting::INSTANT_MODE_LOCATION_QR;
            if ($mode === SystemSetting::ATTENDANCE_MODE_CHECKIN_CHECKOUT) {
                $instant = SystemSetting::INSTANT_MODE_LOCATION;
            }
            $payload['attendance_mode'] = $mode;
            $payload['instant_mode_type'] = $instant;
        }

        if ($request->session()->has('admin_id') && SystemSetting::hasAllowRepDeletionColumn()) {
            $payload['allow_rep_attendance_deletion'] = $request->boolean('allow_rep_attendance_deletion');
        }

        if ($request->session()->has('admin_id') && \App\Support\SchemaFeatures::hasRedisSettings()) {
            $payload['cache_driver'] = $this->stringOrNull($validated['cache_driver'] ?? null) ?? 'database';
            $payload['redis_host'] = $this->stringOrNull($validated['redis_host'] ?? null);
            $payload['redis_port'] = isset($validated['redis_port']) && $validated['redis_port'] !== ''
                ? (int) $validated['redis_port'] : null;
            $payload['redis_database'] = isset($validated['redis_database']) && $validated['redis_database'] !== ''
                ? (int) $validated['redis_database'] : 0;
            $payload['redis_prefix'] = $this->stringOrNull($validated['redis_prefix'] ?? null);
            $newRedisPassword = $request->input('redis_password');
            if (is_string($newRedisPassword) && trim($newRedisPassword) !== '') {
                $payload['redis_password_encrypted'] = $newRedisPassword;
            } elseif ($request->boolean('clear_redis_password')) {
                $payload['redis_password_encrypted'] = null;
            }
        }

        if ($request->session()->has('admin_id') && SystemSetting::hasMailColumns()) {
            $payload['mail_enabled'] = $request->boolean('mail_enabled');
            $payload['mail_host'] = $this->stringOrNull($validated['mail_host'] ?? null);
            $payload['mail_port'] = isset($validated['mail_port']) && $validated['mail_port'] !== ''
                ? (int) $validated['mail_port'] : null;
            $payload['mail_encryption'] = $this->stringOrNull($validated['mail_encryption'] ?? null);
            $payload['mail_username'] = $this->stringOrNull($validated['mail_username'] ?? null);
            $newPassword = $request->input('mail_password');
            if (is_string($newPassword) && trim($newPassword) !== '') {
                $payload['mail_password_encrypted'] = $newPassword;
            }
            $payload['mail_from_address'] = $this->stringOrNull($validated['mail_from_address'] ?? null);
            if ($payload['mail_from_address'] !== null) {
                $payload['mail_from_address'] = mb_strtolower($payload['mail_from_address']);
            }
            $payload['mail_from_name'] = $this->stringOrNull($validated['mail_from_name'] ?? null);
        }

        $settings->update($payload);
        Cache::forget('api_v1_settings');

        $messages = ['Settings updated'];

        if ($request->session()->has('admin_id')) {
            if ($request->boolean('remove_auth_hero_image')) {
                AuthHeroImage::removeCustom();
                $messages[] = 'Login hero image reset to default.';
            } elseif ($request->hasFile('auth_hero_image')) {
                $result = AuthHeroImage::storeUpload($request->file('auth_hero_image'));
                if (! $result['ok']) {
                    return back()->with('error', $result['message'] ?? 'Could not save login image.');
                }
                $messages[] = 'Login hero image updated (compressed to max 500 KB).';
            }

            // Always re-apply mailer config so the next test or reset email
            // uses the values the admin just saved.
            if (SystemSetting::hasMailColumns()) {
                MailRuntimeConfig::reapply();
            }

            // Optional: send a test email right after save.
            if (SystemSetting::hasMailColumns()
                && ($validated['mail_action'] ?? 'save') === 'test'
                && trim((string) ($validated['mail_test_to'] ?? '')) !== '') {
                $err = app(StudentPasswordResetService::class)->sendTestEmail($validated['mail_test_to']);
                if ($err === null) {
                    $messages[] = 'Test email sent to '.$validated['mail_test_to'].'.';
                } else {
                    return back()->with('error', 'Settings saved, but test email failed: '.$err);
                }
            }

            if (\App\Support\SchemaFeatures::hasRedisSettings()) {
                \App\Support\RedisRuntimeConfig::reapply();

                if (($validated['redis_action'] ?? 'save') === 'test') {
                    $err = \App\Support\RedisRuntimeConfig::ping();
                    if ($err === null) {
                        $messages[] = 'Redis ping OK — caching is healthy.';
                    } else {
                        return back()->with('error', 'Settings saved, but Redis ping failed: '.$err);
                    }
                }
            }
        }

        return back()->with('success', implode(' ', $messages));
    }

    private function stringOrNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
