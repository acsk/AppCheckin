<?php

namespace App\Repositories\Bi;

use Illuminate\Support\Facades\DB;

/**
 * Consultas do BI da academia. Toda consulta recebe o tenant do token e filtra por ele.
 * Datas no formato Y-m-d, intervalos fechados [inicio, fim].
 *
 * Status de matrícula: 1 ativa, 2 vencida, 3 cancelada, 4 finalizada, 5 pendente, 6 bloqueado, 7 concluída.
 * Status de pagamento: 1 aguardando, 2 pago, 3 atrasado, 4 cancelado.
 */
class BiTenantRepository
{
    /** Dias após o cancelamento em que uma nova matrícula do aluno conta como troca de plano, não saída */
    public const JANELA_TROCA_DIAS = 7;

    /*
     * Cada indicador de alunos tem uma lista (o detalhamento) e a contagem é o tamanho dela,
     * para o número do card e a lista sempre baterem. Itens: um por aluno.
     */

    /**
     * Alunos com matrícula vigente na data (mesmo critério de DashboardRepository::visaoGeral):
     * não pendente, já iniciada, não vencida e não cancelada até a data.
     */
    public function alunosAtivosEm(int $tenantId, string $ref): int
    {
        return count($this->alunosAtivosLista($tenantId, $ref));
    }

    /** @return list<array<string, mixed>> uma linha por aluno, com a matrícula vigente mais recente */
    public function alunosAtivosLista(int $tenantId, string $ref): array
    {
        $rows = $this->matriculasComAluno($tenantId)
            ->where('m.status_id', '<>', 5)
            ->where('m.data_inicio', '<=', $ref)
            ->where('m.data_vencimento', '>=', $ref)
            ->where(fn ($q) => $q->whereNull('m.data_cancelamento')->orWhere('m.data_cancelamento', '>', $ref))
            ->get();

        return self::umPorAluno($rows, fn ($r) => ['data' => (string) $r->data_vencimento, 'detalhe' => 'Acesso até']);
    }

    public function novosAlunos(int $tenantId, string $inicio, string $fim): int
    {
        return count($this->novosAlunosLista($tenantId, $inicio, $fim));
    }

    /**
     * Alunos cuja primeira matrícula (não pendente) na academia é do período.
     *
     * @return list<array<string, mixed>>
     */
    public function novosAlunosLista(int $tenantId, string $inicio, string $fim): array
    {
        $primeiras = DB::table('matriculas')
            ->where('tenant_id', $tenantId)->whereNotNull('aluno_id')->where('status_id', '<>', 5)
            ->groupBy('aluno_id')
            ->select('aluno_id', DB::raw('MIN(data_matricula) AS primeira'));

        $rows = $this->matriculasComAluno($tenantId)
            ->joinSub($primeiras, 'x', fn ($j) => $j->on('x.aluno_id', '=', 'm.aluno_id')->on('x.primeira', '=', 'm.data_matricula'))
            ->where('m.status_id', '<>', 5)
            ->whereBetween('x.primeira', [$inicio, $fim])
            ->get();

        return self::umPorAluno($rows, fn ($r) => ['data' => (string) $r->data_matricula, 'detalhe' => 'Primeira matrícula']);
    }

    public function saidas(int $tenantId, string $inicio, string $fim): int
    {
        return count($this->saidasLista($tenantId, $inicio, $fim));
    }

    /**
     * Alunos que saíram: matrícula cancelada no período, sem diárias, e sem outra matrícula do aluno
     * vigente logo depois (até JANELA_TROCA_DIAS), que seria troca de plano.
     *
     * @return list<array<string, mixed>>
     */
    public function saidasLista(int $tenantId, string $inicio, string $fim): array
    {
        $canceladas = $this->matriculasComAluno($tenantId)
            ->where('m.status_id', 3)
            ->whereBetween('m.data_cancelamento', [$inicio, $fim])
            ->where(fn ($q) => $q->whereNull('p.duracao_dias')->orWhere('p.duracao_dias', '>', 1))
            ->get();
        if ($canceladas->isEmpty()) {
            return [];
        }

        $outras = DB::table('matriculas')
            ->where('tenant_id', $tenantId)
            ->whereIn('aluno_id', $canceladas->pluck('aluno_id')->unique()->all())
            ->whereNotIn('status_id', [3, 5])
            ->get(['id', 'aluno_id', 'data_inicio', 'data_vencimento'])
            ->groupBy('aluno_id');

        $sairam = $canceladas->reject(function ($c) use ($outras) {
            $limite = date('Y-m-d', strtotime($c->data_cancelamento.' +'.self::JANELA_TROCA_DIAS.' days'));

            return ($outras[$c->aluno_id] ?? collect())->contains(fn ($n) => (int) $n->id !== (int) $c->id
                && (string) $n->data_inicio <= $limite && (string) $n->data_vencimento >= (string) $c->data_cancelamento);
        });

        return self::umPorAluno($sairam, fn ($r) => ['data' => (string) $r->data_cancelamento, 'detalhe' => 'Cancelada em']);
    }

    /** Matrículas da academia com aluno e plano (base das listas de alunos) */
    private function matriculasComAluno(int $tenantId): \Illuminate\Database\Query\Builder
    {
        return DB::table('matriculas as m')
            ->join('alunos as a', 'a.id', '=', 'm.aluno_id')
            ->leftJoin('planos as p', 'p.id', '=', 'm.plano_id')
            ->where('m.tenant_id', $tenantId)
            ->select(['m.id', 'm.aluno_id', 'm.data_matricula', 'm.data_inicio', 'm.data_vencimento', 'm.data_cancelamento',
                'a.nome AS aluno_nome', 'p.nome AS plano_nome']);
    }

    /**
     * Uma linha por aluno (a matrícula de maior id), ordenada por nome.
     *
     * @param  iterable<object>  $rows
     * @param  callable(object): array{data: string, detalhe: string}  $extra
     * @return list<array<string, mixed>>
     */
    private static function umPorAluno(iterable $rows, callable $extra): array
    {
        $por = [];
        foreach ($rows as $r) {
            $k = (int) $r->aluno_id;
            if (! isset($por[$k]) || (int) $r->id > $por[$k]->id) {
                $por[$k] = $r;
            }
        }
        $out = array_map(fn ($r) => [
            'aluno_id' => (int) $r->aluno_id,
            'aluno_nome' => (string) $r->aluno_nome,
            'plano_nome' => $r->plano_nome,
            'matricula_id' => (int) $r->id,
        ] + $extra($r), array_values($por));
        usort($out, fn ($a, $b) => strcmp($a['aluno_nome'], $b['aluno_nome']));

        return $out;
    }

    /**
     * Parcelas que compõem os valores: `recebido` (pagas pela data do pagamento), `vencido`
     * (aguardando/atrasadas que venceram no período antes de hoje) ou `a_vencer` (as que vencem de hoje
     * até o fim do período). Mesmos filtros de financeiro().
     *
     * @return list<array<string, mixed>>
     */
    public function parcelasLista(int $tenantId, string $tipo, string $inicio, string $fim, string $hoje): array
    {
        $q = DB::table('pagamentos_plano as pp')
            ->leftJoin('matriculas as m', 'm.id', '=', 'pp.matricula_id')
            ->leftJoin('alunos as a', 'a.id', '=', DB::raw('COALESCE(pp.aluno_id, m.aluno_id)'))
            ->leftJoin('planos as p', 'p.id', '=', 'm.plano_id')
            ->where('pp.tenant_id', $tenantId);
        if ($tipo === 'recebido') {
            $q->where('pp.status_pagamento_id', 2)->whereBetween('pp.data_pagamento', [$inicio, $fim])->orderByDesc('pp.data_pagamento');
        } elseif ($tipo === 'a_vencer') {
            $q->whereIn('pp.status_pagamento_id', [1, 3])->whereBetween('pp.data_vencimento', [$inicio, $fim])
                ->where('pp.data_vencimento', '>=', $hoje)->orderBy('pp.data_vencimento');
        } else {
            $q->whereIn('pp.status_pagamento_id', [1, 3])->whereBetween('pp.data_vencimento', [$inicio, $fim])
                ->where('pp.data_vencimento', '<', $hoje)->orderBy('pp.data_vencimento');
        }

        return $q->orderBy('pp.id')
            ->get(['pp.id', 'pp.matricula_id', 'pp.valor', 'pp.data_vencimento', 'pp.data_pagamento', 'a.id AS aluno_id', 'a.nome AS aluno_nome', 'p.nome AS plano_nome'])
            ->map(fn ($r) => [
                'parcela_id' => (int) $r->id,
                'aluno_id' => $r->aluno_id !== null ? (int) $r->aluno_id : null,
                'aluno_nome' => (string) ($r->aluno_nome ?? ''),
                'plano_nome' => $r->plano_nome,
                'matricula_id' => (int) $r->matricula_id,
                'valor' => round((float) $r->valor, 2),
                'data' => (string) ($tipo === 'recebido' ? $r->data_pagamento : $r->data_vencimento),
                'detalhe' => ['recebido' => 'Pago em', 'a_vencer' => 'Vence em'][$tipo] ?? 'Venceu em',
            ])->all();
    }

    /**
     * Recebido = parcelas pagas pela data do pagamento. Previsto = parcelas não canceladas que vencem
     * no período, separadas em pagas, a vencer e vencidas (aguardando ou atrasadas com vencimento antes de hoje).
     *
     * @return array{recebido: float, pagantes: int, previsto: float, previsto_pago: float, a_vencer: float, vencido: float, vencido_qtd: int}
     */
    public function financeiro(int $tenantId, string $inicio, string $fim, string $hoje): array
    {
        $rec = DB::selectOne('
            SELECT COALESCE(SUM(pp.valor), 0) AS total, COUNT(DISTINCT COALESCE(pp.aluno_id, m.aluno_id)) AS pagantes
            FROM pagamentos_plano pp LEFT JOIN matriculas m ON m.id = pp.matricula_id
            WHERE pp.tenant_id = ? AND pp.status_pagamento_id = 2 AND pp.data_pagamento BETWEEN ? AND ?
        ', [$tenantId, $inicio, $fim]);

        $prev = DB::selectOne('
            SELECT
                COALESCE(SUM(valor), 0) AS previsto,
                COALESCE(SUM(CASE WHEN status_pagamento_id = 2 THEN valor ELSE 0 END), 0) AS pago,
                COALESCE(SUM(CASE WHEN status_pagamento_id IN (1, 3) AND data_vencimento >= ? THEN valor ELSE 0 END), 0) AS a_vencer,
                COALESCE(SUM(CASE WHEN status_pagamento_id IN (1, 3) AND data_vencimento < ? THEN valor ELSE 0 END), 0) AS vencido,
                COALESCE(SUM(CASE WHEN status_pagamento_id IN (1, 3) AND data_vencimento < ? THEN 1 ELSE 0 END), 0) AS vencido_qtd
            FROM pagamentos_plano
            WHERE tenant_id = ? AND status_pagamento_id <> 4 AND data_vencimento BETWEEN ? AND ?
        ', [$hoje, $hoje, $hoje, $tenantId, $inicio, $fim]);

        return [
            'recebido' => round((float) $rec->total, 2),
            'pagantes' => (int) $rec->pagantes,
            'previsto' => round((float) $prev->previsto, 2),
            'previsto_pago' => round((float) $prev->pago, 2),
            'a_vencer' => round((float) $prev->a_vencer, 2),
            'vencido' => round((float) $prev->vencido, 2),
            'vencido_qtd' => (int) $prev->vencido_qtd,
        ];
    }

    /**
     * Recebido no período agrupado por plano ou por forma de pagamento, maior primeiro.
     * Plano: o da parcela, senão o da matrícula. `valor` já vem com desconto; `desconto` à parte.
     *
     * @return list<array{nome: string, valor: float, parcelas: int}>
     */
    public function recebidoPorGrupo(int $tenantId, string $grupo, string $inicio, string $fim): array
    {
        $q = DB::table('pagamentos_plano as pp')
            ->where('pp.tenant_id', $tenantId)->where('pp.status_pagamento_id', 2)
            ->whereBetween('pp.data_pagamento', [$inicio, $fim]);
        if ($grupo === 'plano') {
            $q->leftJoin('matriculas as m', 'm.id', '=', 'pp.matricula_id')
                ->leftJoin('planos as g', 'g.id', '=', DB::raw('COALESCE(pp.plano_id, m.plano_id)'));
            $vazio = 'Sem plano';
        } else {
            $q->leftJoin('formas_pagamento as g', 'g.id', '=', 'pp.forma_pagamento_id');
            $vazio = 'Não informada';
        }

        return $q->groupBy('g.id', 'g.nome')
            ->get([DB::raw('g.nome AS nome'), DB::raw('SUM(pp.valor) AS valor'), DB::raw('COUNT(*) AS parcelas')])
            ->map(fn ($r) => ['nome' => (string) ($r->nome ?? $vazio), 'valor' => round((float) $r->valor, 2), 'parcelas' => (int) $r->parcelas])
            ->sortByDesc('valor')->values()->all();
    }

    /** Descontos das parcelas pagas no período (pela data do pagamento) */
    public function descontosRecebidos(int $tenantId, string $inicio, string $fim): float
    {
        return round((float) (DB::table('pagamentos_plano')
            ->where('tenant_id', $tenantId)->where('status_pagamento_id', 2)
            ->whereBetween('data_pagamento', [$inicio, $fim])
            ->sum('desconto')), 2);
    }

    /**
     * Mês a mês no intervalo: previsto, a vencer e vencido pelo mês do vencimento; recebido e pagantes
     * pelo mês do pagamento (mesmos critérios de financeiro()).
     *
     * @return array<string, array{previsto: float, a_vencer: float, vencido: float, recebido: float, pagantes: int}>
     */
    public function financeiroPorMes(int $tenantId, string $inicio, string $fim, string $hoje): array
    {
        $out = [];
        $mes = function (string $ym) use (&$out) {
            $out[$ym] ??= ['previsto' => 0.0, 'a_vencer' => 0.0, 'vencido' => 0.0, 'recebido' => 0.0, 'pagantes' => []];
        };

        foreach (DB::table('pagamentos_plano')->where('tenant_id', $tenantId)->where('status_pagamento_id', '<>', 4)
            ->whereBetween('data_vencimento', [$inicio, $fim])->get(['data_vencimento', 'valor', 'status_pagamento_id']) as $r) {
            $ym = substr((string) $r->data_vencimento, 0, 7);
            $mes($ym);
            $out[$ym]['previsto'] += (float) $r->valor;
            if (in_array((int) $r->status_pagamento_id, [1, 3], true)) {
                $out[$ym][(string) $r->data_vencimento < $hoje ? 'vencido' : 'a_vencer'] += (float) $r->valor;
            }
        }

        foreach (DB::table('pagamentos_plano as pp')->leftJoin('matriculas as m', 'm.id', '=', 'pp.matricula_id')
            ->where('pp.tenant_id', $tenantId)->where('pp.status_pagamento_id', 2)
            ->whereBetween('pp.data_pagamento', [$inicio, $fim])
            ->get(['pp.data_pagamento', 'pp.valor', DB::raw('COALESCE(pp.aluno_id, m.aluno_id) AS aluno')]) as $r) {
            $ym = substr((string) $r->data_pagamento, 0, 7);
            $mes($ym);
            $out[$ym]['recebido'] += (float) $r->valor;
            if ($r->aluno !== null) {
                $out[$ym]['pagantes'][(int) $r->aluno] = true;
            }
        }

        return array_map(fn ($m) => [
            'previsto' => round($m['previsto'], 2), 'a_vencer' => round($m['a_vencer'], 2), 'vencido' => round($m['vencido'], 2),
            'recebido' => round($m['recebido'], 2), 'pagantes' => count($m['pagantes']),
        ], $out);
    }

    /** @return array<string, float> data => valor recebido no dia */
    public function recebidoPorDia(int $tenantId, string $inicio, string $fim): array
    {
        $out = [];
        foreach (DB::select('
            SELECT data_pagamento AS dia, SUM(valor) AS total FROM pagamentos_plano
            WHERE tenant_id = ? AND status_pagamento_id = 2 AND data_pagamento BETWEEN ? AND ?
            GROUP BY data_pagamento
        ', [$tenantId, $inicio, $fim]) as $r) {
            $out[substr((string) $r->dia, 0, 10)] = (float) $r->total;
        }

        return $out;
    }

    /** @return array<string, float> data => valor previsto (não cancelado) que vence no dia */
    public function previstoPorDia(int $tenantId, string $inicio, string $fim): array
    {
        $out = [];
        foreach (DB::select('
            SELECT data_vencimento AS dia, SUM(valor) AS total FROM pagamentos_plano
            WHERE tenant_id = ? AND status_pagamento_id <> 4 AND data_vencimento BETWEEN ? AND ?
            GROUP BY data_vencimento
        ', [$tenantId, $inicio, $fim]) as $r) {
            $out[substr((string) $r->dia, 0, 10)] = (float) $r->total;
        }

        return $out;
    }

    /**
     * Check-ins por dia da aula (sem aula vinculada, o dia do check-in), sem os marcados como ausente.
     *
     * @return array<string, int>
     */
    public function checkinsPorDia(int $tenantId, string $inicio, string $fim): array
    {
        $dia = 'DATE(COALESCE(d.data, c.data_checkin_date, c.data_checkin))';
        $out = [];
        foreach (DB::select("
            SELECT {$dia} AS dia, COUNT(*) AS total
            FROM checkins c LEFT JOIN turmas t ON t.id = c.turma_id LEFT JOIN dias d ON d.id = t.dia_id
            WHERE c.tenant_id = ? AND (c.presente IS NULL OR c.presente = 1) AND {$dia} BETWEEN ? AND ?
            GROUP BY {$dia}
        ", [$tenantId, $inicio, $fim]) as $r) {
            $out[substr((string) $r->dia, 0, 10)] = (int) $r->total;
        }

        return $out;
    }
}
