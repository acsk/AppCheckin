<?php

namespace App\Services;

use App\Support\AcademyDateTime;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class AcessoHibridoService
{
    public const LIVRE = 'horario_livre';

    public const FIXA = 'turma_fixa';

    public function aplicarStatus(int $tenantId, int $matriculaId, int $statusId, array $campos = []): int
    {
        return DB::transaction(function () use ($tenantId, $matriculaId, $statusId, $campos) {
            $m = DB::table('matriculas')->where('tenant_id', $tenantId)->where('id', $matriculaId)->first();
            abort_unless($m, 404, 'Matrícula não encontrada.');
            $this->bloquearGrupos($tenantId);
            $m = DB::table('matriculas')->where('tenant_id', $tenantId)->where('id', $matriculaId)->lockForUpdate()->first();
            $codigo = DB::table('status_matricula')->where('id', $statusId)->value('codigo');
            if ($m && $codigo === 'ativa' && ! $this->podeAtivar($tenantId, array_replace((array) $m, $campos))) {
                $statusId = (int) DB::table('status_matricula')->where('codigo', 'aguardando_vaga')->value('id');
                if (! $statusId) {
                    throw new \RuntimeException('Status aguardando_vaga não configurado. Execute a migração de acesso híbrido.');
                }
                Log::warning('Matrícula paga sem vaga para ativação', ['tenant_id' => $tenantId, 'matricula_id' => $matriculaId]);
            }
            DB::table('matriculas')->where('tenant_id', $tenantId)->where('id', $matriculaId)
                ->update(array_replace($campos, ['status_id' => $statusId, 'updated_at' => now()]));

            return $statusId;
        }, 3);
    }

    public function atualizado(): bool
    {
        return Schema::hasColumn('matriculas', 'tipo_acesso');
    }

    public function gradeAtualizada(): bool
    {
        return Schema::hasTable('matricula_grade');
    }

    public function gradeTemporal(): bool
    {
        return Schema::hasColumn('matricula_grade', 'vigencia_inicio');
    }

    public function filtrarPeriodo(Builder $q, string $alias, string $data): Builder
    {
        return $this->gradeTemporal() ? $q->where("$alias.vigencia_inicio", '<=', $data)
            ->where(fn ($p) => $p->whereNull("$alias.vigencia_fim")->orWhere("$alias.vigencia_fim", '>=', $data)) : $q;
    }

    public function bloquearGrupos(int $tenantId): void
    {
        if ($this->atualizado()) {
            DB::table('turmas_fixas')->where('tenant_id', $tenantId)->orderBy('id')->lockForUpdate()->get();
        }
    }

    public function grade(int $tenantId, int $matriculaId, ?string $data = null, bool $incluirFuturas = false): array
    {
        if (! $this->gradeAtualizada()) {
            $m = DB::table('matriculas')->where('tenant_id', $tenantId)->where('id', $matriculaId)->first();
            $t = $m && $m->turma_fixa_id ? DB::table('turmas_fixas')->where('id', $m->turma_fixa_id)->where('tenant_id', $tenantId)->first() : null;

            return $t ? array_map(fn ($dia) => ['turma_fixa_id' => (int) $t->id, 'dia_semana' => (int) $dia,
                'nome' => $t->nome, 'horario_inicio' => $t->horario_inicio, 'horario_fim' => $t->horario_fim,
                'modalidade_id' => (int) $t->modalidade_id], json_decode($t->dias_semana, true)) : [];
        }

        $q = DB::table('matricula_grade as g')->join('turmas_fixas as t', 't.id', '=', 'g.turma_fixa_id')
            ->where('g.tenant_id', $tenantId)->where('g.matricula_id', $matriculaId)->where('t.tenant_id', $tenantId)
            ->orderBy('g.dia_semana')->orderBy('t.horario_inicio');
        $colunas = ['g.turma_fixa_id', 'g.dia_semana', 't.nome', 't.horario_inicio', 't.horario_fim', 't.modalidade_id'];
        if ($this->gradeTemporal()) {
            $colunas = [...$colunas, 'g.vigencia_inicio', 'g.vigencia_fim'];
            if ($incluirFuturas) {
                $q->where(fn ($p) => $p->whereNull('g.vigencia_fim')->orWhere('g.vigencia_fim', '>=', $data ?? AcademyDateTime::today()));
            } else {
                $this->filtrarPeriodo($q, 'g', $data ?? AcademyDateTime::today());
            }
        }

        return $q
            ->when(DB::transactionLevel() > 0, fn ($q) => $q->lockForUpdate())
            ->get($colunas)
            ->map(fn ($g) => (array) $g)->all();
    }

    public function salvarGrade(int $tenantId, int $matriculaId, array $grade): void
    {
        if (! $this->gradeAtualizada()) {
            return;
        }
        if ($this->gradeTemporal()) {
            $hoje = AcademyDateTime::today();
            if (DB::table('matricula_grade')->where('tenant_id', $tenantId)->where('matricula_id', $matriculaId)->where('vigencia_inicio', '>', $hoje)->exists()) {
                throw ValidationException::withMessages(['grade' => 'Esta matrícula tem uma mudança de grade agendada. Confira a alteração na gestão de Turmas antes de editar.']);
            }
            DB::table('matricula_grade')->where('tenant_id', $tenantId)->where('matricula_id', $matriculaId)->where('vigencia_inicio', '>=', $hoje)->delete();
            DB::table('matricula_grade')->where('tenant_id', $tenantId)->where('matricula_id', $matriculaId)
                ->where('vigencia_inicio', '<', $hoje)->where(fn ($q) => $q->whereNull('vigencia_fim')->orWhere('vigencia_fim', '>=', $hoje))
                ->update(['vigencia_fim' => AcademyDateTime::now()->modify('-1 day')->format('Y-m-d')]);
        } else {
            DB::table('matricula_grade')->where('tenant_id', $tenantId)->where('matricula_id', $matriculaId)->delete();
        }
        foreach ($grade as $g) {
            DB::table('matricula_grade')->insert([
                'tenant_id' => $tenantId, 'matricula_id' => $matriculaId,
                'turma_fixa_id' => $g['turma_fixa_id'], 'dia_semana' => $g['dia_semana'],
                'created_at' => now(), 'updated_at' => now(),
                ...($this->gradeTemporal() ? ['vigencia_inicio' => $g['vigencia_inicio'] ?? AcademyDateTime::today()] : []),
            ]);
        }
        DB::table('matriculas')->where('tenant_id', $tenantId)->where('id', $matriculaId)->update(['grade_revisao' => false]);
    }

    public function validarMigracaoAutomatica(int $tenantId, int $matriculaId): void
    {
        if (! $this->atualizado()) {
            return;
        }
        $m = DB::table('matriculas')->where('tenant_id', $tenantId)->where('id', $matriculaId)->lockForUpdate()->first();
        abort_unless($m, 404, 'Matrícula não encontrada.');
        if (($m->tipo_acesso ?? self::LIVRE) === self::FIXA) {
            throw ValidationException::withMessages(['plano_id' => 'A matrícula agora é de turma fixa. A secretaria deve conferir o plano e qualquer pagamento de migração já recebido.']);
        }
    }

    public function validarEscolha(int $tenantId, array $dados, ?array $atual = null, ?string $dataGrade = null, bool $preservarFrequencia = false): array
    {
        $tipo = $dados['tipo_acesso'] ?? $atual['tipo_acesso'] ?? self::LIVRE;
        if (! in_array($tipo, [self::LIVRE, self::FIXA], true)) {
            throw ValidationException::withMessages(['tipo_acesso' => 'Tipo de acesso inválido.']);
        }
        $id = $tipo === self::FIXA ? (int) ($dados['turma_fixa_id'] ?? $atual['turma_fixa_id'] ?? 0) : null;
        $grade = [];
        if ($tipo === self::FIXA) {
            $planoId = (int) ($dados['plano_id'] ?? $atual['plano_id'] ?? 0);
            $plano = DB::table('planos')->where('id', $planoId)->where('tenant_id', $tenantId)->first();
            if (! $plano) {
                throw ValidationException::withMessages(['plano_id' => 'Plano não pertence ao tenant.']);
            }
            $grade = array_key_exists('grade', $dados) ? $dados['grade'] : (! empty($atual['id'])
                ? $this->grade($tenantId, (int) $atual['id'], null, $this->gradeTemporal()) : []);
            if (! $this->gradeAtualizada()) {
                $turma = DB::table('turmas_fixas')->where('id', $id)->where('tenant_id', $tenantId)->first();
                $grade = $turma ? array_map(fn ($d) => ['turma_fixa_id' => $id, 'dia_semana' => $d], json_decode($turma->dias_semana, true)) : [];
            }
            if (! is_array($grade) || ! array_is_list($grade) || count($grade) === 0) {
                throw ValidationException::withMessages(['grade' => 'Selecione ao menos um horário recorrente.']);
            }
            $vistos = [];
            foreach ($grade as $indice => $g) {
                if (! is_array($g) || ! isset($g['turma_fixa_id'], $g['dia_semana'])
                    || filter_var($g['turma_fixa_id'], FILTER_VALIDATE_INT) === false
                    || filter_var($g['dia_semana'], FILTER_VALIDATE_INT) === false) {
                    throw ValidationException::withMessages(['grade' => 'Horário da grade inválido.']);
                }
                $turma = DB::table('turmas_fixas')->where('id', $g['turma_fixa_id'])->where('tenant_id', $tenantId)->where('ativo', 1)->first();
                $key = $g['turma_fixa_id'].':'.$g['dia_semana'];
                if (! $turma || (int) $turma->modalidade_id !== (int) $plano->modalidade_id
                    || ! in_array((int) $g['dia_semana'], json_decode($turma->dias_semana, true))
                    || isset($vistos[$key])) {
                    throw ValidationException::withMessages(['grade' => 'Selecione dias distintos de turmas ativas da modalidade do plano neste tenant.']);
                }
                if ($this->gradeTemporal()) {
                    $vigenciaGrade = $dataGrade ?? max(AcademyDateTime::today(), $turma->vigencia_inicio);
                    if ($turma->deleted_at || $turma->vigencia_inicio > $vigenciaGrade
                        || ($turma->vigencia_fim !== null && ($dataGrade === null || $turma->vigencia_fim < $vigenciaGrade))) {
                        throw ValidationException::withMessages(['grade' => 'A turma não está disponível para os dias selecionados.']);
                    }
                    $grade[$indice]['vigencia_inicio'] = $vigenciaGrade;
                }
                $vistos[$key] = true;
            }
            $alterando = array_key_exists('grade', $dados) || isset($dados['plano_id']) || ! $atual;
            $limite = (int) ($plano->checkins_semanais ?? 0);
            if ($alterando && ! $preservarFrequencia && $limite > 0 && count($grade) !== $limite) {
                throw ValidationException::withMessages(['grade' => "O plano exige exatamente {$limite} horários fixos por semana."]);
            }
            if (! empty($dados['pacote_id'])) {
                throw ValidationException::withMessages(['pacote_id' => 'Turma fixa exige um plano individual.']);
            }
            $id = (int) $grade[0]['turma_fixa_id'];
        }

        return ['tipo_acesso' => $tipo, 'turma_fixa_id' => $id, 'grade' => $grade];
    }

    /** O mesmo mutex é usado na ativação, na edição da turma e em todos os check-ins. */
    public function bloquearAula(array $turma, int $tenantId): void
    {
        $this->bloquearGrupos($tenantId);
        DB::table('turmas')->where('id', $turma['id'])->where('tenant_id', $tenantId)->lockForUpdate()->first();
    }

    private function fixas(int $tenantId, int $grupoId, string $data): Builder
    {
        $q = DB::table('matriculas as m')->join('status_matricula as s', 's.id', '=', 'm.status_id')
            ->where('m.tenant_id', $tenantId)->where('m.tipo_acesso', self::FIXA)
            ->where('s.permite_checkin', 1)->where('s.ativo', 1)
            ->whereDate('m.data_inicio', '<=', $data)
            ->whereRaw('COALESCE(m.proxima_data_vencimento, m.data_vencimento) >= ?', [$data]);

        return $this->gradeAtualizada()
            ? $this->filtrarPeriodo($q->join('matricula_grade as g', 'g.matricula_id', '=', 'm.id')
                ->where('g.tenant_id', $tenantId)->where('g.turma_fixa_id', $grupoId)
                ->where('g.dia_semana', (int) (new \DateTimeImmutable($data))->format('N')), 'g', $data)
            : $q->where('m.turma_fixa_id', $grupoId);
    }

    public function vagas(array $turma, int $tenantId, ?int $excluirMatricula = null): array
    {
        $data = substr((string) ($turma['dia_data'] ?? AcademyDateTime::today()), 0, 10);
        $fixos = empty($turma['turma_fixa_id']) ? [] : $this->fixas($tenantId, (int) $turma['turma_fixa_id'], $data)
            ->when($excluirMatricula, fn ($q) => $q->where('m.id', '<>', $excluirMatricula))
            ->when(DB::transactionLevel() > 0, fn ($q) => $q->lockForUpdate())
            ->distinct()->pluck('m.aluno_id')->map(fn ($id) => (int) $id)->all();
        $checkins = DB::table('checkins')->where('tenant_id', $tenantId)->where('turma_id', $turma['id'])
            ->when(DB::transactionLevel() > 0, fn ($q) => $q->lockForUpdate())
            ->distinct()->pluck('aluno_id')->map(fn ($id) => (int) $id)->all();
        $reposicoes = $this->gradeAtualizada() ? DB::table('aula_reposicoes')->where('tenant_id', $tenantId)->where('turma_id', $turma['id'])
            ->when(DB::transactionLevel() > 0, fn ($q) => $q->lockForUpdate())->pluck('aluno_id')->map(fn ($id) => (int) $id)->all() : [];
        $reposicoes = array_values(array_diff($reposicoes, $fixos));
        $livres = count(array_diff($checkins, $fixos, $reposicoes));
        $saldo = max(0, (int) $turma['limite_alunos'] - count($fixos) - count($reposicoes) - $livres);

        return ['vagas_fixas' => count($fixos), 'reposicoes_confirmadas' => count($reposicoes), 'vagas_livres_ocupadas' => $livres,
            'vagas_livres' => $saldo, 'alunos_fixos' => $fixos, 'alunos_reposicao' => $reposicoes];
    }

    public function matriculaParaAula(int $alunoId, int $tenantId, array $turma, bool $ignorarGrade = false): ?array
    {
        $data = substr((string) $turma['dia_data'], 0, 10);
        $row = DB::table('matriculas as m')->join('status_matricula as s', 's.id', '=', 'm.status_id')
            ->join('planos as p', 'p.id', '=', 'm.plano_id')
            ->where('m.aluno_id', $alunoId)->where('m.tenant_id', $tenantId)->where('p.tenant_id', $tenantId)
            ->where('p.modalidade_id', $turma['modalidade_id'])->where('s.permite_checkin', 1)->where('s.ativo', 1)
            ->whereDate('m.data_inicio', '<=', $data)
            ->whereRaw('COALESCE(m.proxima_data_vencimento, m.data_vencimento) >= ?', [$data])
            ->when(! $ignorarGrade, function ($query) use ($tenantId, $turma, $data) {
                $query->where(function ($q) use ($tenantId, $turma, $data) {
                    $q->where('m.tipo_acesso', self::LIVRE)->orWhere(function ($f) use ($tenantId, $turma, $data) {
                        $f->where('m.tipo_acesso', self::FIXA);
                        if ($this->gradeAtualizada()) {
                            $f->whereExists(fn ($g) => $this->filtrarPeriodo($g->selectRaw('1')->from('matricula_grade as g')
                                ->whereColumn('g.matricula_id', 'm.id')->where('g.tenant_id', $tenantId)
                                ->where('g.turma_fixa_id', $turma['turma_fixa_id'] ?? 0)
                                ->where('g.dia_semana', (int) (new \DateTimeImmutable($data))->format('N')), 'g', $data));
                        } else {
                            $f->where('m.turma_fixa_id', $turma['turma_fixa_id'] ?? 0);
                        }
                    });
                    if ($this->gradeAtualizada()) {
                        $q->orWhereExists(fn ($r) => $r->selectRaw('1')->from('aula_reposicoes as r')
                            ->whereColumn('r.matricula_id', 'm.id')->where('r.tenant_id', $tenantId)->where('r.turma_id', $turma['id']));
                    }
                });
            })
            ->when(DB::transactionLevel() > 0, fn ($q) => $q->lockForUpdate())
            ->orderByRaw("CASE WHEN m.tipo_acesso = 'turma_fixa' THEN 0 ELSE 1 END")
            ->orderByDesc('m.id')->first(['m.*']);

        if (! $row) {
            return null;
        }

        return (array) $row + ['reposicao' => $this->gradeAtualizada() && DB::table('aula_reposicoes')
            ->where('tenant_id', $tenantId)->where('turma_id', $turma['id'])->where('matricula_id', $row->id)->exists()];
    }

    public function erroCheckin(array $turma, int $tenantId, ?array $matricula): ?string
    {
        if (! $matricula || empty($turma['ativo']) || $this->aulaSuspensa($turma, $tenantId)) {
            return 'Você não possui matrícula ativa com acesso a esta turma e data.';
        }

        if (! empty($matricula['reposicao']) || $matricula['tipo_acesso'] === self::FIXA) {
            return null; // Presença na vaga já reservada; não consome saldo livre.
        }
        if (! ($turma['checkin_livre'] ?? true)) {
            return 'Esta aula não permite check-in de horário livre.';
        }

        return $this->vagas($turma, $tenantId)['vagas_livres'] > 0 ? null : 'Sem vagas livres disponíveis nesta aula.';
    }

    public function aulaSuspensa(array $turma, int $tenantId): bool
    {
        if (! $this->gradeTemporal() || empty($turma['turma_fixa_id'])) {
            return false;
        }
        $grupo = DB::table('turmas_fixas')->where('tenant_id', $tenantId)->where('id', $turma['turma_fixa_id'])
            ->when(DB::transactionLevel() > 0, fn ($q) => $q->lockForUpdate())->first();
        $data = substr((string) $turma['dia_data'], 0, 10);

        return ! $grupo || ! $grupo->ativo || $grupo->deleted_at || $grupo->vigencia_inicio > $data
            || ($grupo->vigencia_fim !== null && $grupo->vigencia_fim < $data)
            || DB::table('turma_fixa_pausas')->where('tenant_id', $tenantId)->where('turma_fixa_id', $grupo->id)
                ->whereNull('cancelada_em')->where('inicio', '<=', $data)->where('fim', '>=', $data)
                ->when(DB::transactionLevel() > 0, fn ($q) => $q->lockForUpdate())->exists();
    }

    /** Deve executar na transação que ativa a matrícula. Inclui reservas futuras e check-ins livres já feitos. */
    public function podeAtivar(int $tenantId, array $matricula): bool
    {
        if (($matricula['tipo_acesso'] ?? self::LIVRE) !== self::FIXA) {
            return true;
        }
        if ($this->gradeAtualizada()) {
            return $this->podeReservarGrade($tenantId, $matricula);
        }
        $grupo = DB::table('turmas_fixas')->where('id', $matricula['turma_fixa_id'])->where('tenant_id', $tenantId)->lockForUpdate()->first();
        if (! $grupo || ! $grupo->ativo) {
            return false;
        }
        $inicio = max(AcademyDateTime::today(), substr((string) $matricula['data_inicio'], 0, 10));
        $fim = substr((string) ($matricula['proxima_data_vencimento'] ?? $matricula['data_vencimento']), 0, 10);
        $sobrepostas = DB::table('matriculas as m')->join('status_matricula as s', 's.id', '=', 'm.status_id')
            ->where('m.tenant_id', $tenantId)->where('m.turma_fixa_id', $grupo->id)->where('m.tipo_acesso', self::FIXA)
            ->where('s.permite_checkin', 1)->where('s.ativo', 1)->where('m.id', '<>', $matricula['id'] ?? 0)
            ->whereDate('m.data_inicio', '<=', $fim)->whereRaw('COALESCE(m.proxima_data_vencimento, m.data_vencimento) >= ?', [$inicio])
            ->lockForUpdate()
            ->get(['m.aluno_id', 'm.data_inicio', 'm.data_vencimento', 'm.proxima_data_vencimento']);
        $datas = [$inicio];
        foreach ($sobrepostas as $m) {
            $datas[] = max($inicio, substr((string) $m->data_inicio, 0, 10));
        }
        foreach (array_unique($datas) as $data) {
            $ids = $sobrepostas->filter(fn ($m) => $m->data_inicio <= $data && ($m->proxima_data_vencimento ?? $m->data_vencimento) >= $data)
                ->pluck('aluno_id')->unique()->all();
            if (! in_array($matricula['aluno_id'], $ids) && count($ids) >= $grupo->capacidade) {
                return false;
            }
        }
        $aulas = DB::table('turmas as t')->join('dias as d', 'd.id', '=', 't.dia_id')->where('t.tenant_id', $tenantId)
            ->where('t.turma_fixa_id', $grupo->id)->where('t.ativo', 1)->whereBetween('d.data', [$inicio, $fim])
            ->lockForUpdate()
            ->get(['t.*', 'd.data as dia_data']);
        foreach ($aulas as $aula) {
            $vagas = $this->vagas((array) $aula, $tenantId, $matricula['id'] ?? null);
            $jaPresente = DB::table('checkins')->where('tenant_id', $tenantId)->where('turma_id', $aula->id)
                ->where('aluno_id', $matricula['aluno_id'])->lockForUpdate()->exists();
            if (! in_array((int) $matricula['aluno_id'], $vagas['alunos_fixos']) && ! $jaPresente && $vagas['vagas_livres'] < 1) {
                return false;
            }
        }

        return true;
    }

    private function podeReservarGrade(int $tenantId, array $matricula): bool
    {
        $grade = $matricula['grade'] ?? $this->grade($tenantId, (int) $matricula['id'], null, true);
        if (! $grade) {
            return false;
        }
        $inicio = max(AcademyDateTime::today(), substr((string) $matricula['data_inicio'], 0, 10));
        $fim = substr((string) ($matricula['proxima_data_vencimento'] ?? $matricula['data_vencimento']), 0, 10);
        foreach ($grade as $slot) {
            $inicioSlot = max($inicio, $slot['vigencia_inicio'] ?? $inicio);
            $fimSlot = min($fim, $slot['vigencia_fim'] ?? $fim);
            if ($inicioSlot > $fimSlot) {
                continue;
            }
            $grupo = DB::table('turmas_fixas')->where('tenant_id', $tenantId)->where('id', $slot['turma_fixa_id'])->lockForUpdate()->first();
            if (! $grupo || ! $grupo->ativo || ($this->gradeTemporal() && ($grupo->deleted_at
                || $inicioSlot < $grupo->vigencia_inicio || $fimSlot > ($grupo->vigencia_fim ?? $fimSlot)))) {
                return false;
            }
            $q = DB::table('matriculas as m')->join('matricula_grade as g', 'g.matricula_id', '=', 'm.id')
                ->join('status_matricula as s', 's.id', '=', 'm.status_id')->where('m.tenant_id', $tenantId)
                ->where('g.tenant_id', $tenantId)->where('g.turma_fixa_id', $grupo->id)->where('g.dia_semana', $slot['dia_semana'])
                ->where('m.tipo_acesso', self::FIXA)->where('s.permite_checkin', 1)->where('s.ativo', 1)
                ->where('m.id', '<>', $matricula['id'])->whereDate('m.data_inicio', '<=', $fim)
                ->whereRaw('COALESCE(m.proxima_data_vencimento, m.data_vencimento) >= ?', [$inicio])
                ->lockForUpdate();
            $colunas = ['m.aluno_id', 'm.data_inicio', 'm.data_vencimento', 'm.proxima_data_vencimento'];
            if ($this->gradeTemporal()) {
                $q->where('g.vigencia_inicio', '<=', $fimSlot)->where(fn ($p) => $p->whereNull('g.vigencia_fim')->orWhere('g.vigencia_fim', '>=', $inicioSlot));
                $colunas = [...$colunas, 'g.vigencia_inicio', 'g.vigencia_fim'];
            }
            $reservas = $q->get($colunas);
            $datas = [$inicioSlot, ...$reservas->pluck('data_inicio')->all(), ...($this->gradeTemporal() ? $reservas->pluck('vigencia_inicio')->all() : [])];
            foreach ($datas as $data) {
                $dia = new \DateTimeImmutable(max($inicioSlot, $data));
                $dia = $dia->modify('+'.(((int) $slot['dia_semana'] - (int) $dia->format('N') + 7) % 7).' days')->format('Y-m-d');
                if ($dia > $fimSlot) {
                    continue;
                }
                $alunos = $reservas->filter(fn ($r) => $r->data_inicio <= $dia && ($r->proxima_data_vencimento ?? $r->data_vencimento) >= $dia
                    && ($r->vigencia_inicio ?? $inicioSlot) <= $dia && ($r->vigencia_fim ?? $fimSlot) >= $dia)
                    ->pluck('aluno_id')->map(fn ($id) => (int) $id)->unique()->all();
                if (! in_array((int) $matricula['aluno_id'], $alunos) && count($alunos) >= $grupo->capacidade) {
                    return false;
                }
            }
            $aulas = DB::table('turmas as t')->join('dias as d', 'd.id', '=', 't.dia_id')->where('t.tenant_id', $tenantId)
                ->where('t.turma_fixa_id', $grupo->id)->where('t.ativo', 1)->whereBetween('d.data', [$inicioSlot, $fimSlot])
                ->lockForUpdate()->get(['t.*', 'd.data as dia_data']);
            foreach ($aulas as $aula) {
                if ((int) (new \DateTimeImmutable($aula->dia_data))->format('N') !== (int) $slot['dia_semana']) {
                    continue;
                }
                $vagas = $this->vagas((array) $aula, $tenantId, (int) $matricula['id']);
                $presente = DB::table('checkins')->where('tenant_id', $tenantId)->where('turma_id', $aula->id)
                    ->where('aluno_id', $matricula['aluno_id'])->lockForUpdate()->exists();
                if (! in_array((int) $matricula['aluno_id'], [...$vagas['alunos_fixos'], ...$vagas['alunos_reposicao']])
                    && ! $presente && $vagas['vagas_livres'] < 1) {
                    return false;
                }
            }
        }

        return true;
    }

    public function exigirContratacaoSecretaria(int $userId, int $tenantId, int $planoId): void
    {
        if (! $this->atualizado()) {
            return;
        }
        $modalidadeId = DB::table('planos')->where('tenant_id', $tenantId)->where('id', $planoId)->value('modalidade_id');
        $fixa = DB::table('matriculas as m')->join('alunos as a', 'a.id', '=', 'm.aluno_id')
            ->join('planos as p', 'p.id', '=', 'm.plano_id')->join('status_matricula as s', 's.id', '=', 'm.status_id')
            ->where('a.usuario_id', $userId)->where('m.tenant_id', $tenantId)->where('p.modalidade_id', $modalidadeId)
            ->where('m.tipo_acesso', self::FIXA)->exists();
        if ($fixa) {
            throw ValidationException::withMessages(['plano_id' => 'Para contratar ou trocar o plano de turma fixa, procure a secretaria. O pagamento da mensalidade permanece disponível.']);
        }
    }
}
