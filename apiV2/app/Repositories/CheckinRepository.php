<?php

namespace App\Repositories;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class CheckinRepository
{
    public function createEmTurma(int $alunoId, int $turmaId, int $tenantId): ?int
    {
        try {
            return (int) DB::table('checkins')->insertGetId([
                'aluno_id' => $alunoId,
                'turma_id' => $turmaId,
                'tenant_id' => $tenantId,
                'registrado_por_admin' => 0,
            ]);
        } catch (QueryException $e) {
            if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                return null;
            }

            throw $e;
        }
    }

    public function usuarioTemCheckinNaTurma(int $usuarioId, int $turmaId): bool
    {
        return DB::table('checkins as c')
            ->join('alunos as a', 'a.id', '=', 'c.aluno_id')
            ->where('a.usuario_id', $usuarioId)
            ->where('c.turma_id', $turmaId)
            ->exists();
    }

    /**
     * @return array{total: int, ultimo_checkin_id: ?int}
     */
    public function usuarioTemCheckinNoDiaNaModalidade(int $usuarioId, string $data, ?int $modalidadeId): array
    {
        $query = DB::table('checkins as c')
            ->join('alunos as a', 'a.id', '=', 'c.aluno_id')
            ->join('turmas as t', 'c.turma_id', '=', 't.id')
            ->join('dias as d', 't.dia_id', '=', 'd.id')
            ->where('a.usuario_id', $usuarioId)
            ->whereRaw('DATE(d.data) = ?', [$data]);

        if ($modalidadeId !== null) {
            $query->where('t.modalidade_id', $modalidadeId);
        }

        $row = $query
            ->selectRaw('COUNT(*) as total, MAX(c.id) as ultimo_checkin_id')
            ->first();

        return [
            'total' => (int) ($row->total ?? 0),
            'ultimo_checkin_id' => ! empty($row->ultimo_checkin_id) ? (int) $row->ultimo_checkin_id : null,
        ];
    }

    public function contarCheckinsNaSemana(int $usuarioId, ?int $modalidadeId = null): int
    {
        $query = DB::table('checkins as c')
            ->join('alunos as a', 'a.id', '=', 'c.aluno_id')
            ->where('a.usuario_id', $usuarioId)
            ->whereRaw('YEARWEEK(COALESCE(c.data_checkin_date, DATE(c.created_at)), 1) = YEARWEEK(CURDATE(), 1)')
            ->where(function ($q) {
                $q->whereNull('c.presente')->orWhere('c.presente', 1);
            });

        if ($modalidadeId) {
            $query->join('turmas as t', 'c.turma_id', '=', 't.id')
                ->where('t.modalidade_id', $modalidadeId);
        }

        return (int) $query->count();
    }

    public function contarCheckinsNoMes(int $usuarioId, ?int $modalidadeId = null): int
    {
        $query = DB::table('checkins as c')
            ->join('alunos as a', 'a.id', '=', 'c.aluno_id')
            ->join('turmas as t', 't.id', '=', 'c.turma_id')
            ->join('dias as d', 'd.id', '=', 't.dia_id')
            ->where('a.usuario_id', $usuarioId)
            ->whereRaw('YEAR(d.data) = YEAR(CURDATE())')
            ->whereRaw('MONTH(d.data) = MONTH(CURDATE())')
            ->where(function ($q) {
                $q->whereNull('c.presente')->orWhere('c.presente', 1);
            });

        if ($modalidadeId) {
            $query->where('t.modalidade_id', $modalidadeId);
        }

        return (int) $query->count();
    }

    /**
     * @return array{
     *   limite: int,
     *   plano_nome: string,
     *   tem_plano: bool,
     *   modalidade_id: ?int,
     *   permite_reposicao: bool,
     *   limite_mensal: ?int
     * }
     */
    public function obterLimiteCheckinsPlano(int $usuarioId, int $tenantId, ?int $modalidadeId = null): array
    {
        $query = DB::table('matriculas as m')
            ->join('planos as p', 'm.plano_id', '=', 'p.id')
            ->leftJoin('plano_ciclos as pc', function ($join) {
                $join->on('pc.id', '=', 'm.plano_ciclo_id')
                    ->on('pc.tenant_id', '=', 'm.tenant_id');
            })
            ->join('alunos as a', 'a.id', '=', 'm.aluno_id')
            ->join('status_matricula as sm', 'sm.id', '=', 'm.status_id')
            ->where('a.usuario_id', $usuarioId)
            ->where('m.tenant_id', $tenantId)
            ->where('sm.codigo', 'ativa')
            ->whereRaw('COALESCE(m.proxima_data_vencimento, m.data_vencimento) >= CURDATE()')
            ->select([
                'p.checkins_semanais',
                'p.nome as plano_nome',
                'p.modalidade_id',
                DB::raw("CASE
                    WHEN m.plano_ciclo_id IS NOT NULL THEN COALESCE(pc.permite_reposicao, 0)
                    ELSE COALESCE((
                        SELECT MAX(pc2.permite_reposicao)
                        FROM plano_ciclos pc2
                        WHERE pc2.plano_id = p.id
                          AND pc2.tenant_id = m.tenant_id
                          AND pc2.ativo = 1
                    ), 0)
                END as permite_reposicao"),
            ])
            ->orderByDesc('m.proxima_data_vencimento')
            ->limit(1);

        if ($modalidadeId) {
            $query->where('p.modalidade_id', $modalidadeId);
        }

        $result = $query->first();

        if (! $result) {
            return [
                'limite' => 0,
                'plano_nome' => 'Sem plano',
                'tem_plano' => false,
                'modalidade_id' => null,
                'permite_reposicao' => false,
                'limite_mensal' => null,
            ];
        }

        $permiteReposicao = (bool) $result->permite_reposicao;
        $limite = (int) $result->checkins_semanais;

        return [
            'limite' => $limite,
            'plano_nome' => (string) $result->plano_nome,
            'tem_plano' => true,
            'modalidade_id' => (int) $result->modalidade_id,
            'permite_reposicao' => $permiteReposicao,
            'limite_mensal' => $permiteReposicao ? $limite * 4 : null,
        ];
    }
}
