<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    |
    | This value is the name of your application, which will be used when the
    | framework needs to place the application's name in a notification or
    | other UI elements where an application name needs to be displayed.
    |
    */

    'name' => env('APP_NAME', 'a-tenda'),

    /*
    | Shared hero photo for the split sign-in layout (index, password, set-password).
    */
    'auth_hero_image' => env('AUTH_HERO_IMAGE', 'https://www.shutterstock.com/image-photo/children-using-laptops-school-africa-600nw-2547522299.jpg'),

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    |
    | This value determines the "environment" your application is currently
    | running in. This may determine how you prefer to configure various
    | services the application utilizes. Set this in your ".env" file.
    |
    */

    'env' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Application Debug Mode
    |--------------------------------------------------------------------------
    |
    | When your application is in debug mode, detailed error messages with
    | stack traces will be shown on every error that occurs within your
    | application. If disabled, a simple generic error page is shown.
    |
    */

    'debug' => (bool) env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Application URL
    |--------------------------------------------------------------------------
    |
    | This URL is used by the console to properly generate URLs when using
    | the Artisan command line tool. You should set this to the root of
    | the application so that it's available within Artisan commands.
    |
    */

    'url' => env('APP_URL', 'http://localhost'),

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default timezone for your application, which
    | will be used by the PHP date and date-time functions. The timezone
    | is set to "UTC" by default as it is suitable for most use cases.
    |
    */

    'timezone' => env('APP_TIMEZONE', 'Africa/Accra'),

    /*
    |--------------------------------------------------------------------------
    | Application Locale Configuration
    |--------------------------------------------------------------------------
    |
    | The application locale determines the default locale that will be used
    | by Laravel's translation / localization methods. This option can be
    | set to any locale for which you plan to have translation strings.
    |
    */

    'locale' => env('APP_LOCALE', 'en'),

    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),

    'faker_locale' => env('APP_FAKER_LOCALE', 'en_US'),

    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    |
    | This key is utilized by Laravel's encryption services and should be set
    | to a random, 32 character string to ensure that all encrypted values
    | are secure. You should do this prior to deploying the application.
    |
    */

    'cipher' => 'AES-256-CBC',

    'key' => env('APP_KEY'),

    'previous_keys' => [
        ...array_filter(
            explode(',', (string) env('APP_PREVIOUS_KEYS', ''))
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Maintenance Mode Driver
    |--------------------------------------------------------------------------
    |
    | These configuration options determine the driver used to determine and
    | manage Laravel's "maintenance mode" status. The "cache" driver will
    | allow maintenance mode to be controlled across multiple machines.
    |
    | Supported drivers: "file", "cache"
    |
    */

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Default attendance geofence radius (meters)
    |--------------------------------------------------------------------------
    |
    | Used when session and course do not set attendance_range_m. Real-world
    | defaults target ~150–200 m effective radius (see buffer + min below).
    |
    */

    'default_attendance_range_m' => (int) env('DEFAULT_ATTENDANCE_RANGE_M', 200),

    /*
    | Extra meters added to the server-side geofence check (GPS jitter / indoor).
    |
    */

    'geofence_gps_buffer_m' => (int) env('GEOFENCE_GPS_BUFFER_M', 50),

    /*
    | Minimum radius used for server-side “in range” checks (meters). Prevents
    | false “Out of range” when course/session has a small stored radius.
    | Effective radius = max(nominal, this) + buffer (typically ~200–250 m).
    |
    */

    'min_geofence_check_m' => (int) env('MIN_GEOFENCE_CHECK_M', 150),

    /*
    | Max extra meters allowed when the client sends GPS horizontal accuracy (meters).
    | Matches typical Flutter checks: distance <= radius + accuracy (capped against abuse).
    |
    */

    'geofence_accuracy_slack_cap_m' => (int) env('GEOFENCE_ACCURACY_SLACK_CAP_M', 120),

];
