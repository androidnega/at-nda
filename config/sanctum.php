<?php

use Laravel\Sanctum\Sanctum;

return [

    /*
    |--------------------------------------------------------------------------
    | Stateful Domains
    |--------------------------------------------------------------------------
    |
    | Requests from the following domains / hosts will receive stateful API
    | authentication cookies. Typically, these should include your local
    | and production domains which access your API via a frontend SPA.
    |
    */

    'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', sprintf(
        '%s%s',
        'localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1',
        Sanctum::currentApplicationUrlWithPort(),
        // Sanctum::currentRequestHost(),
    ))),

    /*
    |--------------------------------------------------------------------------
    | Sanctum Guards
    |--------------------------------------------------------------------------
    |
    | This array contains the authentication guards that will be checked when
    | Sanctum is trying to authenticate a request. If none of these guards
    | are able to authenticate the request, Sanctum will use the bearer
    | token that's present on an incoming request for authentication.
    |
    */

    'guard' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Expiration Minutes
    |--------------------------------------------------------------------------
    |
    | This value controls the number of minutes until an issued token will be
    | considered expired. This will override any values set in the token's
    | "expires_at" attribute, but first-party sessions are not affected.
    |
    | -----  P1.T18 verification block  (audit M-03 / H-17)  -----
    | Driven by the SANCTUM_TOKEN_EXPIRATION env key. P1.T17 sets the
    | .env.example default to `10080` (= 7 days). The mapping is:
    |     env unset / empty       -> null   (tokens NEVER expire — unsafe)
    |     env=10080               -> 10080  (7 days — Phase 1 target)
    |     env=N (any positive)    -> N
    | Verify in production with:
    |     php artisan tinker --execute='echo config("sanctum.expiration");'
    | Expect: 10080 after the Phase 1 deploy.
    | AppServiceProvider::assertProductionEnvironment() emits a
    | Log::warning when this resolves to <=0 in production (non-fatal,
    | by design — flipping the fleet to "expired" on a single deploy
    | would log every Flutter client out at once; the warning gets the
    | operator's attention in the next log review).
    |
    */

    'expiration' => env('SANCTUM_TOKEN_EXPIRATION') !== null && env('SANCTUM_TOKEN_EXPIRATION') !== ''
        ? (int) env('SANCTUM_TOKEN_EXPIRATION')
        : null,

    /*
    |--------------------------------------------------------------------------
    | Token Prefix
    |--------------------------------------------------------------------------
    |
    | Sanctum can prefix new tokens in order to take advantage of numerous
    | security scanning initiatives maintained by open source platforms
    | that notify developers if they commit tokens into repositories.
    |
    | See: https://docs.github.com/en/code-security/secret-scanning/about-secret-scanning
    |
    */

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),

    /*
    |--------------------------------------------------------------------------
    | Sanctum Middleware
    |--------------------------------------------------------------------------
    |
    | When authenticating your first-party SPA with Sanctum you may need to
    | customize some of the middleware Sanctum uses while processing the
    | request. You may change the middleware listed below as required.
    |
    */

    'middleware' => [
        'authenticate_session' => Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
        'encrypt_cookies' => Illuminate\Cookie\Middleware\EncryptCookies::class,
        'validate_csrf_token' => Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ],

];
