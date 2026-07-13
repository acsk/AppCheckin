<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

class ModalidadeRepository
{
    public function listarPorTenant(int $tenantId, bool $apenasAtivas = false): array
    {
        $query = DB::table('modalidades as m')
            ->where('m.tenant_id', $tenantId)
            ->select([
                'm.*',
                DB::raw('(SELECT COUNT(*) FROM planos p WHERE p.modalidade_id = m.id AND p.ativo = 1) as planos_count'),
            ])
            ->orderBy('m.nome');

        if ($apenasAtivas) {
            $query->where('m.ativo', 1);
        }

        $modalidades = $query->get()->map(fn ($row) => (array) $row)->all();

        foreach ($modalidades as &$modalidade) {
            $modalidade['planos'] = $this->listarPlanosAtivos((int) $modalidade['id']);
        }

        return $modalidades;
    }

    public function buscarPorId(int $id, ?int $tenantId = null): ?array
    {
        $query = DB::table('modalidades')->where('id', $id);
        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }

        $row = $query->first();
        if (! $row) {
            return null;
        }

        $result = (array) $row;
        $result['planos'] = DB::table('planos')
            ->where('modalidade_id', $id)
            ->orderBy('checkins_semanais')
            ->get(['id', 'nome', 'valor', 'checkins_semanais', 'duracao_dias', 'ativo', 'atual'])
            ->map(fn ($p) => (array) $p)
            ->all();

        return $result;
    }

    public function nomeExiste(int $tenantId, string $nome, ?int $excludeId = null): bool
    {
        $query = DB::table('modalidades')
            ->where('tenant_id', $tenantId)
            ->where('nome', $nome);

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    public function criar(array $dados): int
    {
        return (int) DB::table('modalidades')->insertGetId([
            'tenant_id' => $dados['tenant_id'],
            'nome' => $dados['nome'],
            'descricao' => $dados['descricao'] ?? null,
            'cor' => $dados['cor'] ?? '#f97316',
            'icone' => $dados['icone'] ?? 'activity',
            'ativo' => $dados['ativo'] ?? 1,
        ]);
    }

    public function atualizar(int $id, array $dados): bool
    {
        return DB::table('modalidades')->where('id', $id)->update([
            'nome' => $dados['nome'],
            'descricao' => $dados['descricao'] ?? null,
            'cor' => $dados['cor'] ?? '#f97316',
            'icone' => $dados['icone'] ?? 'activity',
            'ativo' => $dados['ativo'] ?? 1,
        ]) > 0;
    }

    public function alternarAtivo(int $id): bool
    {
        return DB::update('UPDATE modalidades SET ativo = NOT ativo WHERE id = ?', [$id]) > 0;
    }

    /**
     * @return list<int>
     */
    public function idsPlanosDaModalidade(int $modalidadeId): array
    {
        return DB::table('planos')
            ->where('modalidade_id', $modalidadeId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function planoDuplicado(
        int $modalidadeId,
        string $nome,
        float|string $valor,
        int $checkinsSemanais,
        int $duracaoDias,
        int $excludeId = 0,
    ): bool {
        return DB::table('planos')
            ->where('modalidade_id', $modalidadeId)
            ->where('nome', $nome)
            ->where('valor', $valor)
            ->where('checkins_semanais', $checkinsSemanais)
            ->where('duracao_dias', $duracaoDias)
            ->where('id', '!=', $excludeId)
            ->exists();
    }

    public function criarPlano(int $tenantId, int $modalidadeId, array $plano): int
    {
        return (int) DB::table('planos')->insertGetId([
            'tenant_id' => $tenantId,
            'modalidade_id' => $modalidadeId,
            'nome' => $plano['nome'],
            'valor' => $plano['valor'],
            'checkins_semanais' => $plano['checkins_semanais'],
            'duracao_dias' => $plano['duracao_dias'] ?? 30,
            'ativo' => $plano['ativo'] ?? 1,
            'atual' => $plano['atual'] ?? 1,
        ]);
    }

    public function atualizarPlano(int $planoId, int $modalidadeId, array $plano): void
    {
        DB::table('planos')
            ->where('id', $planoId)
            ->where('modalidade_id', $modalidadeId)
            ->update([
                'nome' => $plano['nome'],
                'valor' => $plano['valor'],
                'checkins_semanais' => $plano['checkins_semanais'],
                'duracao_dias' => $plano['duracao_dias'] ?? 30,
                'ativo' => $plano['ativo'] ?? 1,
                'atual' => $plano['atual'] ?? 1,
            ]);
    }

    /**
     * @param  list<int>  $ids
     */
    public function excluirPlanos(array $ids): void
    {
        if ($ids === []) {
            return;
        }

        DB::table('planos')->whereIn('id', $ids)->delete();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listarPlanosAtivos(int $modalidadeId): array
    {
        return DB::table('planos')
            ->where('modalidade_id', $modalidadeId)
            ->where('ativo', 1)
            ->orderBy('checkins_semanais')
            ->get(['id', 'nome', 'valor', 'checkins_semanais', 'duracao_dias', 'ativo', 'atual'])
            ->map(fn ($p) => (array) $p)
            ->all();
    }
}
