<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Regras de pagamentos_plano e status da matrícula (paridade com api Slim).
 */
class PagamentoPlanoService
{
    public function cancelarParcelasAbertas(int $tenantId, int $matriculaId, string $motivo): int
    {
        return DB::update(
            "UPDATE pagamentos_plano
             SET status_pagamento_id = 4,
                 observacoes = CONCAT(COALESCE(observacoes, ''), ?),
                 updated_at = NOW()
             WHERE tenant_id = ? AND matricula_id = ?
               AND status_pagamento_id IN (1, 3) AND data_pagamento IS NULL",
            [' ['.$motivo.']', $tenantId, $matriculaId]
        );
    }

    /**
     * Deixa uma única parcela pendente com o valor atual da matrícula (evita baixa MP na parcela errada).
     */
    public function garantirParcelaPendenteUnica(
        int $tenantId,
        int $alunoId,
        int $matriculaId,
        int $planoId,
        float $valor,
        string $dataVencimento,
        int $criadoPor,
        string $observacoes = 'Aguardando pagamento via Mercado Pago'
    ): int {
        $this->cancelarParcelasAbertas($tenantId, $matriculaId, 'Substituída por nova cobrança');

        $existente = DB::table('pagamentos_plano')
            ->where('tenant_id', $tenantId)
            ->where('matricula_id', $matriculaId)
            ->whereIn('status_pagamento_id', [1, 3])
            ->whereNull('data_pagamento')
            ->where('valor', $valor)
            ->orderByDesc('id')
            ->value('id');

        if ($existente) {
            return (int) $existente;
        }

        return (int) DB::table('pagamentos_plano')->insertGetId([
            'tenant_id' => $tenantId,
            'aluno_id' => $alunoId,
            'matricula_id' => $matriculaId,
            'plano_id' => $planoId,
            'valor' => $valor,
            'data_vencimento' => $dataVencimento,
            'status_pagamento_id' => 1,
            'observacoes' => $observacoes,
            'criado_por' => $criadoPor,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function atualizarStatusMatricula(int $tenantId, int $matriculaId): void
    {
        $matriculaMeta = DB::table('matriculas as m')
            ->leftJoin('status_matricula as sm', 'sm.id', '=', 'm.status_id')
            ->leftJoin('planos as p', 'p.id', '=', 'm.plano_id')
            ->where('m.tenant_id', $tenantId)
            ->where('m.id', $matriculaId)
            ->first(['m.tipo_cobranca', 'm.data_vencimento', 'sm.codigo as status_codigo', 'p.duracao_dias']);

        if (! $matriculaMeta) {
            return;
        }

        $ehAvulso = ($matriculaMeta->tipo_cobranca ?? '') === 'avulso';
        $ehDiariaAvulsa = $ehAvulso && (int) ($matriculaMeta->duracao_dias ?? 0) === 1;
        $statusAtual = (string) ($matriculaMeta->status_codigo ?? '');

        if ($ehDiariaAvulsa && in_array($statusAtual, ['cancelada', 'concluida', 'finalizada'], true)) {
            return;
        }

        $row = DB::table('pagamentos_plano')
            ->where('tenant_id', $tenantId)
            ->where('matricula_id', $matriculaId)
            ->selectRaw('
                MAX(CASE WHEN status_pagamento_id IN (1, 3) AND data_vencimento < CURDATE()
                    THEN DATEDIFF(CURDATE(), data_vencimento) ELSE 0 END) as dias_atraso,
                SUM(CASE WHEN status_pagamento_id IN (1, 3) THEN 1 ELSE 0 END) as pendentes
            ')
            ->first();

        $novoStatus = 'ativa';
        $pendentes = (int) ($row->pendentes ?? 0);
        $diasAtraso = (int) ($row->dias_atraso ?? 0);

        // Avulso: acesso = fim do período PAGO, por parcela paga:
        // venc >= pago + ciclo → parcela já representa o FIM do período (fluxo MP);
        // senão → venc é a data devida (início); fim = GREATEST(pago, venc) + ciclo.
        // Parcela futura "Aguardando" é só cobrança — não estende vigência.
        $acessoAte = null;
        if ($ehAvulso) {
            $acessoAte = DB::table('pagamentos_plano as pg')
                ->join('matriculas as m2', function ($join) {
                    $join->on('m2.id', '=', 'pg.matricula_id')
                        ->on('m2.tenant_id', '=', 'pg.tenant_id');
                })
                ->join('planos as p2', 'p2.id', '=', 'm2.plano_id')
                ->leftJoin('plano_ciclos as pc', 'pc.id', '=', 'm2.plano_ciclo_id')
                ->where('pg.tenant_id', $tenantId)
                ->where('pg.matricula_id', $matriculaId)
                ->where('pg.status_pagamento_id', 2)
                ->selectRaw("MAX(CASE
                    WHEN COALESCE(p2.duracao_dias, 0) = 1 THEN
                        CASE
                            WHEN pg.data_pagamento IS NULL THEN pg.data_vencimento
                            WHEN pg.data_vencimento >= DATE_ADD(pg.data_pagamento, INTERVAL 1 DAY)
                                THEN pg.data_vencimento
                            ELSE DATE_ADD(GREATEST(pg.data_pagamento, pg.data_vencimento), INTERVAL 1 DAY)
                        END
                    WHEN pg.data_pagamento IS NULL THEN pg.data_vencimento
                    WHEN pg.data_vencimento >= DATE_ADD(pg.data_pagamento, INTERVAL COALESCE(pc.meses, 1) MONTH)
                        THEN pg.data_vencimento
                    ELSE DATE_ADD(GREATEST(pg.data_pagamento, pg.data_vencimento), INTERVAL COALESCE(pc.meses, 1) MONTH)
                END) as fim_periodo")
                ->value('fim_periodo')
                ?: ($matriculaMeta->data_vencimento ?? null);

            if ($acessoAte && $acessoAte < date('Y-m-d')) {
                $diasAtrasoAcesso = (int) ((new \DateTime(date('Y-m-d')))->diff(new \DateTime($acessoAte))->days);
                $novoStatus = ($ehDiariaAvulsa || $diasAtrasoAcesso >= 5) ? 'cancelada' : 'vencida';
            }
            // Avulso: status/acesso só pelo período pago. Parcelas pendentes
            // são cobrança futura e não alteram vigência nem status.
        } elseif ($pendentes > 0) {
            if ($diasAtraso >= 5) {
                $novoStatus = 'cancelada';
            } elseif ($diasAtraso >= 1) {
                $novoStatus = 'vencida';
            }
        }

        $statusId = DB::table('status_matricula')->where('codigo', $novoStatus)->value('id');
        if (! $statusId) {
            return;
        }

        DB::table('matriculas')
            ->where('tenant_id', $tenantId)
            ->where('id', $matriculaId)
            ->update(['status_id' => $statusId, 'updated_at' => now()]);

        if ($ehAvulso) {
            if ($acessoAte) {
                DB::table('matriculas')
                    ->where('id', $matriculaId)
                    ->where('tenant_id', $tenantId)
                    ->update([
                        'data_vencimento' => $acessoAte,
                        'proxima_data_vencimento' => $acessoAte,
                        'updated_at' => now(),
                    ]);
            }
            // Avulso sem parcela paga: não usar pendentes para proxima_data_vencimento.
            return;
        }

        if ($novoStatus === 'ativa') {
            $proxima = DB::table('pagamentos_plano')
                ->where('matricula_id', $matriculaId)
                ->whereIn('status_pagamento_id', [1, 3])
                ->min('data_vencimento');

            if ($proxima) {
                DB::table('matriculas')
                    ->where('id', $matriculaId)
                    ->where('tenant_id', $tenantId)
                    ->update(['proxima_data_vencimento' => $proxima, 'updated_at' => now()]);
            }
        }
    }

    /**
     * Numera parcelas pela ordem de vencimento (lista já ordenada por data_vencimento, id).
     * Paridade com Slim PagamentoPlano::anexarNumeroParcela.
     *
     * @param  list<array<string, mixed>>  $pagamentos
     * @return list<array<string, mixed>>
     */
    public static function anexarNumeroParcela(array $pagamentos): array
    {
        foreach ($pagamentos as $idx => &$pagamento) {
            $pagamento['numero_parcela'] = $idx + 1;
        }
        unset($pagamento);

        return $pagamentos;
    }

    /**
     * Marca parcelas pendentes vencidas como atrasado (status 3).
     * Paridade com Slim PagamentoPlano::marcarAtrasados.
     */
    public function marcarAtrasados(int $tenantId): int
    {
        $this->corrigirParcelasFuturasMarcadasAtrasadas($tenantId);

        $marcados = DB::update(
            "UPDATE pagamentos_plano
             SET status_pagamento_id = 3,
                 updated_at = NOW()
             WHERE tenant_id = ?
             AND status_pagamento_id = 1
             AND data_vencimento < CURDATE()
             AND data_pagamento IS NULL",
            [$tenantId]
        );

        $sqlAvulso = '
            UPDATE pagamentos_plano pp
            INNER JOIN matriculas m ON m.id = pp.matricula_id AND m.tenant_id = pp.tenant_id
            SET pp.status_pagamento_id = 3, pp.updated_at = NOW()
            WHERE pp.tenant_id = ?
              AND m.tipo_cobranca = \'avulso\'
              AND pp.status_pagamento_id = 1
              AND pp.data_pagamento IS NULL
              AND '.self::sqlAvulsoPeriodoPagoExpirou('pp').'
        ';
        $marcadosAvulso = DB::update($sqlAvulso, [$tenantId]);

        return $marcados + $marcadosAvulso;
    }

    /**
     * Expressão SQL do fim do período pago (avulso).
     * Tolerância de 2 dias evita somar ciclo extra quando vencimento usa +30d
     * e a comparação usa +1 mês (ex.: pago 10/07, venc 09/08 → fim 09/08).
     */
    public static function sqlFimPeriodoPago(string $tenantExpr, string $matriculaExpr): string
    {
        return "(
            SELECT MAX(CASE
                WHEN COALESCE(p_acesso.duracao_dias, 0) = 1 THEN
                    CASE
                        WHEN pp_acesso.data_pagamento IS NULL THEN pp_acesso.data_vencimento
                        WHEN pp_acesso.data_vencimento >= DATE_ADD(pp_acesso.data_pagamento, INTERVAL 1 DAY)
                            THEN pp_acesso.data_vencimento
                        ELSE DATE_ADD(GREATEST(pp_acesso.data_pagamento, pp_acesso.data_vencimento), INTERVAL 1 DAY)
                    END
                WHEN pp_acesso.data_pagamento IS NULL THEN pp_acesso.data_vencimento
                WHEN pp_acesso.data_vencimento >= DATE_SUB(
                        DATE_ADD(pp_acesso.data_pagamento, INTERVAL COALESCE(pc_acesso.meses, 1) MONTH),
                        INTERVAL 2 DAY
                    )
                    THEN pp_acesso.data_vencimento
                ELSE DATE_ADD(GREATEST(pp_acesso.data_pagamento, pp_acesso.data_vencimento), INTERVAL COALESCE(pc_acesso.meses, 1) MONTH)
            END)
            FROM pagamentos_plano pp_acesso
            INNER JOIN matriculas m_acesso
                ON m_acesso.id = pp_acesso.matricula_id AND m_acesso.tenant_id = pp_acesso.tenant_id
            INNER JOIN planos p_acesso ON p_acesso.id = m_acesso.plano_id
            LEFT JOIN plano_ciclos pc_acesso ON pc_acesso.id = m_acesso.plano_ciclo_id
            WHERE pp_acesso.tenant_id = {$tenantExpr}
              AND pp_acesso.matricula_id = {$matriculaExpr}
              AND pp_acesso.status_pagamento_id = 2
        )";
    }

    private static function sqlAvulsoPeriodoPagoExpirou(string $ppAlias = 'pp'): string
    {
        return 'COALESCE('.self::sqlFimPeriodoPago("{$ppAlias}.tenant_id", "{$ppAlias}.matricula_id").", '1900-01-01') < CURDATE()";
    }

    /**
     * Parcelas com vencimento futuro não podem permanecer como Atrasado (3),
     * exceto renovação avulsa com período pago já expirado.
     */
    public function corrigirParcelasFuturasMarcadasAtrasadas(int $tenantId): int
    {
        $sql = '
            UPDATE pagamentos_plano pp
            INNER JOIN matriculas m ON m.id = pp.matricula_id AND m.tenant_id = pp.tenant_id
            SET pp.status_pagamento_id = 1, pp.updated_at = NOW()
            WHERE pp.tenant_id = ?
            AND pp.status_pagamento_id = 3
            AND pp.data_vencimento >= CURDATE()
            AND pp.data_pagamento IS NULL
            AND NOT (
                m.tipo_cobranca = \'avulso\'
                AND '.self::sqlAvulsoPeriodoPagoExpirou('pp').'
            )';

        return DB::update($sql, [$tenantId]);
    }
}
