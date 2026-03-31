<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $fillable = [
        'enable_ip_binding',
        'allow_multiple_index_on_device',
        'enable_qr',
        'require_password_on_first_login',
        'attendance_data_version',
        'last_attendance_reset_at',
    ];

    protected $casts = [
        'enable_ip_binding' => 'boolean',
        'allow_multiple_index_on_device' => 'boolean',
        'enable_qr' => 'boolean',
        'require_password_on_first_login' => 'boolean',
        'last_attendance_reset_at' => 'datetime',
    ];

    public static function get(): self
    {
        $setting = static::first();
        if (!$setting) {
            $setting = static::create([
                'enable_ip_binding' => true,
                'allow_multiple_index_on_device' => false,
                'enable_qr' => true,
                'require_password_on_first_login' => true,
                'attendance_data_version' => 0,
            ]);
        }
        return $setting;
    }
}
