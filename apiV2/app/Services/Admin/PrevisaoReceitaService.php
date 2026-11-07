<?php

namespace App\Services\Admin;

use Illuminate\Support\Facades\DB;

/**
 * Previsão de renovações a receber: a baixa manual não deixa parcela futura em aberto, então o "a receber"
 * do mês vem do próximo vencimento gravado nas matrículas que não têm cobrança em aberto.
 */
class PrevisaoReceitaService
{
    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function previsao(int $tenantId, ?string $dataInicio, ?string $dataFim): array
    {
        $valida = static fn (?string $d) => $d !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) === 1;
        if (! $valida($dataInicio) || ! $valida($dataFim) || $dataInicio > $dataFim) {
            return ['status' => 422, 'body' => ['error' => 'Informe data_inicio e data_fim (YYYY-MM-DD)']];
        }

        $rows = DB::table('matriculas as m')
            ->join('status_matricula as sm', 'sm.id', '=', 'm.status_id')
            ->join('planos as p', 'p.id', '=', 'm.plano_id')
            ->leftJoin('alunos as a', 'a.id', '=', 'm.aluno_id')
            ->where('m.tenant_id', $tenantId)
            ->whereIn('sm.codigo', ['ativa', 'vencida'])
            ->where('m.valor', '>', 0)
            ->where('m.tipo_cobranca', '!=', 'recorrente')
            ->whereNull('m.pacote_contrato_id')
            ->whereBetween('m.proxima_data_vencimento', [$dataInicio, $dataFim])
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))->from('pagamentos_plano as pp')
                    ->whereColumn('pp.matricula_id', 'm.id')
                    ->whereIn('pp.status_pagamento_id', [1, 3]);
            })
            ->orderBy('m.proxima_data_vencimento')
            ->get(['m.id as matricula_id', 'm.aluno_id', 'a.nome as aluno_nome', 'p.nome as plano_nome',
                'm.valor', 'm.proxima_data_vencimento as data_vencimento']);

        $previsoes = $rows->map(fn ($r) => [
            'matricula_id' => (int) $r->matricula_id,
            'aluno_id' => (int) $r->aluno_id,
            'aluno_nome' => $r->aluno_nome,
            'plano_nome' => $r->plano_nome,
            'valor' => (float) $r->valor,
            'data_vencimento' => substr((string) $r->data_vencimento, 0, 10),
            'previsto' => true,
        ])->all();

        return [
            'status' => 200,
            'body' => [
                'previsoes' => $previsoes,
                'total' => round(array_sum(array_column($previsoes, 'valor')), 2),
            ],
        ];
    }
}
