<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

class AdminPlanoRepository
{
    public function listar(int $tenantId, bool $apenasAtivos = false): array
    {
        $query = DB::table('planos as p')
            ->leftJoin('modalidades as m', 'm.id', '=', 'p.modalidade_id')
            ->where('p.tenant_id', $tenantId)
            ->select([
                'p.*',
                'm.nome as modalidade_nome',
                'm.cor as modalidade_cor',
                'm.icone as modalidade_icone',
            ])
            ->orderBy('p.valor');

        if ($apenasAtivos) {
            $query->where('p.ativo', 1);
        }

        $planos = $query->get()->map(fn ($r) => (array) $r)->all();
        if ($planos === []) {
            return [];
        }

        $planoIds = array_map(fn ($p) => (int) $p['id'], $planos);
        $ciclos = DB::table('plano_ciclos as pc')
            ->leftJoin('assinatura_frequencias as af', 'af.id', '=', 'pc.assinatura_frequencia_id')
            ->whereIn('pc.plano_id', $planoIds)
            ->where('pc.tenant_id', $tenantId)
            ->where('pc.ativo', 1)
            ->orderBy('pc.meses')
            ->select([
                'pc.*',
                'af.codigo as frequencia_codigo',
                'af.nome as frequencia_nome',
                'af.meses as frequencia_meses',
            ])
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        $porPlano = [];
        foreach ($ciclos as $ciclo) {
            $porPlano[(int) $ciclo['plano_id']][] = $ciclo;
        }

        foreach ($planos as &$plano) {
            $plano['ciclos'] = $porPlano[(int) $plano['id']] ?? [];
        }
        unset($plano);

        return $planos;
    }

    public function findById(int $id, int $tenantId): ?array
    {
        $row = DB::table('planos as p')
            ->leftJoin('modalidades as m', 'm.id', '=', 'p.modalidade_id')
            ->where('p.id', $id)
            ->where('p.tenant_id', $tenantId)
            ->select([
                'p.*',
                'm.nome as modalidade_nome',
                'm.cor as modalidade_cor',
                'm.icone as modalidade_icone',
            ])
            ->first();

        return $row ? (array) $row : null;
    }

    public function findBasico(int $id, int $tenantId): ?array
    {
        $row = DB::table('planos')
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->first(['id', 'nome', 'valor']);

        return $row ? (array) $row : null;
    }

    public function existeDuplicado(
        int $tenantId,
        int $modalidadeId,
        string $nome,
        float|string $valor,
        int $checkinsSemanais,
        int $duracaoDias,
        ?int $excludeId = null,
    ): bool {
        $query = DB::table('planos')
            ->where('tenant_id', $tenantId)
            ->where('modalidade_id', $modalidadeId)
            ->where('nome', $nome)
            ->where('valor', $valor)
            ->where('checkins_semanais', $checkinsSemanais)
            ->where('duracao_dias', $duracaoDias);

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    public function create(int $tenantId, array $data): int
    {
        return (int) DB::table('planos')->insertGetId([
            'tenant_id' => $tenantId,
            'modalidade_id' => $data['modalidade_id'],
            'nome' => $data['nome'],
            'descricao' => $data['descricao'] ?? null,
            'valor' => $data['valor'],
            'duracao_dias' => $data['duracao_dias'] ?? 30,
            'checkins_semanais' => $data['checkins_semanais'],
            'ativo' => $data['ativo'] ?? 1,
            'atual' => $data['atual'] ?? 1,
        ]);
    }

    public function update(int $id, int $tenantId, array $data): bool
    {
        $allowed = ['modalidade_id', 'nome', 'descricao', 'valor', 'duracao_dias', 'checkins_semanais', 'ativo', 'atual'];
        $update = [];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $update[$field] = $data[$field];
            }
        }

        if ($update === []) {
            return false;
        }

        DB::table('planos')
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->update($update);

        return true;
    }

    public function softDelete(int $id, int $tenantId): bool
    {
        return DB::table('planos')
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->update(['ativo' => 0]) > 0;
    }

    public function countUsuariosAtivos(int $planoId, int $tenantId): int
    {
        return (int) DB::table('matriculas as m')
            ->join('status_matricula as sm', 'sm.id', '=', 'm.status_id')
            ->where('m.plano_id', $planoId)
            ->where('m.tenant_id', $tenantId)
            ->where('sm.codigo', 'ativa')
            ->selectRaw('COUNT(DISTINCT m.aluno_id) as total')
            ->value('total');
    }

    public function possuiContratos(int $planoId, int $tenantId): bool
    {
        return DB::table('matriculas as m')
            ->join('status_matricula as sm', 'sm.id', '=', 'm.status_id')
            ->where('m.plano_id', $planoId)
            ->where('m.tenant_id', $tenantId)
            ->where('sm.codigo', '!=', 'cancelada')
            ->exists();
    }
}
