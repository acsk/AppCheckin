<?php

namespace App\Repositories;

use App\Support\AcademyDateTime;
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

    /**
     * @return list<array<string, mixed>>
     */
    public function rankingUsuarioPorModalidade(int $userId, int $tenantId): array
    {
        $modalidades = DB::table('checkins as c')
            ->join('alunos as a', 'c.aluno_id', '=', 'a.id')
            ->join('turmas as t', function ($join) {
                $join->on('c.turma_id', '=', 't.id')
                    ->on('t.tenant_id', '=', 'c.tenant_id');
            })
            ->join('modalidades as m', 't.modalidade_id', '=', 'm.id')
            ->where('a.usuario_id', $userId)
            ->where('c.tenant_id', $tenantId)
            ->whereRaw('MONTH(c.data_checkin_date) = MONTH(CURRENT_DATE())')
            ->whereRaw('YEAR(c.data_checkin_date) = YEAR(CURRENT_DATE())')
            ->distinct()
            ->get([
                't.modalidade_id',
                'm.nome as modalidade_nome',
                'm.icone as modalidade_icone',
                'm.cor as modalidade_cor',
            ]);

        $alunoId = DB::table('alunos')->where('usuario_id', $userId)->value('id');
        $rankings = [];

        foreach ($modalidades as $modalidade) {
            $modalidadeId = (int) $modalidade->modalidade_id;

            $posicao = DB::selectOne(
                'SELECT aluno_id, total_checkins, posicao FROM (
                    SELECT c.aluno_id, COUNT(c.id) as total_checkins,
                           RANK() OVER (ORDER BY COUNT(c.id) DESC) as posicao
                    FROM checkins c
                    INNER JOIN turmas t ON c.turma_id = t.id AND t.tenant_id = c.tenant_id
                    WHERE c.tenant_id = ?
                      AND t.modalidade_id = ?
                      AND MONTH(COALESCE(c.data_checkin_date, DATE(c.created_at))) = MONTH(CURRENT_DATE())
                      AND YEAR(COALESCE(c.data_checkin_date, DATE(c.created_at))) = YEAR(CURRENT_DATE())
                      AND (c.presente IS NULL OR c.presente = 1)
                    GROUP BY c.aluno_id
                ) ranking WHERE aluno_id = ?',
                [$tenantId, $modalidadeId, $alunoId],
            );

            $totalParticipantes = (int) DB::table('checkins as c')
                ->join('turmas as t', function ($join) {
                    $join->on('c.turma_id', '=', 't.id')
                        ->on('t.tenant_id', '=', 'c.tenant_id');
                })
                ->where('c.tenant_id', $tenantId)
                ->where('t.modalidade_id', $modalidadeId)
                ->whereRaw('MONTH(COALESCE(c.data_checkin_date, DATE(c.created_at))) = MONTH(CURRENT_DATE())')
                ->whereRaw('YEAR(COALESCE(c.data_checkin_date, DATE(c.created_at))) = YEAR(CURRENT_DATE())')
                ->where(function ($q) {
                    $q->whereNull('c.presente')->orWhere('c.presente', 1);
                })
                ->distinct('c.aluno_id')
                ->count('c.aluno_id');

            if ($posicao) {
                $rankings[] = [
                    'modalidade_id' => $modalidadeId,
                    'modalidade_nome' => $modalidade->modalidade_nome,
                    'modalidade_icone' => $modalidade->modalidade_icone,
                    'modalidade_cor' => $modalidade->modalidade_cor,
                    'posicao' => (int) $posicao->posicao,
                    'total_checkins' => (int) $posicao->total_checkins,
                    'total_participantes' => $totalParticipantes,
                ];
            }
        }

        usort($rankings, static fn ($a, $b) => $a['posicao'] <=> $b['posicao']);

        return $rankings;
    }

    /**
     * @return array{checkins: list<array<string, mixed>>, total: int}
     */
    public function historicoCheckins(int $userId, int $limit, int $offset): array
    {
        $base = $this->historicoCheckinsBaseQuery($userId);

        $checkins = (clone $base)
            ->orderByDesc('d.data')
            ->orderByDesc('t.horario_inicio')
            ->offset($offset)
            ->limit($limit)
            ->get([
                'c.id',
                'c.data_checkin',
                'c.created_at',
                'd.data',
                't.horario_inicio as hora',
                't.nome as turma_nome',
            ])
            ->map(fn ($row) => (array) $row)
            ->all();

        $total = (int) (clone $base)->count('c.id');

        return ['checkins' => $checkins, 'total' => $total];
    }

    private function historicoCheckinsBaseQuery(int $userId): \Illuminate\Database\Query\Builder
    {
        return DB::table('checkins as c')
            ->join('alunos as a', 'a.id', '=', 'c.aluno_id')
            ->join('turmas as t', 'c.turma_id', '=', 't.id')
            ->join('dias as d', 't.dia_id', '=', 'd.id')
            ->where('a.usuario_id', $userId)
            ->where(function ($q) {
                $q->whereNull('c.presente')->orWhere('c.presente', 1);
            });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function checkinsSemanaPorModalidade(
        int $userId,
        int $tenantId,
        string $semanaInicio,
        string $semanaFim,
    ): array {
        return DB::table('checkins as c')
            ->join('alunos as a', 'a.id', '=', 'c.aluno_id')
            ->join('turmas as t', 'c.turma_id', '=', 't.id')
            ->join('dias as d', 't.dia_id', '=', 'd.id')
            ->leftJoin('modalidades as m', 't.modalidade_id', '=', 'm.id')
            ->where('a.usuario_id', $userId)
            ->where('t.tenant_id', $tenantId)
            ->where('c.tenant_id', $tenantId)
            ->where(function ($q) {
                $q->whereNull('c.presente')->orWhere('c.presente', 1);
            })
            ->whereBetween('d.data', [$semanaInicio, $semanaFim])
            ->orderBy('d.data')
            ->get([
                'd.data',
                'm.id as modalidade_id',
                'm.nome as modalidade_nome',
                'm.cor as modalidade_cor',
                'm.icone as modalidade_icone',
            ])
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    public function contarCheckinsTenantMes(int $tenantId, ?int $mes = null, ?int $ano = null): int
    {
        ['mes' => $mes, 'ano' => $ano] = $this->resolveMonthYear($mes, $ano);

        return (int) DB::table('checkins as c')
            ->where('c.tenant_id', $tenantId)
            ->whereRaw('MONTH(COALESCE(c.data_checkin_date, DATE(c.created_at))) = ?', [$mes])
            ->whereRaw('YEAR(COALESCE(c.data_checkin_date, DATE(c.created_at))) = ?', [$ano])
            ->where(function ($q) {
                $q->whereNull('c.presente')->orWhere('c.presente', 1);
            })
            ->count();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function rankingMesAtual(
        int $tenantId,
        int $limite = 3,
        ?int $modalidadeId = null,
        ?int $mes = null,
        ?int $ano = null,
    ): array {
        ['mes' => $mes, 'ano' => $ano] = $this->resolveMonthYear($mes, $ano);

        $query = DB::table('checkins as c')
            ->join('alunos as a', 'c.aluno_id', '=', 'a.id')
            ->join('usuarios as u', 'a.usuario_id', '=', 'u.id')
            ->join('turmas as t', function ($join) {
                $join->on('c.turma_id', '=', 't.id')
                    ->on('t.tenant_id', '=', 'c.tenant_id');
            })
            ->where('c.tenant_id', $tenantId)
            ->whereRaw('MONTH(COALESCE(c.data_checkin_date, DATE(c.created_at))) = ?', [$mes])
            ->whereRaw('YEAR(COALESCE(c.data_checkin_date, DATE(c.created_at))) = ?', [$ano])
            ->where(function ($q) {
                $q->whereNull('c.presente')->orWhere('c.presente', 1);
            });

        if ($modalidadeId) {
            $query->where('t.modalidade_id', $modalidadeId);
        }

        return $query
            ->groupBy('a.id', 'a.nome', 'u.email', 'a.foto_caminho')
            ->orderByDesc(DB::raw('COUNT(c.id)'))
            ->limit($limite)
            ->get([
                'a.id as aluno_id',
                'a.nome',
                'u.email',
                'a.foto_caminho',
                DB::raw('COUNT(c.id) as total_checkins'),
            ])
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    /**
     * @return array{mes: int, ano: int}
     */
    private function resolveMonthYear(?int $mes, ?int $ano): array
    {
        $periodo = AcademyDateTime::currentMonthYear();

        return [
            'mes' => $mes ?? $periodo['mes'],
            'ano' => $ano ?? $periodo['ano'],
        ];
    }
}
