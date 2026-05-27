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

        if ($pendentes > 0) {
            $novoStatus = $diasAtraso >= 5 ? 'cancelada' : ($diasAtraso >= 1 ? 'vencida' : 'ativa');
        }

        $statusId = DB::table('status_matricula')->where('codigo', $novoStatus)->value('id');
        if (! $statusId) {
            return;
        }

        DB::table('matriculas')
            ->where('tenant_id', $tenantId)
            ->where('id', $matriculaId)
            ->update(['status_id' => $statusId, 'updated_at' => now()]);

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
}
