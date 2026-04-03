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
        'enable_qr',
        'require_password_on_first_login',
        'require_profile_image_on_onboarding',
        'face_match_threshold',
        'attendance_data_version',
        'last_attendance_reset_at',
        // Optional JSON list for backend-driven small UI updates (Flutter renders only when present).
        'dynamic_ui',
    ];

    protected $casts = [
        'enable_face_verification' => 'boolean',
        'enable_ip_binding' => 'boolean',
        'allow_multiple_index_on_device' => 'boolean',
        'enable_qr' => 'boolean',
        'require_password_on_first_login' => 'boolean',
        'require_profile_image_on_onboarding' => 'boolean',
        'face_match_threshold' => 'float',
        'last_attendance_reset_at' => 'datetime',
        'dynamic_ui' => 'array',
    ];

    public static function hasRequireProfileImageColumn(): bool
    {
        return Schema::hasColumn('system_settings', 'require_profile_image_on_onboarding');
    }

    public static function hasDynamicUiColumn(): bool
    {
        return Schema::hasColumn('system_settings', 'dynamic_ui');
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
        if (!$setting) {
            $payload = [
                'enable_face_verification' => true,
                'enable_ip_binding' => true,
                'allow_multiple_index_on_device' => false,
                'enable_qr' => true,
                'require_password_on_first_login' => true,
                'face_match_threshold' => 0.5,
                'attendance_data_version' => 0,
            ];
            if (static::hasRequireProfileImageColumn()) {
                $payload['require_profile_image_on_onboarding'] = true;
            }
            if (static::hasDynamicUiColumn()) {
                $payload['dynamic_ui'] = [];
            }

            $setting = static::create($payload);
        }
        return $setting;
    }
}
