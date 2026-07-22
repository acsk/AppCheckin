<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

class PlanoRepository
{
    public function findPlanoAtivoIdPorUsuario(int $userId, int $tenantId): ?int
    {
        $id = DB::table('matriculas as m')
            ->join('alunos as a', 'a.id', '=', 'm.aluno_id')
            ->join('status_matricula as sm', 'sm.id', '=', 'm.status_id')
            ->where('a.usuario_id', $userId)
            ->where('m.tenant_id', $tenantId)
            ->where('sm.codigo', 'ativa')
            ->orderByDesc('m.created_at')
            ->value('m.plano_id');

        return $id !== null ? (int) $id : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listarMatriculasAtivasPorUsuario(int $userId, int $tenantId): array
    {
        return DB::table('matriculas as m')
            ->join('alunos as a', 'a.id', '=', 'm.aluno_id')
            ->join('status_matricula as sm', 'sm.id', '=', 'm.status_id')
            ->join('planos as p', 'p.id', '=', 'm.plano_id')
            ->where('a.usuario_id', $userId)
            ->where('m.tenant_id', $tenantId)
            ->where('sm.codigo', 'ativa')
            ->whereRaw('COALESCE(m.proxima_data_vencimento, m.data_vencimento) >= CURDATE()')
            ->orderByDesc('m.updated_at')
            ->orderByDesc('m.id')
            ->get([
                'm.id',
                'm.plano_id',
                'm.plano_ciclo_id',
                'm.valor',
                'm.data_inicio',
                'm.data_vencimento',
                'm.proxima_data_vencimento',
                'p.modalidade_id',
                'p.nome as plano_nome',
                'p.checkins_semanais',
                'sm.codigo as status_codigo',
            ])
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listarPlanosPagos(int $tenantId, ?int $modalidadeId = null): array
    {
        $query = DB::table('planos as p')
            ->leftJoin('modalidades as m', 'p.modalidade_id', '=', 'm.id')
            ->where('p.tenant_id', $tenantId)
            ->where('p.ativo', 1)
            ->where('p.valor', '>', 0)
            ->orderBy('p.valor');

        if ($modalidadeId) {
            $query->where('p.modalidade_id', $modalidadeId);
        }

        return $query
            ->get([
                'p.id',
                'p.nome',
                'p.descricao',
                'p.valor',
                'p.duracao_dias',
                'p.checkins_semanais',
                'm.id as modalidade_id',
                'm.nome as modalidade_nome',
            ])
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    /**
     * @param  list<int>  $planoIds
     * @return list<array<string, mixed>>
     */
    public function listarCiclosAtivosPorPlanos(array $planoIds): array
    {
        if ($planoIds === []) {
            return [];
        }

        return DB::table('plano_ciclos as pc')
            ->join('assinatura_frequencias as af', 'af.id', '=', 'pc.assinatura_frequencia_id')
            ->whereIn('pc.plano_id', $planoIds)
            ->where('pc.ativo', 1)
            ->orderBy('af.ordem')
            ->get([
                'pc.id',
                'pc.plano_id',
                'af.nome',
                'af.codigo',
                'pc.meses',
                'pc.valor',
                'pc.valor_mensal_equivalente',
                'pc.desconto_percentual',
                'pc.permite_recorrencia',
                'pc.permite_reposicao',
                'af.ordem',
            ])
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    public function findPlanoDetalhe(int $planoId, int $tenantId): ?array
    {
        $row = DB::table('planos as p')
            ->leftJoin('modalidades as m', 'p.modalidade_id', '=', 'm.id')
            ->where('p.id', $planoId)
            ->where('p.tenant_id', $tenantId)
            ->first([
                'p.id',
                'p.nome',
                'p.descricao',
                'p.valor',
                'p.duracao_dias',
                'p.checkins_semanais',
                'p.ativo',
                'm.id as modalidade_id',
                'm.nome as modalidade_nome',
                'm.cor as modalidade_cor',
            ]);

        return $row ? (array) $row : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listarCiclosDoPlano(int $planoId): array
    {
        return DB::table('plano_ciclos as pc')
            ->join('assinatura_frequencias as af', 'af.id', '=', 'pc.assinatura_frequencia_id')
            ->where('pc.plano_id', $planoId)
            ->where('pc.ativo', 1)
            ->orderBy('af.ordem')
            ->get([
                'pc.id',
                'af.nome',
                'af.codigo',
                'pc.meses',
                'pc.valor',
                'pc.valor_mensal_equivalente',
                'pc.desconto_percentual',
                'pc.permite_recorrencia',
                'pc.permite_reposicao',
                'af.ordem',
            ])
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    public function findMatriculaAtivaNoPlano(int $userId, int $planoId, int $tenantId): ?array
    {
        $row = DB::table('matriculas as m')
            ->join('alunos as a', 'a.id', '=', 'm.aluno_id')
            ->join('status_matricula as sm', 'sm.id', '=', 'm.status_id')
            ->where('a.usuario_id', $userId)
            ->where('m.plano_id', $planoId)
            ->where('m.tenant_id', $tenantId)
            ->whereIn('sm.codigo', ['ativa', 'pendente'])
            ->orderByDesc('m.created_at')
            ->select([
                'm.id',
                'm.data_inicio',
                'm.data_vencimento',
                'm.proxima_data_vencimento',
                'm.plano_ciclo_id',
                'm.valor',
                'sm.nome as status',
                'sm.codigo as status_codigo',
                DB::raw("(
                    SELECT a2.status_gateway
                    FROM assinaturas a2
                    WHERE a2.matricula_id = m.id
                      AND a2.tenant_id = m.tenant_id
                    ORDER BY a2.id DESC
                    LIMIT 1
                ) as assinatura_status_gateway"),
            ])
            ->first();

        return $row ? (array) $row : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listarMatriculasUsuario(int $userId, int $tenantId, bool $apenasAtivas): array
    {
        $query = DB::table('matriculas as mat')
            ->join('planos as p', 'mat.plano_id', '=', 'p.id')
            ->join('alunos as a', 'a.id', '=', 'mat.aluno_id')
            ->leftJoin('status_matricula as sm', 'sm.id', '=', 'mat.status_id')
            ->leftJoin('motivo_matricula as mm', 'mm.id', '=', 'mat.motivo_id')
            ->where('a.usuario_id', $userId)
            ->where('mat.tenant_id', $tenantId);

        if ($apenasAtivas) {
            $query->where('mat.status_id', function ($q) {
                $q->select('id')
                    ->from('status_matricula')
                    ->where('nome', 'ativa')
                    ->limit(1);
            });
        }

        return $query
            ->orderByDesc('mat.data_vencimento')
            ->get([
                'mat.id',
                'mat.aluno_id',
                'mat.plano_id',
                'mat.data_matricula',
                'mat.data_inicio',
                'mat.data_vencimento',
                'mat.valor',
                'sm.nome as status',
                'mm.nome as motivo',
                'p.id as plano_id_ref',
                'p.tenant_id',
                'p.modalidade_id',
                'p.nome as plano_nome',
                'p.descricao',
                'p.valor as plano_valor',
                'p.duracao_dias',
                'p.checkins_semanais',
                'p.ativo',
                'mat.created_at',
                'mat.updated_at',
            ])
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function mapModalidadesPorId(): array
    {
        $map = [];
        foreach (DB::table('modalidades')->get(['id', 'nome', 'cor']) as $mod) {
            $map[(int) $mod->id] = (array) $mod;
        }

        return $map;
    }
}
