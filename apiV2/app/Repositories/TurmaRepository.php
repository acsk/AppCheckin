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

    /** Contagem por turma_id apenas (paridade Slim detalheTurma). */
    public function contarCheckinsPorTurmaId(int $turmaId): int
    {
        return (int) DB::table('checkins')->where('turma_id', $turmaId)->count();
    }

    public function findDetalheMobile(int $turmaId, int $tenantId): ?array
    {
        $row = DB::table('turmas as t')
            ->join('professores as p', 't.professor_id', '=', 'p.id')
            ->leftJoin('modalidades as m', 't.modalidade_id', '=', 'm.id')
            ->leftJoin('dias as d', 't.dia_id', '=', 'd.id')
            ->where('t.id', $turmaId)
            ->where('t.tenant_id', $tenantId)
            ->first([
                't.id',
                't.nome',
                't.professor_id',
                't.modalidade_id',
                't.limite_alunos',
                't.horario_inicio',
                't.horario_fim',
                't.tolerancia_minutos',
                't.tolerancia_antes_minutos',
                't.ativo',
                'p.nome as professor_nome',
                'p.email as professor_email',
                'm.nome as modalidade_nome',
                'm.icone as modalidade_icone',
                'm.cor as modalidade_cor',
                'd.data as dia_data',
            ]);

        return $row ? (array) $row : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listarAlunosAgregadosCheckinTurma(int $turmaId): array
    {
        return DB::table('alunos as a')
            ->join('usuarios as u', 'u.id', '=', 'a.usuario_id')
            ->join('checkins as c', 'a.id', '=', 'c.aluno_id')
            ->where('c.turma_id', $turmaId)
            ->groupBy('a.id', 'a.nome', 'a.data_nascimento', 'u.email', 'a.foto_caminho')
            ->orderBy('u.nome')
            ->get([
                'a.id',
                'a.nome',
                'a.data_nascimento',
                'u.email',
                'a.foto_caminho',
                DB::raw('COUNT(c.id) as checkins_do_aluno'),
            ])
            ->map(static fn ($row) => (array) $row)
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listarCheckinsRecentesMobile(int $turmaId, int $limit = 50): array
    {
        return DB::table('checkins as c')
            ->join('alunos as a', 'c.aluno_id', '=', 'a.id')
            ->where('c.turma_id', $turmaId)
            ->orderByDesc('c.created_at')
            ->limit($limit)
            ->get([
                'c.id as checkin_id',
                'c.aluno_id',
                'a.nome as usuario_nome',
                'a.data_nascimento',
                'c.created_at as data_checkin',
                DB::raw("TIME_FORMAT(c.created_at, '%H:%i:%s') as hora_checkin"),
                DB::raw("DATE_FORMAT(c.created_at, '%d/%m/%Y') as data_checkin_formatada"),
                'c.presente',
                'c.presenca_confirmada_em',
                'c.presenca_confirmada_por',
            ])
            ->map(static fn ($row) => (array) $row)
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listarParticipantesMobile(int $turmaId): array
    {
        return DB::table('checkins as c')
            ->join('alunos as a', 'c.aluno_id', '=', 'a.id')
            ->join('usuarios as u', 'a.usuario_id', '=', 'u.id')
            ->where('c.turma_id', $turmaId)
            ->orderByDesc('c.created_at')
            ->get([
                'c.id as checkin_id',
                'c.aluno_id',
                'a.nome as usuario_nome',
                'a.data_nascimento',
                'u.email',
                'c.created_at as data_checkin',
                DB::raw("TIME_FORMAT(c.created_at, '%H:%i:%s') as hora_checkin"),
                DB::raw("DATE_FORMAT(c.created_at, '%d/%m/%Y') as data_checkin_formatada"),
            ])
            ->map(static fn ($row) => (array) $row)
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listarTurmasMobileAtivas(int $tenantId): array
    {
        $sql = 'SELECT t.id,
                t.nome,
                p.id as professor_id,
                p.nome as professor,
                m.id as modalidade_id,
                m.nome as modalidade,
                m.icone,
                m.cor,
                t.horario_inicio,
                t.horario_fim,
                d.data as dia_aula,
                d.id as dia_id,
                t.limite_alunos,
                (SELECT COUNT(*) FROM checkins c WHERE c.turma_id = t.id) as total_checkins,
                (SELECT COUNT(*) FROM inscricoes_turmas it
                 WHERE it.turma_id = t.id AND it.ativo = 1 AND it.status = \'ativa\') as alunos_inscritos,
                (t.limite_alunos - COALESCE((SELECT COUNT(*) FROM inscricoes_turmas it
                 WHERE it.turma_id = t.id AND it.ativo = 1 AND it.status = \'ativa\'), 0)) as vagas_disponiveis,
                t.ativo,
                t.created_at,
                t.updated_at
                FROM turmas t
                INNER JOIN professores p ON t.professor_id = p.id
                INNER JOIN modalidades m ON t.modalidade_id = m.id
                INNER JOIN dias d ON t.dia_id = d.id
                WHERE t.tenant_id = ? AND t.ativo = 1
                ORDER BY d.data ASC, t.horario_inicio ASC';

        return $this->rows($sql, [$tenantId]);
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

    /**
     * Espelha App\Models\Turma::listarPorDia da Slim (inclui alunos_count).
     *
     * @return list<array<string, mixed>>
     */
    public function listarPorDia(int $tenantId, int $diaId, bool $apenasAtivas = true): array
    {
        $sql = 'SELECT t.*,
                p.nome as professor_nome,
                p.id as professor_id,
                m.nome as modalidade_nome,
                m.icone as modalidade_icone,
                m.cor as modalidade_cor,
                d.data as dia_data,
                '.self::SUBQUERY_ALUNOS_COUNT.'
                FROM turmas t
                LEFT JOIN professores p ON t.professor_id = p.id
                LEFT JOIN modalidades m ON t.modalidade_id = m.id
                LEFT JOIN dias d ON t.dia_id = d.id
                WHERE t.tenant_id = ? AND t.dia_id = ?';

        if ($apenasAtivas) {
            $sql .= ' AND t.ativo = 1';
        }

        $sql .= ' ORDER BY t.horario_inicio ASC';

        return $this->rows($sql, [$tenantId, $diaId]);
    }

    /**
     * Espelha App\Models\Turma::findById da Slim (inclui alunos_count).
     */
    public function buscarComContagem(int $id, ?int $tenantId = null): ?array
    {
        $sql = 'SELECT t.*,
                p.nome as professor_nome,
                m.nome as modalidade_nome,
                m.icone as modalidade_icone,
                m.cor as modalidade_cor,
                d.data as dia_data,
                '.self::SUBQUERY_ALUNOS_COUNT.'
                FROM turmas t
                LEFT JOIN professores p ON t.professor_id = p.id
                LEFT JOIN modalidades m ON t.modalidade_id = m.id
                LEFT JOIN dias d ON t.dia_id = d.id
                WHERE t.id = ?';

        $params = [$id];

        if ($tenantId) {
            $sql .= ' AND t.tenant_id = ?';
            $params[] = $tenantId;
        }

        $row = DB::selectOne($sql, $params);

        return $row ? (array) $row : null;
    }

    /**
     * Turmas ativas de vários dias, com dia_data e DAYOFWEEK (usado em replicar-semana).
     *
     * @param  list<int>  $diaIds
     * @return list<array<string, mixed>>
     */
    public function listarAtivasPorDiaIds(int $tenantId, array $diaIds): array
    {
        if ($diaIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($diaIds), '?'));

        $sql = "SELECT t.*, d.data as dia_data, DAYOFWEEK(d.data) as dia_semana
                FROM turmas t
                JOIN dias d ON t.dia_id = d.id
                WHERE t.tenant_id = ? AND t.dia_id IN ($placeholders) AND t.ativo = 1
                ORDER BY d.data, t.horario_inicio";

        return $this->rows($sql, array_merge([$tenantId], $diaIds));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function criar(array $data): int
    {
        return (int) DB::table('turmas')->insertGetId([
            'tenant_id' => $data['tenant_id'],
            'professor_id' => $data['professor_id'],
            'modalidade_id' => $data['modalidade_id'],
            'dia_id' => $data['dia_id'],
            'horario_inicio' => self::normalizarHorario((string) $data['horario_inicio']),
            'horario_fim' => self::normalizarHorario((string) $data['horario_fim']),
            'nome' => $data['nome'],
            'limite_alunos' => $data['limite_alunos'] ?? 20,
            'tolerancia_minutos' => $data['tolerancia_minutos'] ?? 10,
            'tolerancia_antes_minutos' => $data['tolerancia_antes_minutos'] ?? 480,
            'ativo' => $data['ativo'] ?? 1,
        ]);
    }

    /**
     * Atualiza apenas os campos presentes no payload (espelha o UPDATE dinâmico da Slim).
     *
     * @param  array<string, mixed>  $data
     */
    public function atualizar(int $id, array $data): void
    {
        $permitidos = [
            'professor_id', 'modalidade_id', 'dia_id', 'horario_inicio', 'horario_fim',
            'nome', 'limite_alunos', 'tolerancia_minutos', 'tolerancia_antes_minutos', 'ativo',
        ];

        $updates = [];
        foreach ($permitidos as $campo) {
            if (! isset($data[$campo])) {
                continue;
            }

            $updates[$campo] = ($campo === 'horario_inicio' || $campo === 'horario_fim')
                ? self::normalizarHorario((string) $data[$campo])
                : $data[$campo];
        }

        if ($updates === []) {
            return;
        }

        $updates['updated_at'] = DB::raw('CURRENT_TIMESTAMP');

        DB::table('turmas')->where('id', $id)->update($updates);
    }

    public function desativar(int $id): void
    {
        DB::table('turmas')->where('id', $id)->update([
            'ativo' => 0,
            'updated_at' => DB::raw('CURRENT_TIMESTAMP'),
        ]);
    }

    public function deletarPermanente(int $id): void
    {
        DB::table('turmas')->where('id', $id)->delete();
    }

    /**
     * Sobreposição de horário no mesmo dia (opcionalmente restrita a um professor).
     *
     * @return list<array<string, mixed>>
     */
    public function verificarHorarioOcupado(
        int $tenantId,
        int $diaId,
        string $horarioInicio,
        string $horarioFim,
        ?int $turmaIdExcluir = null,
        ?int $professorId = null,
    ): array {
        $sql = 'SELECT t.id, t.nome, t.professor_id, t.horario_inicio, t.horario_fim, t.modalidade_id
                FROM turmas t
                WHERE t.tenant_id = ?
                AND t.dia_id = ?
                AND t.ativo = 1
                AND (t.horario_inicio < ? AND t.horario_fim > ?)';

        $params = [
            $tenantId,
            $diaId,
            self::normalizarHorario($horarioFim),
            self::normalizarHorario($horarioInicio),
        ];

        if ($professorId !== null) {
            $sql .= ' AND t.professor_id = ?';
            $params[] = $professorId;
        }

        if ($turmaIdExcluir !== null) {
            $sql .= ' AND t.id != ?';
            $params[] = $turmaIdExcluir;
        }

        return $this->rows($sql, $params);
    }

    /**
     * Turma com mesmo professor/modalidade/horário em outro dia (usado no desativar em lote).
     */
    public function buscarSimilarEmOutroDia(
        int $tenantId,
        int $diaId,
        int $professorId,
        int $modalidadeId,
        string $horarioInicio,
        string $horarioFim,
    ): ?array {
        $row = DB::selectOne(
            'SELECT id FROM turmas
             WHERE tenant_id = ?
             AND dia_id = ?
             AND professor_id = ?
             AND modalidade_id = ?
             AND horario_inicio = ?
             AND horario_fim = ?
             LIMIT 1',
            [$tenantId, $diaId, $professorId, $modalidadeId, $horarioInicio, $horarioFim],
        );

        return $row ? (array) $row : null;
    }

    public function contarAlunos(int $turmaId): int
    {
        return (int) DB::table('inscricoes_turmas')
            ->where('turma_id', $turmaId)
            ->where('ativo', 1)
            ->where('status', 'ativa')
            ->count();
    }

    public function temVagas(int $turmaId): bool
    {
        $turma = $this->buscarComContagem($turmaId);
        if (! $turma) {
            return false;
        }

        return $this->contarAlunos($turmaId) < (int) $turma['limite_alunos'];
    }

    /**
     * Espelha App\Models\Turma::listarPorProfessor da Slim.
     *
     * @return list<array<string, mixed>>
     */
    public function listarPorProfessor(int $professorId, int $tenantId, bool $apenasAtivas = false): array
    {
        $sql = 'SELECT t.*,
                m.nome as modalidade_nome,
                m.icone as modalidade_icone,
                m.cor as modalidade_cor,
                d.data as dia_data,
                '.self::SUBQUERY_ALUNOS_COUNT.'
                FROM turmas t
                LEFT JOIN modalidades m ON t.modalidade_id = m.id
                LEFT JOIN dias d ON t.dia_id = d.id
                WHERE t.professor_id = ? AND t.tenant_id = ?';

        if ($apenasAtivas) {
            $sql .= ' AND t.ativo = 1';
        }

        $sql .= ' ORDER BY d.data ASC, t.horario_inicio ASC';

        return $this->rows($sql, [$professorId, $tenantId]);
    }

    public function deletar(int $id): void
    {
        $this->desativar($id);
    }

    /**
     * Espelha App\Models\Professor::pertenceAoTenant (papel_id = 2 ativo no tenant).
     */
    public function professorPertenceAoTenant(int $professorId, int $tenantId): bool
    {
        return DB::table('professores as p')
            ->join('tenant_usuario_papel as tup', function ($join) use ($tenantId) {
                $join->on('tup.usuario_id', '=', 'p.usuario_id')
                    ->where('tup.tenant_id', '=', $tenantId)
                    ->where('tup.papel_id', '=', 2)
                    ->where('tup.ativo', '=', 1);
            })
            ->where('p.id', $professorId)
            ->exists();
    }

    /**
     * Resumo das turmas de um dia (usado ao remover todos os horários do dia).
     *
     * @return list<array<string, mixed>>
     */
    public function listarResumoPorDia(int $diaId, int $tenantId): array
    {
        return $this->rows(
            'SELECT t.id, t.nome, t.horario_inicio, t.horario_fim, t.limite_alunos
             FROM turmas t
             WHERE t.dia_id = ? AND t.tenant_id = ?',
            [$diaId, $tenantId],
        );
    }

    /**
     * @param  list<int>  $turmaIds
     */
    public function deletarCheckinsDasTurmas(array $turmaIds): int
    {
        if ($turmaIds === []) {
            return 0;
        }

        return DB::table('checkins')->whereIn('turma_id', $turmaIds)->delete();
    }

    public function deletarTurmasDoDia(int $diaId, int $tenantId): int
    {
        return DB::table('turmas')
            ->where('dia_id', $diaId)
            ->where('tenant_id', $tenantId)
            ->delete();
    }

    /**
     * Aceita "HH:MM" ou "HH:MM:SS" e devolve sempre "HH:MM:SS".
     */
    public static function normalizarHorario(string $horario): string
    {
        if (strlen($horario) === 5 && substr_count($horario, ':') === 1) {
            return $horario.':00';
        }

        return $horario;
    }

    /**
     * @param  list<mixed>  $bindings
     * @return list<array<string, mixed>>
     */
    private function rows(string $sql, array $bindings): array
    {
        return array_map(
            static fn ($row) => (array) $row,
            DB::select($sql, $bindings),
        );
    }

    private const SUBQUERY_ALUNOS_COUNT = "(SELECT COUNT(*) FROM inscricoes_turmas it WHERE it.turma_id = t.id AND it.ativo = 1 AND it.status = 'ativa') as alunos_count";
}
