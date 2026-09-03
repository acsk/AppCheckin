<?php

namespace App\Services\SuperAdmin;

class SuperAdminMiscService
{
    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function getEnvironmentVariables(): array
    {
        $safeVars = [
            'APP_ENV' => env('APP_ENV', 'unknown'),
            'APP_URL' => env('APP_URL', 'unknown'),
            'APP_DEBUG' => env('APP_DEBUG', false),
            'APP_TIMEZONE' => config('app.timezone', 'unknown'),
            'DB_HOST' => env('DB_HOST', 'unknown'),
            'DB_PORT' => env('DB_PORT', 3306),
            'DB_NAME' => env('DB_DATABASE', 'unknown'),
            'DB_USER' => env('DB_USERNAME', 'unknown'),
            'JWT_EXPIRATION' => config('appcheckin.jwt_expiration', 86400),
            'LOG_LEVEL' => env('LOG_LEVEL', 'error'),
            'LOG_PATH' => storage_path('logs'),
            'RATE_LIMIT_ENABLED' => env('RATE_LIMIT_ENABLED', true),
            'RATE_LIMIT_MAX_REQUESTS' => env('RATE_LIMIT_MAX_REQUESTS', 100),
            'RATE_LIMIT_WINDOW_SECONDS' => env('RATE_LIMIT_WINDOW_SECONDS', 60),
        ];

        return [
            'status' => 200,
            'body' => [
                'warning' => 'Dados de ambiente do servidor - Proteja este acesso',
                'environment' => $safeVars,
                'php_version' => PHP_VERSION,
                'timestamp' => date('Y-m-d H:i:s'),
            ],
        ];
    }
}
