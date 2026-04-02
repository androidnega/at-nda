<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

/**
 * Standard API JSON envelope for class-rep REST routes (does not alter legacy /rep/* bodies).
 */
final class ApiEnvelope
{
    /**
     * @param  array<string, mixed>  $data
     */
    public static function success(array $data, string $message = 'OK', int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    /**
     * @param  array<string, mixed>|null  $data
     */
    public static function error(string $message, int $status = 400, ?array $data = null): JsonResponse
    {
        $body = [
            'success' => false,
            'message' => $message,
        ];
        if ($data !== null) {
            $body['data'] = $data;
        }

        return response()->json($body, $status);
    }
}
