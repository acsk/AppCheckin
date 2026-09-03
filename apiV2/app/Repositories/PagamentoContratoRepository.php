<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

class PagamentoContratoRepository
{
    /**
     * @param  array<string, mixed>  $dados
     */
    public function criar(array $dados): int
    {
        return (int) DB::table('pagamentos_contrato')->insertGetId([
            'tenant_plano_id' => $dados['tenant_plano_id'] ?? $dados['contrato_id'],
            'valor' => $dados['valor'],
            'data_vencimento' => $dados['data_vencimento'],
            'data_pagamento' => $dados['data_pagamento'] ?? null,
            'status_pagamento_id' => $dados['status_pagamento_id'] ?? 1,
            'forma_pagamento_id' => $dados['forma_pagamento_id'],
            'comprovante' => $dados['comprovante'] ?? null,
            'observacoes' => $dados['observacoes'] ?? null,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function buscarPorId(int $id): ?array
    {
        $row = DB::table('pagamentos_contrato as p')
            ->join('status_pagamento as sp', 'p.status_pagamento_id', '=', 'sp.id')
            ->leftJoin('formas_pagamento as fp', 'p.forma_pagamento_id', '=', 'fp.id')
            ->where('p.id', $id)
            ->select(['p.*', 'sp.nome as status_nome', 'fp.nome as forma_pagamento_nome'])
            ->first();

        return $row ? (array) $row : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listarPorContrato(int $contratoId): array
    {
        return DB::table('pagamentos_contrato as p')
            ->join('status_pagamento as sp', 'p.status_pagamento_id', '=', 'sp.id')
            ->leftJoin('formas_pagamento as fp', 'p.forma_pagamento_id', '=', 'fp.id')
            ->where('p.tenant_plano_id', $contratoId)
            ->orderByDesc('p.data_vencimento')
            ->select(['p.*', 'sp.nome as status_nome', 'fp.nome as forma_pagamento_nome'])
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return list<array<string, mixed>>
     */
    public function listarTodos(array $filtros = []): array
    {
        $query = DB::table('pagamentos_contrato as p')
            ->join('status_pagamento as sp', 'p.status_pagamento_id', '=', 'sp.id')
            ->join('tenant_planos_sistema as c', 'p.tenant_plano_id', '=', 'c.id')
            ->join('tenants as t', 'c.tenant_id', '=', 't.id')
            ->join('planos_sistema as ps', 'c.plano_sistema_id', '=', 'ps.id');

        if (! empty($filtros['status_pagamento_id'])) {
            $query->where('p.status_pagamento_id', $filtros['status_pagamento_id']);
        }
        if (! empty($filtros['tenant_id'])) {
            $query->where('c.tenant_id', $filtros['tenant_id']);
        }
        if (! empty($filtros['data_inicio'])) {
            $query->where('p.data_vencimento', '>=', $filtros['data_inicio']);
        }
        if (! empty($filtros['data_fim'])) {
            $query->where('p.data_vencimento', '<=', $filtros['data_fim']);
        }

        return $query
            ->orderByDesc('p.data_vencimento')
            ->get([
                'p.*',
                'sp.nome as status_nome',
                't.nome as academia_nome',
                'ps.nome as plano_nome',
                'c.data_inicio as contrato_inicio',
                'c.data_vencimento as contrato_vencimento',
            ])
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return list<array<string, mixed>>
     */
    public function resumo(array $filtros = []): array
    {
        $query = DB::table('pagamentos_contrato as p')
            ->join('status_pagamento as sp', 'p.status_pagamento_id', '=', 'sp.id')
            ->join('tenant_planos_sistema as c', 'p.tenant_plano_id', '=', 'c.id');

        if (! empty($filtros['tenant_id'])) {
            $query->where('c.tenant_id', $filtros['tenant_id']);
        }
        if (! empty($filtros['data_inicio'])) {
            $query->where('p.data_vencimento', '>=', $filtros['data_inicio']);
        }
        if (! empty($filtros['data_fim'])) {
            $query->where('p.data_vencimento', '<=', $filtros['data_fim']);
        }

        return $query
            ->groupBy('sp.id', 'sp.nome')
            ->get([
                'sp.nome as status',
                DB::raw('COUNT(*) as quantidade'),
                DB::raw('SUM(p.valor) as total'),
                DB::raw('SUM(CASE WHEN p.data_vencimento < CURDATE() AND p.status_pagamento_id != 2 THEN p.valor ELSE 0 END) as total_atrasado'),
            ])
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    public function confirmarPagamento(
        int $id,
        ?string $dataPagamento = null,
        ?int $formaPagamentoId = null,
        ?string $comprovante = null,
        ?string $observacoes = null,
    ): bool {
        $update = [
            'status_pagamento_id' => 2,
            'data_pagamento' => $dataPagamento ?? date('Y-m-d'),
        ];

        if ($formaPagamentoId !== null) {
            $update['forma_pagamento_id'] = $formaPagamentoId;
        }
        if ($comprovante !== null) {
            $update['comprovante'] = $comprovante;
        }
        if ($observacoes !== null) {
            $update['observacoes'] = $observacoes;
        }

        return DB::table('pagamentos_contrato')->where('id', $id)->update($update) > 0;
    }

    public function cancelar(int $id, ?string $observacoes = null): bool
    {
        $update = ['status_pagamento_id' => 4];
        if ($observacoes !== null) {
            $update['observacoes'] = $observacoes;
        }

        return DB::table('pagamentos_contrato')->where('id', $id)->update($update) > 0;
    }

    public function marcarAtrasados(): int
    {
        return DB::table('pagamentos_contrato')
            ->where('status_pagamento_id', 1)
            ->where('data_vencimento', '<', DB::raw('CURDATE()'))
            ->whereNull('data_pagamento')
            ->update(['status_pagamento_id' => 3]);
    }

    public function temPagamentosPendentes(int $contratoId): bool
    {
        return DB::table('pagamentos_contrato')
            ->where('tenant_plano_id', $contratoId)
            ->whereIn('status_pagamento_id', [1, 3])
            ->count() > 0;
    }

    public function bloquearContratosComPagamentosAtrasados(): int
    {
        return DB::update(
            'UPDATE tenant_planos_sistema c
             SET c.status_id = 4
             WHERE c.status_id = 1
             AND EXISTS (
                 SELECT 1 FROM pagamentos_contrato p
                 WHERE p.tenant_plano_id = c.id
                 AND p.status_pagamento_id = 3
             )',
        );
    }
}
