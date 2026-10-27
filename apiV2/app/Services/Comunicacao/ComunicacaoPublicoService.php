<?php

namespace App\Services\Comunicacao;

use App\Support\AcademyDateTime;
use App\Support\Comunicacao;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Quem recebe uma comunicação. Sempre parte dos alunos ativos da academia (papel aluno ativo no tenant);
 * nada de outro tenant entra, mesmo que o filtro traga ids de fora.
 *
 * publico.tipo:
 * - todos: todos os alunos da academia;
 * - ativos: com matrícula ativa e dentro do acesso;
 * - segmento: filtros combinados (E): modalidades e planos (matrícula ativa), horários (check-in em turma
 *   daquela modalidade e horário nos últimos dias_checkin dias, padrão 30: turma é a aula do dia, então o
 *   "aluno da turma" é quem a frequenta) e inadimplentes (parcela vencida em aberto);
 * - alunos: alunos escolhidos e/ou donos das matrículas escolhidas.
 */
class ComunicacaoPublicoService
{
    /**
     * Normaliza e valida o público vindo do painel.
     *
     * @param  mixed  $publico
     * @return array{0: array<string, mixed>|null, 1: string|null} [publico, erro]
     */
    public function normalizar(mixed $publico): array
    {
        $p = is_array($publico) ? $publico : [];
        $tipo = (string) ($p['tipo'] ?? 'todos');
        if (! in_array($tipo, Comunicacao::PUBLICOS, true)) {
            return [null, 'Público inválido.'];
        }
        $ids = fn (string $k) => array_values(array_unique(array_filter(array_map('intval', (array) ($p[$k] ?? [])), fn ($v) => $v > 0)));
        $out = ['tipo' => $tipo];
        if ($tipo === 'segmento') {
            $out += [
                'modalidades' => $ids('modalidades'),
                'planos' => $ids('planos'),
                'horarios' => $this->horarios($p['horarios'] ?? []),
                'dias_checkin' => max(1, min(365, (int) ($p['dias_checkin'] ?? Comunicacao::DIAS_CHECKIN_PADRAO))),
                'inadimplentes' => (bool) ($p['inadimplentes'] ?? false),
            ];
            if (! $out['modalidades'] && ! $out['planos'] && ! $out['horarios'] && ! $out['inadimplentes']) {
                return [null, 'Escolha ao menos um filtro do segmento (modalidade, plano, turma/horário ou inadimplentes).'];
            }
        }
        if ($tipo === 'alunos') {
            $out += ['alunos' => $ids('alunos'), 'matriculas' => $ids('matriculas')];
            if (! $out['alunos'] && ! $out['matriculas']) {
                return [null, 'Escolha ao menos um aluno ou matrícula.'];
            }
        }

        return [$out, null];
    }

    /** @return list<int> aluno_id */
    public function resolver(int $tenantId, array $publico): array
    {
        $q = $this->base($tenantId);
        $hoje = AcademyDateTime::today();
        $tipo = $publico['tipo'] ?? 'todos';

        if ($tipo === 'ativos') {
            $q->whereExists(fn ($e) => $this->matriculaAtiva($e, $tenantId, $hoje));
        }
        if ($tipo === 'segmento') {
            if (! empty($publico['modalidades'])) {
                $q->whereExists(fn ($e) => $this->matriculaAtiva($e, $tenantId, $hoje)->whereIn('p.modalidade_id', $publico['modalidades']));
            }
            if (! empty($publico['planos'])) {
                $q->whereExists(fn ($e) => $this->matriculaAtiva($e, $tenantId, $hoje)->whereIn('m.plano_id', $publico['planos']));
            }
            if (! empty($publico['horarios'])) {
                $desde = AcademyDateTime::parse($hoje)->modify('-'.(int) ($publico['dias_checkin'] ?? Comunicacao::DIAS_CHECKIN_PADRAO).' days')->format('Y-m-d');
                $q->whereExists(fn ($e) => $e->selectRaw('1')->from('checkins as ck')
                    ->join('turmas as t', 't.id', '=', 'ck.turma_id')
                    ->whereColumn('ck.aluno_id', 'a.id')->where('ck.tenant_id', $tenantId)->where('t.tenant_id', $tenantId)
                    ->where('ck.data_checkin', '>=', $desde)
                    ->where(function ($w) use ($publico) {
                        foreach ($publico['horarios'] as $h) {
                            $w->orWhere(fn ($o) => $o->where('t.modalidade_id', $h['modalidade_id'])->where('t.horario_inicio', $h['horario'].':00'));
                        }
                    }));
            }
            if (! empty($publico['inadimplentes'])) {
                $q->whereExists(fn ($e) => $e->selectRaw('1')->from('pagamentos_plano as pp')
                    ->whereColumn('pp.aluno_id', 'a.id')->where('pp.tenant_id', $tenantId)
                    ->whereIn('pp.status_pagamento_id', [1, 3])->whereNull('pp.data_pagamento')
                    ->where('pp.data_vencimento', '<', $hoje));
            }
        }
        if ($tipo === 'alunos') {
            $alunos = $publico['alunos'] ?? [];
            $matriculas = $publico['matriculas'] ?? [];
            $q->where(function ($w) use ($alunos, $matriculas, $tenantId) {
                $w->whereIn('a.id', $alunos ?: [0]);
                if ($matriculas) {
                    $w->orWhereExists(fn ($e) => $e->selectRaw('1')->from('matriculas as mm')
                        ->whereColumn('mm.aluno_id', 'a.id')->where('mm.tenant_id', $tenantId)->whereIn('mm.id', $matriculas));
                }
            });
        }

        return $q->distinct()->orderBy('a.id')->pluck('a.id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * Turma = modalidade + horário de início ("HH:MM").
     *
     * @return list<array{modalidade_id: int, horario: string}>
     */
    private function horarios(mixed $lista): array
    {
        $out = [];
        foreach ((array) $lista as $h) {
            $mod = (int) (is_array($h) ? ($h['modalidade_id'] ?? 0) : 0);
            $hora = substr((string) (is_array($h) ? ($h['horario'] ?? '') : ''), 0, 5);
            if ($mod > 0 && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $hora)) {
                $out["{$mod}|{$hora}"] = ['modalidade_id' => $mod, 'horario' => $hora];
            }
        }

        return array_values($out);
    }

    /** Alunos ativos da academia. */
    private function base(int $tenantId): Builder
    {
        return DB::table('alunos as a')
            ->join('tenant_usuario_papel as tup', 'tup.usuario_id', '=', 'a.usuario_id')
            ->join('usuarios as u', 'u.id', '=', 'a.usuario_id')
            ->where('tup.tenant_id', $tenantId)
            ->where('tup.papel_id', 1)
            ->where('tup.ativo', 1)
            ->where(fn ($w) => $w->whereNull('u.ativo')->orWhere('u.ativo', 1));
    }

    private function matriculaAtiva(Builder $e, int $tenantId, string $hoje): Builder
    {
        return $e->selectRaw('1')->from('matriculas as m')
            ->join('planos as p', 'p.id', '=', 'm.plano_id')
            ->join('status_matricula as sm', 'sm.id', '=', 'm.status_id')
            ->whereColumn('m.aluno_id', 'a.id')
            ->where('m.tenant_id', $tenantId)
            ->where('sm.codigo', 'ativa')
            ->where(fn ($w) => $w->whereRaw('COALESCE(m.proxima_data_vencimento, m.data_vencimento) IS NULL')
                ->orWhereRaw('COALESCE(m.proxima_data_vencimento, m.data_vencimento) >= ?', [$hoje]));
    }
}
