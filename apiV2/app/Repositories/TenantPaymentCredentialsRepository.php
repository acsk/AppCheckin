<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
                    is_active, enable_pix, enable_boleto, enable_credit_card'.$this->sqlSelectAntecipacao().',
                    created_at, updated_at,
                    CASE WHEN CHAR_LENGTH(TRIM(IFNULL(access_token_test, \'\'))) > 0 THEN 1 ELSE 0 END as has_token_test,
                    CASE WHEN CHAR_LENGTH(TRIM(IFNULL(access_token_prod, \'\'))) > 0 THEN 1 ELSE 0 END as has_token_prod
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
                    is_active, enable_pix, enable_boleto, enable_credit_card'.$this->sqlSelectAntecipacao().',
                    created_at, updated_at,
                    CASE WHEN CHAR_LENGTH(TRIM(IFNULL(access_token_test, \'\'))) > 0 THEN 1 ELSE 0 END as has_token_test,
                    CASE WHEN CHAR_LENGTH(TRIM(IFNULL(access_token_prod, \'\'))) > 0 THEN 1 ELSE 0 END as has_token_prod
             FROM tenant_payment_credentials
             WHERE tenant_id = ? AND provider = ?
             LIMIT 1',
            [$tenantId, $provider]
        );

        return $row ? (array) $row : null;
    }

    public function temTokenAmbiente(int $tenantId, string $provider, string $environment): bool
    {
        $coluna = $environment === 'production' ? 'access_token_prod' : 'access_token_test';
        $row = DB::selectOne(
            "SELECT CHAR_LENGTH(TRIM(IFNULL(`{$coluna}`, ''))) AS tamanho
             FROM tenant_payment_credentials
             WHERE tenant_id = ? AND provider = ?
             LIMIT 1",
            [$tenantId, $provider]
        );

        if (! $row) {
            return false;
        }

        $tamanho = is_object($row) ? $row->tamanho : ($row['tamanho'] ?? 0);

        return (int) $tamanho > 0;
    }

    private function temColunasAntecipacao(): bool
    {
        return Schema::hasColumn('tenant_payment_credentials', 'enable_sobretaxa_antecipacao');
    }

    private function temColunaAntecipacaoAutomatica(): bool
    {
        return Schema::hasColumn('tenant_payment_credentials', 'enable_antecipacao_automatica');
    }

    private function temColunaPixRecorrencia(): bool
    {
        return Schema::hasColumn('tenant_payment_credentials', 'enable_pix_recorrencia');
    }

    /** PIX aceito na assinatura mensal (fidelização) da academia: credencial Asaas ativa com o flag ligado. */
    public function pixRecorrenciaHabilitado(int $tenantId): bool
    {
        if (! Schema::hasTable('tenant_payment_credentials') || ! $this->temColunaPixRecorrencia()) {
            return false;
        }

        return DB::table('tenant_payment_credentials')->where('tenant_id', $tenantId)->where('provider', 'asaas')
            ->where('is_active', 1)->where('enable_pix', 1)->where('enable_pix_recorrencia', 1)->exists();
    }

    private function temColunaMaxParcelas(): bool
    {
        return Schema::hasColumn('tenant_payment_credentials', 'max_parcelas_cartao');
    }

    private function temColunaSomenteVinculados(): bool
    {
        return Schema::hasColumn('tenant_payment_credentials', 'conciliar_somente_vinculados');
    }

    private function sqlSelectAntecipacao(): string
    {
        $sql = $this->temColunasAntecipacao()
            ? ', enable_sobretaxa_antecipacao, sobretaxa_antecipacao_avista_percentual, sobretaxa_antecipacao_parcelada_percentual'
            : '';

        $sql .= $this->temColunaAntecipacaoAutomatica() ? ', enable_antecipacao_automatica' : '';

        $sql .= $this->temColunaPixRecorrencia() ? ', enable_pix_recorrencia' : '';

        $sql .= $this->temColunaMaxParcelas() ? ', max_parcelas_cartao' : '';

        return $sql.($this->temColunaSomenteVinculados() ? ', conciliar_somente_vinculados' : '');
    }

    public function definirAntecipacaoAutomatica(int $tenantId, string $provider, bool $ligada): void
    {
        if (! $this->temColunaAntecipacaoAutomatica()) {
            return;
        }
        DB::update(
            'UPDATE tenant_payment_credentials SET enable_antecipacao_automatica = ?, updated_at = NOW() WHERE tenant_id = ? AND provider = ?',
            [$ligada ? 1 : 0, $tenantId, $provider]
        );
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
        $cols = 'tenant_id, provider, environment, access_token_test, access_token_prod,
                 public_key_test, public_key_prod, webhook_secret, is_active,
                 enable_pix, enable_boleto, enable_credit_card';
        $params = [
            $tenantId,
            $dados['provider'],
            $dados['environment'],
            $dados['access_token_test'],
            $dados['access_token_prod'],
            $dados['public_key_test'],
            $dados['public_key_prod'],
            $dados['webhook_secret'],
            $dados['is_active'],
            $dados['enable_pix'] ?? 1,
            $dados['enable_boleto'] ?? 1,
            $dados['enable_credit_card'] ?? 1,
        ];
        if ($this->temColunasAntecipacao()) {
            $cols .= ', enable_sobretaxa_antecipacao, sobretaxa_antecipacao_avista_percentual, sobretaxa_antecipacao_parcelada_percentual';
            $params[] = $dados['enable_sobretaxa_antecipacao'] ?? 0;
            $params[] = $dados['sobretaxa_antecipacao_avista_percentual'] ?? 1.15;
            $params[] = $dados['sobretaxa_antecipacao_parcelada_percentual'] ?? 1.60;
        }
        if ($this->temColunaAntecipacaoAutomatica()) {
            $cols .= ', enable_antecipacao_automatica';
            $params[] = $dados['enable_antecipacao_automatica'] ?? 0;
        }
        if ($this->temColunaSomenteVinculados()) {
            $cols .= ', conciliar_somente_vinculados';
            $params[] = $dados['conciliar_somente_vinculados'] ?? 0;
        }
        if ($this->temColunaPixRecorrencia()) {
            $cols .= ', enable_pix_recorrencia';
            $params[] = $dados['enable_pix_recorrencia'] ?? 0;
        }
        if ($this->temColunaMaxParcelas()) {
            $cols .= ', max_parcelas_cartao';
            $params[] = $dados['max_parcelas_cartao'] ?? 6;
        }
        $placeholders = implode(', ', array_fill(0, count($params), '?'));
        DB::insert("INSERT INTO tenant_payment_credentials ({$cols}) VALUES ({$placeholders})", $params);
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
                    enable_pix = ?,
                    enable_boleto = ?,
                    enable_credit_card = ?';
        $params = [
            $dados['environment'],
            $dados['public_key_test'],
            $dados['public_key_prod'],
            $dados['is_active'],
            $dados['enable_pix'] ?? 1,
            $dados['enable_boleto'] ?? 1,
            $dados['enable_credit_card'] ?? 1,
        ];
        if ($this->temColunasAntecipacao()) {
            $sql .= ', enable_sobretaxa_antecipacao = ?,
                    sobretaxa_antecipacao_avista_percentual = ?,
                    sobretaxa_antecipacao_parcelada_percentual = ?';
            $params[] = $dados['enable_sobretaxa_antecipacao'] ?? 0;
            $params[] = $dados['sobretaxa_antecipacao_avista_percentual'] ?? 1.15;
            $params[] = $dados['sobretaxa_antecipacao_parcelada_percentual'] ?? 1.60;
        }
        if ($this->temColunaAntecipacaoAutomatica() && array_key_exists('enable_antecipacao_automatica', $dados)) {
            $sql .= ', enable_antecipacao_automatica = ?';
            $params[] = $dados['enable_antecipacao_automatica'];
        }
        if ($this->temColunaSomenteVinculados() && array_key_exists('conciliar_somente_vinculados', $dados)) {
            $sql .= ', conciliar_somente_vinculados = ?';
            $params[] = $dados['conciliar_somente_vinculados'];
        }
        if ($this->temColunaPixRecorrencia() && array_key_exists('enable_pix_recorrencia', $dados)) {
            $sql .= ', enable_pix_recorrencia = ?';
            $params[] = $dados['enable_pix_recorrencia'];
        }
        if ($this->temColunaMaxParcelas() && array_key_exists('max_parcelas_cartao', $dados)) {
            $sql .= ', max_parcelas_cartao = ?';
            $params[] = $dados['max_parcelas_cartao'];
        }
        $sql .= ', updated_at = NOW()';

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
