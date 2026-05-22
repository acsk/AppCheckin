<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

class TurmaRepository
{
    public function findDiaByData(string $data): ?array
    {
        $row = DB::table('dias')->where('data', $data)->first();

        return $row ? (array) $row : null;
    }

    /**
     * @return list<object>
     */
    public function listarTurmasAtivasPorDia(int $diaId, int $tenantId): array
    {
        return DB::table('turmas as t')
            ->join('dias as d', 't.dia_id', '=', 'd.id')
            ->join('professores as p', 't.professor_id', '=', 'p.id')
            ->join('modalidades as m', 't.modalidade_id', '=', 'm.id')
            ->where('d.id', $diaId)
            ->where('t.ativo', 1)
            ->where('t.tenant_id', $tenantId)
            ->orderBy('t.horario_inicio')
            ->select([
                't.id',
                't.tenant_id',
                't.professor_id',
                't.modalidade_id',
                't.dia_id',
                't.horario_inicio',
                't.horario_fim',
                't.nome',
                't.limite_alunos',
                't.ativo',
                't.tolerancia_minutos',
                't.tolerancia_antes_minutos',
                't.created_at',
                't.updated_at',
                'p.nome as professor_nome',
                'm.nome as modalidade_nome',
                'm.icone as modalidade_icone',
                'm.cor as modalidade_cor',
                'd.data as dia_data',
            ])
            ->get()
            ->all();
    }

    public function contarCheckinsNaTurma(int $turmaId, int $tenantId): int
    {
        return (int) DB::table('checkins')
            ->where('turma_id', $turmaId)
            ->where('tenant_id', $tenantId)
            ->distinct()
            ->count('aluno_id');
    }

    public function findById(int $id, ?int $tenantId = null): ?array
    {
        $query = DB::table('turmas as t')
            ->leftJoin('professores as p', 't.professor_id', '=', 'p.id')
            ->leftJoin('modalidades as m', 't.modalidade_id', '=', 'm.id')
            ->leftJoin('dias as d', 't.dia_id', '=', 'd.id')
            ->where('t.id', $id)
            ->select([
                't.*',
                'p.nome as professor_nome',
                'm.nome as modalidade_nome',
                'm.icone as modalidade_icone',
                'm.cor as modalidade_cor',
                'd.data as dia_data',
            ]);

        if ($tenantId !== null) {
            $query->where('t.tenant_id', $tenantId);
        }

        $row = $query->first();

        return $row ? (array) $row : null;
    }

    public function contarAlunosInscritos(int $turmaId): int
    {
        return (int) DB::table('inscricoes_turmas')
            ->where('turma_id', $turmaId)
            ->where('ativo', 1)
            ->where('status', 'ativa')
            ->count();
    }

    public function findCheckinComTurma(int $checkinId, int $tenantId): ?array
    {
        $row = DB::table('checkins as c')
            ->join('alunos as a', 'c.aluno_id', '=', 'a.id')
            ->join('turmas as t', 'c.turma_id', '=', 't.id')
            ->join('dias as d', 't.dia_id', '=', 'd.id')
            ->where('c.id', $checkinId)
            ->where('t.tenant_id', $tenantId)
            ->select([
                'c.id',
                'c.aluno_id',
                'c.turma_id',
                'a.usuario_id',
                'd.data as dia_data',
            ])
            ->first();

        return $row ? (array) $row : null;
    }

    public function deleteCheckin(int $checkinId): void
    {
        DB::table('checkins')->where('id', $checkinId)->delete();
    }
}
