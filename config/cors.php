<?php

// NB: keep the default EMPTY. Shipping a default that contains
// "http://localhost:8082" caused every fresh production deploy whose .env
// didn't explicitly set CORS_ALLOWED_ORIGINS to 500 on every request,
// because the production guard in AppServiceProvider::assertProductionEnvironment()
// reads config('cors.allowed_origins') and refuses to boot when a dev origin
// is present. Local-dev devs get the dev origins from .env.example (which
// still pre-fills the localhost / 127.0.0.1 / 0.0.0.0 trio), so removing
// the hardcoded fallback here is safe for development too.
$corsAllowedOrigins = array_values(array_filter(array_map(
    static fn ($origin) => trim($origin),
    explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))
)));

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'media/*'],

    'allowed_methods' => ['*'],

    // Keep this explicit for production safety and predictable preflight behavior.
    'allowed_origins' => $corsAllowedOrigins,

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // Needed when browser sends credentials/authorization headers.
    'supports_credentials' => true,

];
