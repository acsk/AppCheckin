<?php

namespace App\Services\Admin;

use App\Repositories\DiaRepository;
use App\Repositories\TurmaRepository;
use App\Services\AcessoHibridoService;
use App\Services\PagamentoPlanoService;
use App\Support\AcademyDateTime;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AdminTurmaFixaService
{
    public function listar(int $tenantId): array
    {
        $turmas = DB::table('turmas_fixas')->where('tenant_id', $tenantId)->orderBy('nome')->get();
        foreach ($turmas as $turma) {
            $turma->dias_semana = json_decode($turma->dias_semana, true);
            $turma->planos = DB::table('turma_fixa_planos as tp')->join('planos as p', 'p.id', '=', 'tp.plano_id')
                ->where('tp.turma_fixa_id', $turma->id)->where('p.tenant_id', $tenantId)->get(['p.id', 'p.nome', 'p.valor'])->all();
        }

        return ['turmas' => $turmas];
    }

    public function criar(int $tenantId, array $dados): array
    {
        $v = Validator::make($dados, [
            'nome' => 'required|string|max:255', 'professor_id' => 'required|integer|min:1',
            'modalidade_id' => 'required|integer|min:1', 'dias_semana' => 'required|array|min:1|max:7',
            'dias_semana.*' => 'required|integer|between:1,7|distinct',
            'horario_inicio' => 'required|date_format:H:i', 'horario_fim' => 'required|date_format:H:i|after:horario_inicio',
            'capacidade' => 'required|integer|between:1,500', 'planos' => 'required|array|min:1',
            'planos.*' => 'integer|min:1|distinct', 'checkin_livre' => 'sometimes|boolean',
        ])->validate();
        if (! app(TurmaRepository::class)->professorPertenceAoTenant((int) $v['professor_id'], $tenantId)) {
            throw ValidationException::withMessages(['professor_id' => 'Professor não pertence ao tenant.']);
        }
        $planos = DB::table('planos')->where('tenant_id', $tenantId)->where('modalidade_id', $v['modalidade_id'])
            ->where('ativo', 1)->whereIn('id', $v['planos'])->count();
        if ($planos !== count($v['planos'])) {
            throw ValidationException::withMessages(['planos' => 'Todos os planos devem ser ativos e da modalidade deste tenant.']);
        }

        return DB::transaction(function () use ($tenantId, $v) {
            $id = DB::table('turmas_fixas')->insertGetId([
                'tenant_id' => $tenantId, 'nome' => trim($v['nome']), 'professor_id' => $v['professor_id'],
                'modalidade_id' => $v['modalidade_id'], 'dias_semana' => json_encode($v['dias_semana']),
                'horario_inicio' => $v['horario_inicio'], 'horario_fim' => $v['horario_fim'],
                'capacidade' => $v['capacidade'], 'checkin_livre' => $v['checkin_livre'] ?? false,
                'ativo' => 1, 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('turma_fixa_planos')->insert(array_map(fn ($p) => ['turma_fixa_id' => $id, 'plano_id' => $p], $v['planos']));
            $this->gerarAulas($tenantId, (int) $id);

            return ['id' => (int) $id, 'message' => 'Turma fixa criada com suas aulas dos próximos 90 dias.'];
        }, 3);
    }

    public function gerarAulas(int $tenantId, int $id): int
    {
        return DB::transaction(function () use ($tenantId, $id) {
            $grupo = DB::table('turmas_fixas')->where('id', $id)->where('tenant_id', $tenantId)->where('ativo', 1)->lockForUpdate()->first();
            if (! $grupo) {
                return 0;
            }
            DB::table('professores')->where('id', $grupo->professor_id)->lockForUpdate()->first();

            $diasSemana = json_decode($grupo->dias_semana, true);
            $dias = app(DiaRepository::class);
            $turmas = app(TurmaRepository::class);
            $total = 0;
            $dia = AcademyDateTime::now();
            for ($i = 0; $i < 90; $i++, $dia->modify('+1 day')) {
                // ISO: segunda=1 ... domingo=7 (distinto do DAYOFWEEK legado).
                if (! in_array((int) $dia->format('N'), $diasSemana)) {
                    continue;
                }
                $data = $dia->format('Y-m-d');
                $d = $dias->findByData($data, $tenantId);
                if (! $d) {
                    $campos = ['data' => $data, 'ativo' => 1];
                    if (Schema::hasColumn('dias', 'tenant_id')) {
                        $campos['tenant_id'] = $tenantId;
                    }
                    try {
                        $diaId = DB::table('dias')->insertGetId($campos);
                    } catch (QueryException $e) {
                        if ((int) ($e->errorInfo[1] ?? 0) !== 1062) {
                            throw $e;
                        }
                        $q = DB::table('dias')->where('data', $data);
                        if (Schema::hasColumn('dias', 'tenant_id')) {
                            $q->where('tenant_id', $tenantId);
                        }
                        $existente = $q->lockForUpdate()->first();
                        if (! $existente) {
                            throw $e;
                        }
                        if (! $existente->ativo) {
                            continue;
                        }
                        $diaId = $existente->id;
                    }
                } else {
                    if (! $d['ativo']) {
                        continue; // Feriados/dias fechados já configurados não são reabertos.
                    }
                    $diaId = $d['id'];
                }
                if (DB::table('turmas')->where('turma_fixa_id', $id)->where('dia_id', $diaId)->exists()) {
                    continue;
                }
                if ($turmas->verificarHorarioOcupado($tenantId, (int) $diaId, $grupo->horario_inicio, $grupo->horario_fim, null, $grupo->professor_id)) {
                    throw ValidationException::withMessages(['horario_inicio' => "Professor já possui aula em {$data} neste horário."]);
                }
                $turmas->criar([
                    'tenant_id' => $tenantId, 'turma_fixa_id' => $id, 'professor_id' => $grupo->professor_id,
                    'modalidade_id' => $grupo->modalidade_id, 'dia_id' => $diaId, 'nome' => $grupo->nome,
                    'horario_inicio' => $grupo->horario_inicio, 'horario_fim' => $grupo->horario_fim,
                    'limite_alunos' => $grupo->capacidade, 'checkin_livre' => (bool) $grupo->checkin_livre,
                ]);
                $total++;
            }

            return $total;
        }, 3);
    }

    public function atualizar(int $tenantId, int $id, array $dados): array
    {
        $v = Validator::make($dados, [
            'nome' => 'required|string|max:255', 'capacidade' => 'required|integer|between:1,500',
            'checkin_livre' => 'required|boolean', 'planos' => 'required|array|min:1',
            'planos.*' => 'integer|min:1|distinct',
        ])->validate();

        return DB::transaction(function () use ($tenantId, $id, $v) {
            $grupo = DB::table('turmas_fixas')->where('tenant_id', $tenantId)->where('id', $id)->lockForUpdate()->first();
            abort_unless($grupo, 404, 'Turma fixa não encontrada.');
            $ofertados = DB::table('planos')->where('tenant_id', $tenantId)->where('modalidade_id', $grupo->modalidade_id)
                ->where('ativo', 1)->whereIn('id', $v['planos'])->count();
            if ($ofertados !== count($v['planos'])) {
                throw ValidationException::withMessages(['planos' => 'Planos devem ser ativos e da modalidade deste tenant.']);
            }
            $acesso = app(AcessoHibridoService::class);
            $matriculas = DB::table('matriculas as m')->join('status_matricula as s', 's.id', '=', 'm.status_id')
                ->where('m.tenant_id', $tenantId)->where('m.turma_fixa_id', $id)->where('s.permite_checkin', 1)
                ->whereRaw('COALESCE(m.proxima_data_vencimento, m.data_vencimento) >= ?', [AcademyDateTime::today()])
                ->lockForUpdate()->get(['m.*']);
            DB::table('turmas_fixas')->where('id', $id)->update([
                'capacidade' => $v['capacidade'], 'nome' => trim($v['nome']), 'checkin_livre' => $v['checkin_livre'], 'updated_at' => now(),
            ]);
            foreach ($matriculas as $matricula) {
                if (! $acesso->podeAtivar($tenantId, (array) $matricula)) {
                    throw ValidationException::withMessages(['capacidade' => 'A nova capacidade não comporta as matrículas vigentes.']);
                }
            }
            $aulas = DB::table('turmas as t')->join('dias as d', 'd.id', '=', 't.dia_id')
                ->where('t.tenant_id', $tenantId)->where('t.turma_fixa_id', $id)->where('d.data', '>', AcademyDateTime::today())
                ->orderBy('t.id')->lockForUpdate()->get(['t.*', 'd.data as dia_data']);
            foreach ($aulas as $aula) {
                $saldo = $acesso->vagas((array) $aula, $tenantId);
                if ($v['capacidade'] < $saldo['vagas_fixas'] + $saldo['vagas_livres_ocupadas']) {
                    throw ValidationException::withMessages(['capacidade' => 'A nova capacidade não comporta as reservas e check-ins existentes.']);
                }
                DB::table('turmas')->where('id', $aula->id)->update(['limite_alunos' => $v['capacidade'], 'nome' => trim($v['nome']), 'updated_at' => now()]);
            }
            DB::table('turma_fixa_planos')->where('turma_fixa_id', $id)->delete();
            DB::table('turma_fixa_planos')->insert(array_map(fn ($p) => ['turma_fixa_id' => $id, 'plano_id' => $p], $v['planos']));

            return ['message' => 'Turma atualizada. Contratos e permissões específicas das aulas foram preservados.'];
        }, 3);
    }

    public function vincularMatricula(int $tenantId, int $id, array $dados): array
    {
        $dados = array_intersect_key($dados, array_flip(['tipo_acesso', 'turma_fixa_id']));

        return DB::transaction(function () use ($tenantId, $id, $dados) {
            $m = DB::table('matriculas as m')->join('status_matricula as s', 's.id', '=', 'm.status_id')
                ->where('m.id', $id)->where('m.tenant_id', $tenantId)->first(['m.*', 's.permite_checkin']);
            abort_unless($m, 404, 'Matrícula não encontrada.');
            $acesso = app(AcessoHibridoService::class);
            $escolha = $acesso->validarEscolha($tenantId, $dados, (array) $m);
            $grupos = array_unique(array_filter([$m->turma_fixa_id, $escolha['turma_fixa_id']]));
            sort($grupos);
            DB::table('turmas_fixas')->where('tenant_id', $tenantId)->whereIn('id', $grupos)->orderBy('id')->lockForUpdate()->get();
            $m = DB::table('matriculas as m')->join('status_matricula as s', 's.id', '=', 'm.status_id')
                ->where('m.id', $id)->where('m.tenant_id', $tenantId)->lockForUpdate()->first(['m.*', 's.permite_checkin']);
            $escolha = $acesso->validarEscolha($tenantId, $dados, (array) $m);
            if ($m->permite_checkin && ! $acesso->podeAtivar($tenantId, array_replace((array) $m, $escolha))) {
                throw ValidationException::withMessages(['turma_fixa_id' => 'Não há vaga para o período vigente desta matrícula.']);
            }
            DB::table('matriculas')->where('id', $id)->where('tenant_id', $tenantId)->update($escolha + ['updated_at' => now()]);
            Log::info('Tipo de acesso alterado pela secretaria', ['tenant_id' => $tenantId, 'matricula_id' => $id, 'antes' => ['tipo_acesso' => $m->tipo_acesso, 'turma_fixa_id' => $m->turma_fixa_id], 'depois' => $escolha]);
            if (DB::table('status_matricula')->where('id', $m->status_id)->value('codigo') === 'aguardando_vaga') {
                app(PagamentoPlanoService::class)->atualizarStatusMatricula($tenantId, $id);
            }

            return ['message' => 'Tipo de acesso atualizado sem alterar o valor contratado.', 'data' => $escolha];
        }, 3);
    }
}
