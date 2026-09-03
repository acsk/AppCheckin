<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

class TenantPlanoRepository
{
    /**
     * @param  array<string, mixed>  $dados
     */
    public function criar(array $dados): int
    {
        if ($this->buscarContratoAtivo((int) $dados['tenant_id'])) {
            throw new \RuntimeException(
                'Esta academia já possui um contrato ativo. Desative ou cancele o contrato atual antes de criar um novo.',
            );
        }

        $planoSistemaId = $dados['plano_sistema_id'] ?? $dados['plano_id'] ?? null;

        return (int) DB::table('tenant_planos_sistema')->insertGetId([
            'tenant_id' => $dados['tenant_id'],
            'plano_id' => $planoSistemaId,
            'plano_sistema_id' => $planoSistemaId,
            'status_id' => $dados['status_id'] ?? 1,
            'data_inicio' => $dados['data_inicio'],
            'observacoes' => $dados['observacoes'] ?? null,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function buscarContratoAtivo(int $tenantId): ?array
    {
        $row = DB::table('tenant_planos_sistema as tp')
            ->join('planos_sistema as ps', 'tp.plano_sistema_id', '=', 'ps.id')
            ->join('status_contrato as sc', 'tp.status_id', '=', 'sc.id')
            ->where('tp.tenant_id', $tenantId)
            ->where('tp.status_id', 1)
            ->select([
                'tp.*',
                'ps.nome as plano_nome',
                'ps.valor',
                'ps.max_alunos',
                'ps.max_admins',
                'ps.features',
                'sc.nome as status_nome',
                'sc.id as status_id',
            ])
            ->first();

        return $row ? (array) $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function buscarPorId(int $id): ?array
    {
        $row = DB::table('tenant_planos_sistema as tp')
            ->join('tenants as t', 'tp.tenant_id', '=', 't.id')
            ->join('planos_sistema as ps', 'tp.plano_sistema_id', '=', 'ps.id')
            ->join('status_contrato as sc', 'tp.status_id', '=', 'sc.id')
            ->where('tp.id', $id)
            ->select([
                'tp.*',
                't.id as tenant_id',
                't.nome as tenant_nome',
                'ps.id as plano_sistema_id',
                'ps.nome as plano_nome',
                'ps.valor as plano_valor',
                'ps.descricao as plano_descricao',
                'ps.duracao_dias',
                'sc.id as status_id',
                'sc.nome as status_nome',
            ])
            ->first();

        return $row ? (array) $row : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listarTodos(): array
    {
        return DB::table('tenant_planos_sistema as tp')
            ->join('tenants as t', 'tp.tenant_id', '=', 't.id')
            ->join('planos_sistema as ps', 'tp.plano_sistema_id', '=', 'ps.id')
            ->join('status_contrato as sc', 'tp.status_id', '=', 'sc.id')
            ->orderByDesc('tp.created_at')
            ->get([
                'tp.*',
                't.nome as academia_nome',
                't.id as academia_id',
                'ps.nome as plano_nome',
                'ps.valor',
                'ps.duracao_dias',
                'ps.max_alunos',
                'ps.max_admins',
                'ps.features',
                'sc.nome as status_nome',
                'sc.id as status_id',
            ])
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listarPorTenant(int $tenantId): array
    {
        return DB::table('tenant_planos_sistema as tp')
            ->join('planos_sistema as ps', 'tp.plano_sistema_id', '=', 'ps.id')
            ->join('status_contrato as sc', 'tp.status_id', '=', 'sc.id')
            ->where('tp.tenant_id', $tenantId)
            ->orderByDesc('tp.created_at')
            ->get([
                'tp.*',
                'ps.nome as plano_nome',
                'ps.valor',
                'ps.duracao_dias',
                'ps.max_alunos',
                'ps.max_admins',
                'ps.features',
                'sc.nome as status_nome',
                'sc.id as status_id',
            ])
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    public function desativarContratoAtivo(int $tenantId): bool
    {
        return DB::table('tenant_planos_sistema')
            ->where('tenant_id', $tenantId)
            ->where('status_id', 1)
            ->update(['status_id' => 3]) >= 0;
    }

    /**
     * @return array<string, mixed>
     */
    public function trocarPlano(int $tenantId, int $novoPlanoSistemaId, int $formaPagamentoId, ?string $observacoes = null): array
    {
        return DB::transaction(function () use ($tenantId, $novoPlanoSistemaId, $formaPagamentoId, $observacoes) {
            $this->desativarContratoAtivo($tenantId);

            $dataInicio = date('Y-m-d');

            $contratoId = $this->criar([
                'tenant_id' => $tenantId,
                'plano_sistema_id' => $novoPlanoSistemaId,
                'status_id' => 2,
                'data_inicio' => $dataInicio,
                'observacoes' => $observacoes ?? 'Troca de plano',
            ]);

            return $this->buscarPorId($contratoId) ?? [
                'id' => $contratoId,
                'tenant_id' => $tenantId,
                'plano_sistema_id' => $novoPlanoSistemaId,
            ];
        });
    }

    public function cancelar(int $id): bool
    {
        return DB::table('tenant_planos_sistema')
            ->where('id', $id)
            ->update(['status_id' => 3, 'updated_at' => DB::raw('NOW()')]) >= 0;
    }

    public function renovar(int $contratoId, string $novaDataVencimento): bool
    {
        return DB::table('tenant_planos_sistema')
            ->where('id', $contratoId)
            ->update([
                'data_vencimento' => $novaDataVencimento,
                'updated_at' => DB::raw('NOW()'),
            ]) >= 0;
    }

    public function atualizarStatus(int $contratoId, int $statusId): bool
    {
        return DB::table('tenant_planos_sistema')
            ->where('id', $contratoId)
            ->update(['status_id' => $statusId]) >= 0;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function proximosVencimento(int $dias = 7): array
    {
        $rows = DB::select(
            'SELECT tp.*, t.nome as academia_nome, t.id as academia_id,
                    ps.nome as plano_nome, ps.valor, sc.nome as status_nome,
                    MIN(pc.data_vencimento) as proximo_vencimento,
                    DATEDIFF(MIN(pc.data_vencimento), CURDATE()) as dias_restantes
             FROM tenant_planos_sistema tp
             INNER JOIN tenants t ON tp.tenant_id = t.id
             INNER JOIN planos_sistema ps ON tp.plano_sistema_id = ps.id
             INNER JOIN status_contrato sc ON tp.status_id = sc.id
             LEFT JOIN pagamentos_contrato pc ON tp.id = pc.tenant_plano_id AND pc.status_pagamento_id = 1
             WHERE tp.status_id = 1
             AND pc.data_vencimento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
             GROUP BY tp.id
             ORDER BY proximo_vencimento ASC',
            [$dias],
        );

        return array_map(fn ($row) => (array) $row, $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function vencidos(): array
    {
        $rows = DB::select(
            'SELECT tp.*, t.nome as academia_nome, t.id as academia_id,
                    ps.nome as plano_nome, ps.valor, sc.nome as status_nome,
                    MIN(pc.data_vencimento) as data_vencimento_antiga,
                    DATEDIFF(CURDATE(), MIN(pc.data_vencimento)) as dias_vencido
             FROM tenant_planos_sistema tp
             INNER JOIN tenants t ON tp.tenant_id = t.id
             INNER JOIN planos_sistema ps ON tp.plano_sistema_id = ps.id
             INNER JOIN status_contrato sc ON tp.status_id = sc.id
             LEFT JOIN pagamentos_contrato pc ON tp.id = pc.tenant_plano_id AND pc.status_pagamento_id IN (1, 4)
             WHERE tp.status_id = 1
             AND pc.data_vencimento < CURDATE()
             GROUP BY tp.id
             ORDER BY data_vencimento_antiga ASC',
        );

        return array_map(fn ($row) => (array) $row, $rows);
    }
}
