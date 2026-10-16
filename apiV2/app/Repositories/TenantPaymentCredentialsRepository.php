<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

/**
 * Credenciais de pagamento por tenant+provider (tenant_payment_credentials).
 */
class TenantPaymentCredentialsRepository
{
    /**
     * @return list<array<string, mixed>>
     */
    public function listarPorTenant(int $tenantId): array
    {
        $rows = DB::select(
            'SELECT id, tenant_id, provider, environment,
                    public_key_test, public_key_prod,
                    is_active, created_at, updated_at,
                    CASE WHEN access_token_test IS NOT NULL AND access_token_test != \'\' THEN TRUE ELSE FALSE END as has_token_test,
                    CASE WHEN access_token_prod IS NOT NULL AND access_token_prod != \'\' THEN TRUE ELSE FALSE END as has_token_prod
             FROM tenant_payment_credentials
             WHERE tenant_id = ?
             ORDER BY provider',
            [$tenantId]
        );

        return array_map(fn ($row) => (array) $row, $rows);
    }

    public function obterPorTenantProvider(int $tenantId, string $provider): ?array
    {
        $row = DB::selectOne(
            'SELECT id, tenant_id, provider, environment,
                    public_key_test, public_key_prod,
                    is_active, created_at, updated_at,
                    CASE WHEN access_token_test IS NOT NULL AND access_token_test != \'\' THEN TRUE ELSE FALSE END as has_token_test,
                    CASE WHEN access_token_prod IS NOT NULL AND access_token_prod != \'\' THEN TRUE ELSE FALSE END as has_token_prod
             FROM tenant_payment_credentials
             WHERE tenant_id = ? AND provider = ?
             LIMIT 1',
            [$tenantId, $provider]
        );

        return $row ? (array) $row : null;
    }

    public function existePorTenantProvider(int $tenantId, string $provider): bool
    {
        return DB::selectOne(
            'SELECT id FROM tenant_payment_credentials WHERE tenant_id = ? AND provider = ?',
            [$tenantId, $provider]
        ) !== null;
    }

    /**
     * @param  array<string, mixed>  $dados
     */
    public function inserir(int $tenantId, array $dados): void
    {
        DB::insert(
            'INSERT INTO tenant_payment_credentials
                (tenant_id, provider, environment, access_token_test, access_token_prod,
                 public_key_test, public_key_prod, webhook_secret, is_active)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $tenantId,
                $dados['provider'],
                $dados['environment'],
                $dados['access_token_test'],
                $dados['access_token_prod'],
                $dados['public_key_test'],
                $dados['public_key_prod'],
                $dados['webhook_secret'],
                $dados['is_active'],
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $dados
     */
    public function atualizar(int $tenantId, string $provider, array $dados): void
    {
        $sql = 'UPDATE tenant_payment_credentials SET
                    environment = ?,
                    public_key_test = ?,
                    public_key_prod = ?,
                    is_active = ?,
                    updated_at = NOW()';
        $params = [
            $dados['environment'],
            $dados['public_key_test'],
            $dados['public_key_prod'],
            $dados['is_active'],
        ];

        if (array_key_exists('access_token_test', $dados)) {
            $sql .= ', access_token_test = ?';
            $params[] = $dados['access_token_test'];
        }
        if (array_key_exists('access_token_prod', $dados)) {
            $sql .= ', access_token_prod = ?';
            $params[] = $dados['access_token_prod'];
        }
        if (array_key_exists('webhook_secret', $dados)) {
            $sql .= ', webhook_secret = ?';
            $params[] = $dados['webhook_secret'];
        }

        $sql .= ' WHERE tenant_id = ? AND provider = ?';
        $params[] = $tenantId;
        $params[] = $provider;

        DB::update($sql, $params);
    }

    public function atualizarAmbiente(int $tenantId, string $provider, string $environment): void
    {
        DB::update(
            'UPDATE tenant_payment_credentials
             SET environment = ?, updated_at = NOW()
             WHERE tenant_id = ? AND provider = ?',
            [$environment, $tenantId, $provider]
        );
    }
}
