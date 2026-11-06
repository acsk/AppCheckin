<?php

namespace App\Services\Admin;

use App\Services\AcessoHibridoService;
use App\Services\Comunicacao\ComunicacaoService;
use App\Support\AcademyDateTime;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AdminTurmaFixaGestaoService
{
    public function atualizado(): bool
    {
        return Schema::hasTable('turma_fixa_eventos');
    }

    public function situacao(object $grupo, ?string $data = null): string
    {
        $data ??= AcademyDateTime::today();
        if (! $grupo->ativo || $grupo->deleted_at || ($grupo->vigencia_fim && $grupo->vigencia_fim < $data)) {
            return 'arquivada';
        }
        if ($grupo->vigencia_inicio > $data) {
            return 'agendada';
        }
        if (DB::table('turma_fixa_pausas')->where('tenant_id', $grupo->tenant_id)->where('turma_fixa_id', $grupo->id)
            ->whereNull('cancelada_em')->where('inicio', '<=', $data)->where('fim', '>=', $data)
            ->when(DB::transactionLevel() > 0, fn ($q) => $q->lockForUpdate())->exists()) {
            return 'pausada';
        }

        return 'ativa';
    }

    private function grupo(int $tenantId, int $id): object
    {
        $grupo = DB::table('turmas_fixas')->where('tenant_id', $tenantId)->where('id', $id)->whereNull('deleted_at')->lockForUpdate()->first();
        abort_unless($grupo, 404, 'Turma não encontrada.');

        return $grupo;
    }

    private function aulas(int $tenantId, int $id): Builder
    {
        return DB::table('turmas as t')->join('dias as d', 'd.id', '=', 't.dia_id')
            ->where('t.tenant_id', $tenantId)->where('t.turma_fixa_id', $id);
    }

    public function vinculados(int $tenantId, int $id, ?string $desde = null): array
    {
        $desde ??= AcademyDateTime::today();

        return DB::table('matricula_grade as g')->join('matriculas as m', 'm.id', '=', 'g.matricula_id')
            ->join('alunos as a', 'a.id', '=', 'm.aluno_id')->join('planos as p', 'p.id', '=', 'm.plano_id')
            ->join('status_matricula as s', 's.id', '=', 'm.status_id')
            ->where('g.tenant_id', $tenantId)->where('m.tenant_id', $tenantId)->where('p.tenant_id', $tenantId)
            ->where('g.turma_fixa_id', $id)->where('m.tipo_acesso', 'turma_fixa')->where('s.codigo', '<>', 'cancelada')
            ->where(fn ($q) => $q->whereNull('g.vigencia_fim')->orWhere('g.vigencia_fim', '>=', $desde))
            ->whereRaw('COALESCE(m.proxima_data_vencimento, m.data_vencimento) >= ?', [$desde])
            ->when(DB::transactionLevel() > 0, fn ($q) => $q->lockForUpdate())
            ->orderBy('m.id')->get(['m.id as matricula_id', 'm.aluno_id', 'a.nome', 'p.nome as plano_nome',
                'p.checkins_semanais', 's.codigo as status_codigo', 'g.dia_semana', 'g.vigencia_inicio', 'g.vigencia_fim'])
            ->groupBy('matricula_id')->map(fn ($rows) => (array) $rows->first() + ['dias_semana' => $rows->pluck('dia_semana')->unique()->values()->all()])
            ->values()->all();
    }

    public function detalhe(int $tenantId, int $id, ?string $desde = null): array
    {
        abort_unless($this->atualizado(), 409, 'Execute a migração de ciclo de vida das turmas.');
        if ($desde !== null) {
            Validator::make(['desde' => $desde], ['desde' => 'date_format:Y-m-d|after_or_equal:'.AcademyDateTime::today()])->validate();
        }

        return DB::transaction(function () use ($tenantId, $id, $desde) {
            app(AcessoHibridoService::class)->bloquearGrupos($tenantId);
            $grupo = $this->grupo($tenantId, $id);
            $aulas = $this->aulas($tenantId, $id)->orderBy('d.data')->orderBy('t.horario_inicio')
                ->where('d.data', '>=', AcademyDateTime::today())->limit(90)->get(['t.*', 'd.data as dia_data']);
            $acesso = app(AcessoHibridoService::class);
            $grupo->dias_semana = json_decode($grupo->dias_semana, true, 512, JSON_THROW_ON_ERROR);
            $grupo->situacao = $this->situacao($grupo);

            return ['turma' => $grupo, 'alunos' => $this->vinculados($tenantId, $id, $desde),
                'pausas' => DB::table('turma_fixa_pausas')->where('tenant_id', $tenantId)->where('turma_fixa_id', $id)->whereNull('cancelada_em')->orderBy('inicio')->get(),
                'aulas' => $aulas->map(fn ($aula) => (array) $aula + ['vagas' => $acesso->vagas((array) $aula, $tenantId)])->all(),
                'historico' => DB::table('turma_fixa_eventos')->where('tenant_id', $tenantId)->where('turma_fixa_id', $id)->orderByDesc('id')->limit(100)->get()
                    ->map(fn ($evento) => (array) $evento + ['detalhes' => json_decode($evento->dados, true, 512, JSON_THROW_ON_ERROR)])->all()];
        });
    }

    public function executar(int $tenantId, ?int $userId, array $data): array
    {
        abort_unless($this->atualizado(), 409, 'Execute a migração de ciclo de vida das turmas.');
        $v = Validator::make($data, [
            'acao' => 'required|in:duplicar,professor,grade,capacidade,pausar,retomar,arquivar,reativar,excluir',
            'ids' => 'required|array|min:1|max:50', 'ids.*' => 'required|integer|min:1|distinct',
            'dados' => 'sometimes|array',
        ])->validate();
        $dados = $v['dados'] ?? [];
        Validator::make($dados, ['avisar' => 'sometimes|boolean'])->validate();
        if (isset($dados['por_turma'])) {
            $porTurma = Validator::make($dados, ['por_turma' => 'required|array|min:1',
                'por_turma.*.id' => 'required|integer|distinct', 'por_turma.*.dados' => 'required|array',
                'por_turma.*.dados.avisar' => 'sometimes|boolean'])->validate()['por_turma'];
            $idsDados = array_map(fn ($item) => (int) $item['id'], $porTurma);
            $idsAcao = array_map('intval', $v['ids']);
            sort($idsDados);
            sort($idsAcao);
            if ($idsDados !== $idsAcao) {
                throw ValidationException::withMessages(['por_turma' => 'Informe os dados específicos de todas as turmas selecionadas.']);
            }
        }
        if (in_array($v['acao'], ['grade', 'duplicar', 'excluir'], true) && count($v['ids']) !== 1) {
            throw ValidationException::withMessages(['ids' => 'Esta ação deve ser feita em uma turma por vez.']);
        }

        return DB::transaction(function () use ($tenantId, $userId, $v, $dados) {
            app(AcessoHibridoService::class)->bloquearGrupos($tenantId);
            $ids = $v['ids'];
            sort($ids);
            $resultados = [];
            foreach ($ids as $id) {
                $grupo = $this->grupo($tenantId, $id);
                $dadosTurma = isset($dados['por_turma']) ? collect($dados['por_turma'])->firstWhere('id', $id)['dados'] : $dados;
                $resultados[] = $this->acao($tenantId, $userId, $grupo, $v['acao'], $dadosTurma);
                DB::table('turma_fixa_eventos')->insert([
                    'tenant_id' => $tenantId, 'turma_fixa_id' => $id, 'usuario_id' => $userId,
                    'acao' => $v['acao'], 'dados' => json_encode(['solicitacao' => $dadosTurma, 'resultado' => end($resultados)], JSON_THROW_ON_ERROR),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }

            return ['message' => 'Operação concluída. Grades, valores contratados e histórico foram preservados.', 'resultados' => $resultados];
        }, 3);
    }

    private function dataVigencia(array $dados): string
    {
        return Validator::make($dados, ['from' => 'required|date_format:Y-m-d|after:'.AcademyDateTime::today()])->validate()['from'];
    }

    private function exigirLivreDeReservasPontuais(int $tenantId, int $id, string $inicio, ?string $fim = null): void
    {
        $aulas = $this->aulas($tenantId, $id)->where('d.data', '>=', $inicio)
            ->when($fim, fn ($q) => $q->where('d.data', '<=', $fim))->lockForUpdate()->pluck('t.id');
        $presencas = DB::table('checkins')->where('tenant_id', $tenantId)->whereIn('turma_id', $aulas)->lockForUpdate()->exists();
        $reposicoes = DB::table('aula_reposicoes')->where('tenant_id', $tenantId)->whereIn('turma_id', $aulas)->lockForUpdate()->exists();
        if ($presencas || $reposicoes) {
            throw ValidationException::withMessages(['from' => 'Há check-ins ou reposições no período afetado. Resolva essas reservas explicitamente antes de mudar/cancelar as aulas.']);
        }
    }

    private function acao(int $tenantId, ?int $userId, object $grupo, string $acao, array $dados): array
    {
        $id = (int) $grupo->id;
        $admin = app(AdminTurmaFixaService::class);
        if ($acao === 'duplicar') {
            $v = Validator::make($dados, ['nome' => 'required|string|max:255', 'dias_semana' => 'required|array|min:1|max:7',
                'dias_semana.*' => 'integer|between:1,7|distinct', 'horario_inicio' => 'required|date_format:H:i',
                'horario_fim' => 'required|date_format:H:i|after:horario_inicio', 'professor_id' => 'required|integer|min:1'])->validate();

            return $admin->criar($tenantId, array_replace((array) $grupo, $v));
        }
        if ($acao === 'capacidade') {
            $cap = Validator::make($dados, ['capacidade' => 'required|integer|between:1,500'])->validate()['capacidade'];

            return $admin->atualizar($tenantId, $id, ['nome' => $grupo->nome, 'capacidade' => $cap, 'checkin_livre' => (bool) $grupo->checkin_livre]);
        }
        if ($acao === 'excluir') {
            if (DB::table('matricula_grade')->where('tenant_id', $tenantId)->where('turma_fixa_id', $id)->lockForUpdate()->exists()
                || $this->aulas($tenantId, $id)->whereExists(fn ($q) => $q->selectRaw('1')->from('checkins as c')->whereColumn('c.turma_id', 't.id')->where('c.tenant_id', $tenantId))->lockForUpdate()->exists()
                || $this->aulas($tenantId, $id)->whereExists(fn ($q) => $q->selectRaw('1')->from('aula_reposicoes as r')->whereColumn('r.turma_id', 't.id')->where('r.tenant_id', $tenantId))->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['ids' => 'Turmas com vínculos, presenças ou reposições não podem ser excluídas. Use arquivar.']);
            }
            DB::table('turmas_fixas')->where('id', $id)->update(['deleted_at' => now(), 'ativo' => 0]);
            $this->prepararOcorrencias($tenantId, $id);

            return ['id' => $id, 'excluida' => true];
        }
        if ($acao === 'retomar') {
            abort_unless($this->situacao($grupo) !== 'arquivada', 422, 'Reative a turma arquivada antes de retomar.');
            $canceladas = DB::table('turma_fixa_pausas')->where('tenant_id', $tenantId)->where('turma_fixa_id', $id)->whereNull('cancelada_em')
                ->where('fim', '>=', AcademyDateTime::today())->update(['cancelada_em' => now(), 'updated_at' => now()]);
            abort_unless($canceladas > 0, 422, 'A turma não tem pausa vigente ou futura para retomar.');
            $this->prepararOcorrencias($tenantId, $id);
            $admin->gerarAulas($tenantId, $id);
            $this->avisar($tenantId, $userId, $this->vinculados($tenantId, $id), 'Turma retomada: '.$grupo->nome, 'As próximas aulas voltaram à agenda.', $dados);

            return ['id' => $id];
        }
        if ($acao === 'reativar') {
            abort_if($grupo->substituida_por_id, 422, 'Esta turma foi substituída por outra versão. Reative ou edite a nova turma.');
            abort_unless($this->situacao($grupo) === 'arquivada', 422, 'A turma não está arquivada.');
            DB::table('turmas_fixas')->where('id', $id)->update(['ativo' => 1, 'vigencia_fim' => null, 'updated_at' => now()]);
            $this->prepararOcorrencias($tenantId, $id);
            $admin->gerarAulas($tenantId, $id);

            return ['id' => $id, 'message' => 'Turma reativada. Vínculos retirados não são restaurados automaticamente.'];
        }
        abort_unless($this->situacao($grupo) !== 'arquivada' && ! $grupo->vigencia_fim, 422, 'A turma está arquivada ou já tem uma substituição/arquivamento agendado.');
        if ($acao === 'pausar') {
            $v = Validator::make($dados, ['from' => 'required|date_format:Y-m-d|after_or_equal:'.AcademyDateTime::today(),
                'to' => 'required|date_format:Y-m-d|after_or_equal:from', 'motivo' => 'nullable|string|max:500'])->validate();
            $this->exigirLivreDeReservasPontuais($tenantId, $id, $v['from'], $v['to']);
            if (DB::table('turma_fixa_pausas')->where('tenant_id', $tenantId)->where('turma_fixa_id', $id)->whereNull('cancelada_em')
                ->where('inicio', '<=', $v['to'])->where('fim', '>=', $v['from'])->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['from' => 'A turma já tem uma pausa nesse período.']);
            }
            DB::table('turma_fixa_pausas')->insert(['tenant_id' => $tenantId, 'turma_fixa_id' => $id,
                'inicio' => $v['from'], 'fim' => $v['to'], 'motivo' => $v['motivo'] ?? null, 'created_at' => now(), 'updated_at' => now()]);
            $this->prepararOcorrencias($tenantId, $id);
            $this->avisar($tenantId, $userId, $this->vinculados($tenantId, $id, $v['from']), 'Pausa: '.$grupo->nome,
                "Sem aulas de {$v['from']} a {$v['to']}. ".($v['motivo'] ?? '').' Cobrança e vigência permanecem iguais.', $dados);

            return ['id' => $id];
        }
        $desde = $this->dataVigencia($dados);
        abort_if($desde < $grupo->vigencia_inicio, 422, 'A mudança não pode começar antes da vigência desta turma.');
        $this->exigirLivreDeReservasPontuais($tenantId, $id, $desde);
        $alunos = $this->vinculados($tenantId, $id, $desde);
        if ($acao === 'arquivar') {
            $this->remapear($tenantId, $id, null, $desde, $alunos, $dados, true);
            DB::table('turmas_fixas')->where('id', $id)->update(['vigencia_fim' => (new \DateTimeImmutable($desde))->modify('-1 day')->format('Y-m-d'), 'updated_at' => now()]);
            $this->prepararOcorrencias($tenantId, $id);
            $this->avisar($tenantId, $userId, $alunos, 'Turma arquivada: '.$grupo->nome, "Aulas encerradas a partir de {$desde}. Confira sua grade no app. Cobrança e vigência não mudaram.", $dados);

            return ['id' => $id];
        }
        $v = $acao === 'professor'
            ? Validator::make($dados, ['professor_id' => 'required|integer|min:1'])->validate()
            : Validator::make($dados, ['dias_semana' => 'required|array|min:1|max:7', 'dias_semana.*' => 'integer|between:1,7|distinct',
                'horario_inicio' => 'required|date_format:H:i', 'horario_fim' => 'required|date_format:H:i|after:horario_inicio',
                'professor_id' => 'required|integer|min:1', 'nome' => 'sometimes|required|string|max:255'])->validate();
        $v['dias_semana'] ??= json_decode($grupo->dias_semana, true, 512, JSON_THROW_ON_ERROR);
        $v['horario_inicio'] ??= substr($grupo->horario_inicio, 0, 5);
        $v['horario_fim'] ??= substr($grupo->horario_fim, 0, 5);
        DB::table('turmas_fixas')->where('id', $id)->update(['vigencia_fim' => (new \DateTimeImmutable($desde))->modify('-1 day')->format('Y-m-d')]);
        $this->prepararOcorrencias($tenantId, $id);
        $novo = $admin->criar($tenantId, array_replace((array) $grupo, $v), $desde);
        $novoId = (int) $novo['id'];
        $this->copiarConfiguracoesOcorrencias($tenantId, $id, $novoId);
        if ($acao === 'professor') {
            $dados['remapeamentos'] = array_map(fn ($aluno) => ['matricula_id' => $aluno['matricula_id'], 'dias_semana' => $aluno['dias_semana']], $alunos);
        }
        $this->remapear($tenantId, $id, $novoId, $desde, $alunos, $dados, false, $acao === 'professor');
        DB::table('turmas_fixas')->where('id', $id)->update(['substituida_por_id' => $novoId, 'updated_at' => now()]);
        $pausas = DB::table('turma_fixa_pausas')->where('tenant_id', $tenantId)->where('turma_fixa_id', $id)->whereNull('cancelada_em')->where('fim', '>=', $desde)->get();
        foreach ($pausas as $pausa) {
            DB::table('turma_fixa_pausas')->insert(['tenant_id' => $tenantId, 'turma_fixa_id' => $novoId, 'inicio' => max($desde, $pausa->inicio),
                'fim' => $pausa->fim, 'motivo' => $pausa->motivo, 'created_at' => now(), 'updated_at' => now()]);
        }
        $this->prepararOcorrencias($tenantId, $novoId);
        $this->avisar($tenantId, $userId, $alunos, 'Alteração de turma: '.$grupo->nome, "Nova grade/professor a partir de {$desde}. Confira sua grade no app. Plano, valor e histórico permanecem iguais.", $dados);

        return ['id' => $id, 'nova_turma_id' => $novoId, 'vigencia_inicio' => $desde];
    }

    private function remapear(int $tenantId, int $origem, ?int $nova, string $desde, array $alunos, array $dados, bool $arquivar, bool $preservarFrequencia = false): void
    {
        $v = Validator::make($dados, ['remapeamentos' => 'present|array', 'remapeamentos.*.matricula_id' => 'required|integer|distinct',
            'remapeamentos.*.dias_semana' => 'sometimes|array', 'remapeamentos.*.dias_semana.*' => 'integer|between:1,7',
            'remapeamentos.*.grade' => 'sometimes|array', 'remapeamentos.*.retirar' => 'sometimes|boolean'])->validate();
        $mapa = collect($v['remapeamentos'])->keyBy('matricula_id');
        $esperados = array_map(fn ($aluno) => (int) $aluno['matricula_id'], $alunos);
        $informados = $mapa->keys()->map(fn ($id) => (int) $id)->all();
        sort($esperados);
        sort($informados);
        if ($esperados !== $informados) {
            throw ValidationException::withMessages(['remapeamentos' => 'Confirme o remapeamento ou retirada de todos os vínculos afetados, sem incluir matrículas de outra turma.']);
        }
        $acesso = app(AcessoHibridoService::class);
        foreach ($alunos as $aluno) {
            $m = DB::table('matriculas')->where('tenant_id', $tenantId)->where('id', $aluno['matricula_id'])->lockForUpdate()->first();
            $atual = $acesso->grade($tenantId, (int) $m->id, $desde);
            $outros = array_values(array_filter($atual, fn ($slot) => (int) $slot['turma_fixa_id'] !== $origem));
            $r = $mapa[$m->id];
            $slots = $arquivar ? ($r['grade'] ?? []) : array_map(fn ($dia) => ['turma_fixa_id' => $nova, 'dia_semana' => $dia], $r['dias_semana'] ?? []);
            if ($arquivar && $slots && ! empty($r['retirar'])) {
                throw ValidationException::withMessages(['remapeamentos' => 'Escolha transferência ou retirada, não ambas para a mesma matrícula.']);
            }
            if (! $slots && (! $arquivar || empty($r['retirar']))) {
                throw ValidationException::withMessages(['remapeamentos' => 'Escolha horários de destino ou confirme explicitamente a retirada desse vínculo ao arquivar.']);
            }
            $grade = [...$outros, ...$slots];
            if ($grade) {
                $acesso->validarEscolha($tenantId, ['grade' => $grade], (array) $m, $desde, $preservarFrequencia);
            }
            $q = DB::table('matricula_grade')->where('tenant_id', $tenantId)->where('matricula_id', $m->id)->where('turma_fixa_id', $origem)
                ->where(fn ($q) => $q->whereNull('vigencia_fim')->orWhere('vigencia_fim', '>=', $desde));
            (clone $q)->where('vigencia_inicio', '>=', $desde)->delete();
            $q->where('vigencia_inicio', '<', $desde)->update(['vigencia_fim' => (new \DateTimeImmutable($desde))->modify('-1 day')->format('Y-m-d'), 'updated_at' => now()]);
            foreach ($slots as $slot) {
                DB::table('matricula_grade')->insert(['tenant_id' => $tenantId, 'matricula_id' => $m->id,
                    'turma_fixa_id' => $slot['turma_fixa_id'], 'dia_semana' => $slot['dia_semana'],
                    'vigencia_inicio' => $desde, 'created_at' => now(), 'updated_at' => now()]);
            }
            $limite = (int) $aluno['checkins_semanais'];
            DB::table('matriculas')->where('id', $m->id)->where('tenant_id', $tenantId)
                ->update(['grade_revisao' => count($grade) === 0 || ($limite > 0 && count($grade) > $limite)]);
        }
        foreach ($alunos as $aluno) {
            $m = DB::table('matriculas')->where('tenant_id', $tenantId)->where('id', $aluno['matricula_id'])->first();
            $grade = $acesso->grade($tenantId, (int) $m->id, $desde, true);
            $datas = [$desde];
            foreach ($grade as $slot) {
                $datas[] = max($desde, $slot['vigencia_inicio']);
                if ($slot['vigencia_fim']) {
                    $datas[] = (new \DateTimeImmutable($slot['vigencia_fim']))->modify('+1 day')->format('Y-m-d');
                }
            }
            foreach (array_unique($datas) as $data) {
                $periodo = $acesso->grade($tenantId, (int) $m->id, $data);
                if ($periodo) {
                    $acesso->validarEscolha($tenantId, ['grade' => $periodo], (array) $m, $data, $preservarFrequencia);
                }
            }
            if ($grade && ! $acesso->podeAtivar($tenantId, (array) $m + ['grade' => $grade])) {
                throw ValidationException::withMessages(['remapeamentos' => 'A grade de destino não comporta todas as reservas. Nenhuma alteração foi aplicada.']);
            }
        }
    }

    public function prepararOcorrencias(int $tenantId, int $id): void
    {
        if (! $this->atualizado()) {
            return;
        }
        $grupo = DB::table('turmas_fixas')->where('tenant_id', $tenantId)->where('id', $id)->lockForUpdate()->first();
        $pausas = DB::table('turma_fixa_pausas')->where('tenant_id', $tenantId)->where('turma_fixa_id', $id)->whereNull('cancelada_em')->lockForUpdate()->get();
        $aulas = $this->aulas($tenantId, $id)->where('d.data', '>=', AcademyDateTime::today())->orderBy('t.id')->lockForUpdate()->get(['t.*', 'd.data as dia_data']);
        foreach ($aulas as $aula) {
            $suspensa = ! $grupo->ativo || $grupo->deleted_at || $aula->dia_data < $grupo->vigencia_inicio
                || ($grupo->vigencia_fim && $aula->dia_data > $grupo->vigencia_fim)
                || $pausas->contains(fn ($pausa) => $pausa->inicio <= $aula->dia_data && $pausa->fim >= $aula->dia_data);
            if ($suspensa && $aula->ativo) {
                DB::table('turmas')->where('id', $aula->id)->where('tenant_id', $tenantId)->update(['ativo' => 0, 'suspensao_turma_id' => $id, 'updated_at' => now()]);
            } elseif (! $suspensa && (int) $aula->suspensao_turma_id === $id) {
                DB::table('turmas')->where('id', $aula->id)->where('tenant_id', $tenantId)->update(['ativo' => 1, 'suspensao_turma_id' => null, 'updated_at' => now()]);
            }
        }

    }

    public function copiarConfiguracoesOcorrencias(int $tenantId, int $origem, int $destino, ?array $idsNovas = null): void
    {
        $campos = ['limite_alunos', 'checkin_livre', 'tolerancia_minutos', 'tolerancia_antes_minutos',
            'tolerancia_antes_checkin_minutos', 'tolerancia_cancelamento_minutos'];
        $pares = DB::table('turmas as n')->join('turmas as o', function ($q) {
            $q->on('o.dia_id', '=', 'n.dia_id')->on('o.horario_inicio', '=', 'n.horario_inicio')->on('o.horario_fim', '=', 'n.horario_fim');
        })->where('n.tenant_id', $tenantId)->where('o.tenant_id', $tenantId)
            ->where('o.turma_fixa_id', $origem)->where('n.turma_fixa_id', $destino)
            ->when($idsNovas !== null, fn ($q) => $q->whereIn('n.id', $idsNovas))->lockForUpdate()
            ->get(['n.id as nova_id', 'o.ativo', 'o.suspensao_turma_id', ...array_map(fn ($campo) => 'o.'.$campo, $campos)]);
        foreach ($pares as $par) {
            $dados = array_intersect_key((array) $par, array_flip($campos));
            if (! $par->ativo && $par->suspensao_turma_id === null) {
                $dados += ['ativo' => 0, 'suspensao_turma_id' => null];
            }
            DB::table('turmas')->where('tenant_id', $tenantId)->where('id', $par->nova_id)->update($dados);
        }
    }

    private function avisar(int $tenantId, ?int $userId, array $alunos, string $titulo, string $texto, array $dados): void
    {
        if (empty($dados['avisar']) || ! $alunos) {
            return;
        }
        $r = app(ComunicacaoService::class)->criarComunicacao($tenantId, ['tipo' => 'notificacao',
            'titulo' => mb_substr($titulo, 0, 160), 'descricao' => mb_substr($texto, 0, 500), 'conteudo' => $texto,
            'publico' => ['tipo' => 'alunos', 'alunos' => array_values(array_unique(array_column($alunos, 'aluno_id')))],
            'publicar' => true, 'obrigatoria' => false, 'manter_historico' => true], $userId);
        if ($r['status'] !== 201) {
            throw ValidationException::withMessages(['avisar' => $r['body']['error'] ?? 'Não foi possível publicar o aviso aos alunos. A operação foi desfeita.']);
        }
    }
}
