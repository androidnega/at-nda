<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class SystemSetting extends Model
{
    protected $fillable = [
        'enable_face_verification',
        'enable_ip_binding',
        'allow_multiple_index_on_device',
        'enforce_student_logout_lock',
        'enable_qr',
        'require_password_on_first_login',
        'require_profile_image_on_onboarding',
        'face_match_threshold',
        'attendance_data_version',
        'last_attendance_reset_at',
        // Optional JSON list for backend-driven small UI updates (Flutter renders only when present).
        'dynamic_ui',
        'rep_dashboard_theme',
        'student_dashboard_theme',
        'mobile_app_theme_seed',
        'attendance_mode',
        'instant_mode_type',
        'auth_hero_image_path',
        'mail_enabled',
        'mail_host',
        'mail_port',
        'mail_encryption',
        'mail_username',
        'mail_password_encrypted',
        'mail_from_address',
        'mail_from_name',
        'allow_rep_attendance_deletion',
        'cache_driver',
        'redis_host',
        'redis_port',
        'redis_database',
        'redis_password_encrypted',
        'redis_prefix',
    ];

    protected $casts = [
        'enable_face_verification' => 'boolean',
        'enable_ip_binding' => 'boolean',
        'allow_multiple_index_on_device' => 'boolean',
        'enforce_student_logout_lock' => 'boolean',
        'enable_qr' => 'boolean',
        'require_password_on_first_login' => 'boolean',
        'require_profile_image_on_onboarding' => 'boolean',
        'face_match_threshold' => 'float',
        'last_attendance_reset_at' => 'datetime',
        'dynamic_ui' => 'array',
        'mail_enabled' => 'boolean',
        // Stored encrypted via APP_KEY so SMTP creds never sit in plaintext.
        'mail_password_encrypted' => 'encrypted',
        'allow_rep_attendance_deletion' => 'boolean',
        'redis_password_encrypted' => 'encrypted',
    ];

    public static function hasMailColumns(): bool
    {
        return \App\Support\SchemaFeatures::hasMailSettings();
    }

    public static function hasAllowRepDeletionColumn(): bool
    {
        return \App\Support\SchemaFeatures::hasAllowRepDeletionSetting();
    }

    public static function repsCanDeleteAttendance(): bool
    {
        if (! self::hasAllowRepDeletionColumn()) {
            return false;
        }
        try {
            return (bool) (self::get()->allow_rep_attendance_deletion ?? false);
        } catch (\Throwable $e) {
            return false;
        }
    }

    public const ATTENDANCE_MODE_INSTANT = 'instant';
    public const ATTENDANCE_MODE_CHECKIN_CHECKOUT = 'checkin_checkout';

    public const INSTANT_MODE_LOCATION = 'location';
    public const INSTANT_MODE_LOCATION_QR = 'location_qr';
    public const INSTANT_MODE_WIFI = 'wifi';

    public static function hasRequireProfileImageColumn(): bool
    {
        return Schema::hasColumn('system_settings', 'require_profile_image_on_onboarding');
    }

    public static function hasDynamicUiColumn(): bool
    {
        return Schema::hasColumn('system_settings', 'dynamic_ui');
    }

    public static function hasRepDashboardThemeColumn(): bool
    {
        return Schema::hasColumn('system_settings', 'rep_dashboard_theme');
    }

    public static function hasStudentDashboardThemeColumn(): bool
    {
        return Schema::hasColumn('system_settings', 'student_dashboard_theme');
    }

    public static function hasMobileAppThemeSeedColumn(): bool
    {
        return Schema::hasColumn('system_settings', 'mobile_app_theme_seed');
    }

    public static function hasAttendanceModeColumns(): bool
    {
        return Schema::hasColumn('system_settings', 'attendance_mode')
            && Schema::hasColumn('system_settings', 'instant_mode_type');
    }

    public static function hasEnforceStudentLogoutLockColumn(): bool
    {
        return Schema::hasColumn('system_settings', 'enforce_student_logout_lock');
    }

    public static function hasAuthHeroImagePathColumn(): bool
    {
        return Schema::hasColumn('system_settings', 'auth_hero_image_path');
    }

    public function requiresProfileImageOnOnboarding(): bool
    {
        if (! static::hasRequireProfileImageColumn()) {
            return true;
        }

        return (bool) ($this->require_profile_image_on_onboarding ?? true);
    }

    public static function get(): self
    {
        $setting = static::first();
        if (! $setting) {
            $payload = [
                'enable_face_verification' => true,
                'enable_ip_binding' => true,
                'allow_multiple_index_on_device' => false,
                'enable_qr' => true,
                'require_password_on_first_login' => true,
                'face_match_threshold' => 0.5,
                'attendance_data_version' => 0,
            ];
            if (static::hasEnforceStudentLogoutLockColumn()) {
                $payload['enforce_student_logout_lock'] = true;
            }
            if (static::hasRequireProfileImageColumn()) {
                $payload['require_profile_image_on_onboarding'] = true;
            }
            if (static::hasDynamicUiColumn()) {
                $payload['dynamic_ui'] = [];
            }
            if (static::hasRepDashboardThemeColumn()) {
                $payload['rep_dashboard_theme'] = 'classic';
            }
            if (static::hasStudentDashboardThemeColumn()) {
                $payload['student_dashboard_theme'] = 'classic';
            }
            if (static::hasMobileAppThemeSeedColumn()) {
                $payload['mobile_app_theme_seed'] = 'teal';
            }
            if (static::hasAttendanceModeColumns()) {
                $payload['attendance_mode'] = self::ATTENDANCE_MODE_INSTANT;
                $payload['instant_mode_type'] = self::INSTANT_MODE_LOCATION_QR;
            }

            $setting = static::create($payload);
        }

        return $setting;
    }
}
