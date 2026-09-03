<?php

namespace App\Services\SuperAdmin;

use App\Repositories\PlanoRepository;
use App\Repositories\TenantRepository;

class SuperAdminMiscService
{
    public function __construct(
        private readonly TenantRepository $tenants,
        private readonly PlanoRepository $planos,
    ) {}

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function listarPapeis(): array
    {
        return [
            'status' => 200,
            'body' => [
                'papeis' => [
                    ['id' => 1, 'nome' => 'Aluno', 'descricao' => 'Pode acessar o app mobile e fazer check-in'],
                    ['id' => 2, 'nome' => 'Professor', 'descricao' => 'Pode marcar presença e gerenciar turmas'],
                    ['id' => 3, 'nome' => 'Admin', 'descricao' => 'Pode acessar o painel administrativo'],
                ],
            ],
        ];
    }

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

    /**
     * @param  array<string, mixed>  $query
     * @return array{status: int, body: array<string, mixed>}
     */
    public function listarPlanosAlunos(array $query): array
    {
        $tenantId = isset($query['tenant_id']) ? (int) $query['tenant_id'] : null;
        $apenasAtivos = isset($query['ativos']) && $query['ativos'] === 'true';
        $tenants = $this->tenants->getAll(['ativo' => true]);

        if (! $tenantId) {
            return [
                'status' => 200,
                'body' => [
                    'planos' => [],
                    'total' => 0,
                    'tenants' => $tenants,
                    'message' => 'Selecione uma academia para ver os planos',
                ],
            ];
        }

        $planos = $this->planos->listarPorTenantSuperAdmin($tenantId, $apenasAtivos);

        return [
            'status' => 200,
            'body' => [
                'planos' => $planos,
                'total' => count($planos),
                'tenants' => $tenants,
            ],
        ];
    }
}
