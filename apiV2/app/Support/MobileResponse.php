<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

final class MobileResponse
{
    public static function success(array $payload = [], int $status = 200): JsonResponse
    {
        return response()->json(
            array_merge(['success' => true], $payload),
            $status,
            [],
            JSON_UNESCAPED_UNICODE,
        );
    }

    public static function error(
        string $error,
        int $status = 400,
        ?string $code = null,
        array $extra = [],
    ): JsonResponse {
        $payload = array_merge([
            'success' => false,
            'error' => $error,
        ], $extra);

        if ($code !== null) {
            $payload['code'] = $code;
        }

        return response()->json($payload, $status, [], JSON_UNESCAPED_UNICODE);
    }

    public static function serverError(string $error, ?string $message = null): JsonResponse
    {
        $payload = [
            'success' => false,
            'error' => $error,
        ];

        if ($message !== null) {
            $payload['message'] = $message;
        }

        return response()->json($payload, 500, [], JSON_UNESCAPED_UNICODE);
    }
}
