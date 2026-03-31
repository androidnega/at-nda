<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $fillable = [
        'enable_face_verification',
        'enable_ip_binding',
        'allow_multiple_index_on_device',
        'enable_qr',
        'require_password_on_first_login',
        'face_match_threshold',
        'attendance_data_version',
        'last_attendance_reset_at',
    ];

    protected $casts = [
        'enable_face_verification' => 'boolean',
        'enable_ip_binding' => 'boolean',
        'allow_multiple_index_on_device' => 'boolean',
        'enable_qr' => 'boolean',
        'require_password_on_first_login' => 'boolean',
        'face_match_threshold' => 'float',
        'last_attendance_reset_at' => 'datetime',
    ];

    public static function get(): self
    {
        $setting = static::first();
        if (!$setting) {
            $setting = static::create([
                'enable_face_verification' => true,
                'enable_ip_binding' => true,
                'allow_multiple_index_on_device' => false,
                'enable_qr' => true,
                'require_password_on_first_login' => true,
                'face_match_threshold' => 0.5,
                'attendance_data_version' => 0,
            ]);
        }
        return $setting;
    }
}
