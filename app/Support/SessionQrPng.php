<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;

/**
 * Fetches a high-resolution PNG for printing (same payload as on-screen QR).
 */
class SessionQrPng
{
    public static function fetchBytes(string $payloadJson): string
    {
        $url = 'https://api.qrserver.com/v1/create-qr-code/?size=1200x1200&format=png&margin=12&data='.urlencode($payloadJson);
        $response = Http::timeout(25)->get($url);
        if (! $response->successful() || strlen($response->body()) < 200) {
            abort(502, 'Could not generate QR image. Try again in a moment.');
        }

        return $response->body();
    }
}
