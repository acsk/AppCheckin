<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

final class ApiError
{
    public static function json(
        string $message,
        string $code,
        int $status = 400,
        string $type = 'error',
        array $extra = [],
    ): JsonResponse {
        return response()->json(array_merge([
            'type' => $type,
            'code' => $code,
            'message' => $message,
        ], $extra), $status, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param  list<string>  $errors
     */
    public static function validation(array $errors, string $message = 'Erro de validação'): JsonResponse
    {
        return self::json($message, 'VALIDATION_ERROR', 422, extra: ['errors' => $errors]);
    }
}
