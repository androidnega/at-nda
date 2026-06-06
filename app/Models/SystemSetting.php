<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class SystemSetting extends Model
{
    /**
     * Per-request memo of the resolved settings row. Hot paths
     * (controllers, middleware, services) call ::get() multiple
     * times per request — caching it avoids repeated SELECT 1
     * queries on shared hosting where DB connections are scarce.
     */
    private static ?self $cachedInstance = null;

    /**
     * Per-request memo for has*Column lookups so we don't hit
     * INFORMATION_SCHEMA every time a controller asks "is this
     * optional column on the table?".
     *
     * @var array<string, bool>
     */
    private static array $columnCache = [];

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

    /**
     * Cheap, memoised "does optional column X exist?" — avoids one
     * INFORMATION_SCHEMA query per check on busy pages.
     */
    private static function columnExists(string $column): bool
    {
        if (array_key_exists($column, self::$columnCache)) {
            return self::$columnCache[$column];
        }

        try {
            $exists = Schema::hasColumn('system_settings', $column);
        } catch (\Throwable $e) {
            $exists = false;
        }

        return self::$columnCache[$column] = $exists;
    }

    public static function hasRequireProfileImageColumn(): bool
    {
        return self::columnExists('require_profile_image_on_onboarding');
    }

    public static function hasDynamicUiColumn(): bool
    {
        return self::columnExists('dynamic_ui');
    }

    public static function hasRepDashboardThemeColumn(): bool
    {
        return self::columnExists('rep_dashboard_theme');
    }

    public static function hasStudentDashboardThemeColumn(): bool
    {
        return self::columnExists('student_dashboard_theme');
    }

    public static function hasMobileAppThemeSeedColumn(): bool
    {
        return self::columnExists('mobile_app_theme_seed');
    }

    public static function hasAttendanceModeColumns(): bool
    {
        return self::columnExists('attendance_mode') && self::columnExists('instant_mode_type');
    }

    public static function hasEnforceStudentLogoutLockColumn(): bool
    {
        return self::columnExists('enforce_student_logout_lock');
    }

    public static function hasAuthHeroImagePathColumn(): bool
    {
        return self::columnExists('auth_hero_image_path');
    }

    public function requiresProfileImageOnOnboarding(): bool
    {
        if (! static::hasRequireProfileImageColumn()) {
            return true;
        }

        return (bool) ($this->require_profile_image_on_onboarding ?? true);
    }

    /**
     * Resolve the (singleton) settings row.
     *
     * Hot path — read from many controllers, services and middleware.
     * Caching layers, in order:
     *   1. Per-request static memo (zero overhead on repeat calls)
     *   2. Application cache for 60s (saves DB query across requests)
     *   3. Fresh DB read with default-row seed
     *
     * The `saved` / `deleted` boot hooks invalidate both caches so
     * admin updates take effect immediately for everyone.
     */
    public static function get(): self
    {
        if (self::$cachedInstance !== null) {
            return self::$cachedInstance;
        }

        try {
            $cached = Cache::get('atenda:system_settings:row');
            if ($cached instanceof self) {
                return self::$cachedInstance = $cached;
            }
        } catch (\Throwable $e) {
            // Cache backend unreachable — fall through to DB.
        }

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

        try {
            Cache::put('atenda:system_settings:row', $setting, now()->addSeconds(60));
        } catch (\Throwable $e) {
            // Cache write failed — not critical, the per-request memo
            // below still saves repeated DB hits inside this request.
        }

        return self::$cachedInstance = $setting;
    }

    /**
     * Drop both layers of cached settings. Called automatically from
     * the model's saved/deleted hooks; can also be called explicitly
     * after bulk updates that bypass Eloquent.
     */
    public static function flushCache(): void
    {
        self::$cachedInstance = null;
        self::$columnCache = [];
        try {
            Cache::forget('atenda:system_settings:row');
            Cache::forget('api_v1_settings');
        } catch (\Throwable $e) {
            // Cache backend unavailable — safe to swallow.
        }
    }

    protected static function booted(): void
    {
        static::saved(fn () => self::flushCache());
        static::deleted(fn () => self::flushCache());
    }
}
