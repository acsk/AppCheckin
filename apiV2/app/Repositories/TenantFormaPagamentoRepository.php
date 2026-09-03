<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

/**
 * Configurações de formas de pagamento por tenant (tenant_formas_pagamento).
 *
 * Paridade com api/app/Models/TenantFormaPagamento.php (Slim).
 */
class TenantFormaPagamentoRepository
{
    public function listar(int $tenantId, bool $apenasAtivas = false): array
    {
        $sql = 'SELECT
                    tfp.*,
                    fp.nome as forma_pagamento_nome,
                    fp.descricao as forma_pagamento_descricao
                FROM tenant_formas_pagamento tfp
                INNER JOIN formas_pagamento fp ON tfp.forma_pagamento_id = fp.id
                WHERE tfp.tenant_id = ?';

        if ($apenasAtivas) {
            $sql .= ' AND tfp.ativo = 1 AND fp.ativo = 1';
        }

        $sql .= ' ORDER BY fp.nome';

        return array_map(fn ($row) => (array) $row, DB::select($sql, [$tenantId]));
    }

    public function buscar(int $id, int $tenantId): ?array
    {
        $row = DB::selectOne(
            'SELECT tfp.*, fp.nome as forma_pagamento_nome
             FROM tenant_formas_pagamento tfp
             INNER JOIN formas_pagamento fp ON tfp.forma_pagamento_id = fp.id
             WHERE tfp.id = ? AND tfp.tenant_id = ?',
            [$id, $tenantId]
        );

        return $row ? (array) $row : null;
    }

    /**
     * Taxas ativas por forma_pagamento_id (para cálculo de valor líquido).
     *
     * @return array<string, mixed>|null
     */
    public function buscarTaxasAtivas(int $tenantId, int $formaPagamentoId): ?array
    {
        $row = DB::selectOne(
            'SELECT taxa_percentual, taxa_fixa
             FROM tenant_formas_pagamento
             WHERE tenant_id = ?
               AND forma_pagamento_id = ?
               AND ativo = 1',
            [$tenantId, $formaPagamentoId]
        );

        return $row ? (array) $row : null;
    }

    /**
     * Configuração ativa completa por forma_pagamento_id (para parcelamento).
     *
     * @return array<string, mixed>|null
     */
    public function buscarConfigAtiva(int $tenantId, int $formaPagamentoId): ?array
    {
        $row = DB::selectOne(
            'SELECT *
             FROM tenant_formas_pagamento
             WHERE tenant_id = ?
               AND forma_pagamento_id = ?
               AND ativo = 1',
            [$tenantId, $formaPagamentoId]
        );

        return $row ? (array) $row : null;
    }

    /**
     * @param  array<string, mixed>  $dados
     */
    public function atualizar(int $id, int $tenantId, array $dados): bool
    {
        return DB::update(
            'UPDATE tenant_formas_pagamento SET
                ativo = ?,
                taxa_percentual = ?,
                taxa_fixa = ?,
                aceita_parcelamento = ?,
                parcelas_minimas = ?,
                parcelas_maximas = ?,
                juros_parcelamento = ?,
                parcelas_sem_juros = ?,
                dias_compensacao = ?,
                valor_minimo = ?,
                observacoes = ?,
                updated_at = CURRENT_TIMESTAMP
             WHERE id = ? AND tenant_id = ?',
            [
                $dados['ativo'],
                $dados['taxa_percentual'],
                $dados['taxa_fixa'],
                $dados['aceita_parcelamento'],
                $dados['parcelas_minimas'],
                $dados['parcelas_maximas'],
                $dados['juros_parcelamento'],
                $dados['parcelas_sem_juros'],
                $dados['dias_compensacao'],
                $dados['valor_minimo'],
                $dados['observacoes'],
                $id,
                $tenantId,
            ]
        ) !== false;
    }
}
