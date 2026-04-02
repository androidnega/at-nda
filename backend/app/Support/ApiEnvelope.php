<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

/**
 * Strict JSON contract for /api/v1/* (legacy /api/* remains unchanged).
 */
class ApiEnvelope
{
    /**
     * @param  array<string, mixed>|null  $meta
     */
    public static function success(mixed $data = null, string $message = 'Request successful', ?array $meta = null): array
    {
        return [
            'status' => true,
            'message' => $message,
            'data' => $data,
            'errors' => null,
            'meta' => $meta,
        ];
    }

    /**
     * @param  array<string, mixed>|\Illuminate\Support\MessageBag|string|null  $errors
     */
    public static function errorResponse(
        string $message,
        int $httpStatus = 400,
        mixed $errors = null,
        mixed $data = null,
        ?array $meta = null
    ): JsonResponse {
        return response()->json([
            'status' => false,
            'message' => $message,
            'data' => $data,
            'errors' => $errors,
            'meta' => $meta,
        ], $httpStatus);
    }
}
