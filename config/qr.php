<?php

return [

    /*
    |--------------------------------------------------------------------------
    | QR payload signing (HMAC-SHA256)
    |--------------------------------------------------------------------------
    |
    | Set QR_SECRET in .env for tamper-proof QR strings: { data, sig } base64.
    | If empty, the app falls back to a random plain qr_token (legacy / local dev).
    |
    */
    'secret' => env('QR_SECRET'),

    /*
    | Inner expiry (Unix timestamp in signed payload), capped by session end_time.
    | QR text only changes when the session row is updated or refreshed (not on a timer).
    |
    */
    'ttl_minutes' => (int) env('QR_TOKEN_TTL_MINUTES', 2),

];
