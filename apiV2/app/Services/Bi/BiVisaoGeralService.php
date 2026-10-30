<?php

namespace App\Services\Bi;

use App\Repositories\Bi\BiTenantRepository;
use App\Support\AcademyDateTime;
use App\Support\Bi\Periodo;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * BI da academia — Visão geral: indicadores do período com o período anterior equivalente e as séries
 * dos gráficos. Fórmulas em BiTenantRepository; aqui só a composição.
 */
class BiVisaoGeralService
{
    private const CACHE_SEGUNDOS = 300;

    /** Acima disso a série de check-ins vem por mês, não por dia */
    private const MAX_DIAS_SERIE_DIARIA = 62;

    public function __construct(
        private readonly BiTenantRepository $repo,
    ) {}

    /** @return array{status: int, body: array<string, mixed>} */
    public function visaoGeral(?int $tenantId, ?string $preset, ?string $hoje = null): array
    {
        if (! $tenantId) {
            return ['status' => 400, 'body' => ['success' => false, 'error' => 'Academia não identificada']];
        }
        $hoje = $hoje ?: AcademyDateTime::today();
        $periodo = Periodo::resolver($preset, $hoje);

        try {
            $data = Cache::remember(
                "bi:tenant:{$tenantId}:visao-geral:{$periodo->preset}:{$hoje}",
                self::CACHE_SEGUNDOS,
                fn () => $this->montar($tenantId, $periodo, $hoje),
            );

            return ['status' => 200, 'body' => ['success' => true, 'data' => $data]];
        } catch (Throwable $e) {
            error_log('[BiVisaoGeralService::visaoGeral] '.$e->getMessage());

            return ['status' => 500, 'body' => ['success' => false, 'error' => 'Não foi possível calcular os indicadores']];
        }
    }

    /** Indicadores que podem ser abertos em lista */
    public const DETALHAVEIS = ['alunos_ativos', 'novos_alunos', 'saidas', 'receita_recebida', 'valor_vencido', 'a_vencer'];

    /** Teto de itens devolvidos no detalhamento (o total continua sendo o do período inteiro) */
    private const MAX_ITENS = 1000;

    /**
     * Registros que compõem um indicador no período atual. O total bate com o card da Visão geral.
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    public function detalhe(?int $tenantId, ?string $indicador, ?string $preset, ?string $hoje = null): array
    {
        if (! $tenantId) {
            return ['status' => 400, 'body' => ['success' => false, 'error' => 'Academia não identificada']];
        }
        if (! in_array($indicador, self::DETALHAVEIS, true)) {
            return ['status' => 422, 'body' => ['success' => false, 'error' => 'Indicador sem detalhamento']];
        }
        $hoje = $hoje ?: AcademyDateTime::today();
        $p = Periodo::resolver($preset, $hoje);

        try {
            $itens = match ($indicador) {
                'alunos_ativos' => $this->repo->alunosAtivosLista($tenantId, $p->fim),
                'novos_alunos' => $this->repo->novosAlunosLista($tenantId, $p->inicio, $p->fim),
                'saidas' => $this->repo->saidasLista($tenantId, $p->inicio, $p->fim),
                'receita_recebida' => $this->repo->parcelasLista($tenantId, 'recebido', $p->inicio, $p->fim, $hoje),
                'valor_vencido' => $this->repo->parcelasLista($tenantId, 'vencido', $p->inicio, $p->fim, $hoje),
                'a_vencer' => $this->repo->parcelasLista($tenantId, 'a_vencer', $p->inicio, $p->fim, $hoje),
            };
            $valores = in_array($indicador, ['receita_recebida', 'valor_vencido', 'a_vencer'], true);

            return ['status' => 200, 'body' => ['success' => true, 'data' => [
                'indicador' => $indicador,
                'periodo' => $p->toArray(),
                'quantidade' => count($itens),
                'valor_total' => $valores ? round(array_sum(array_column($itens, 'valor')), 2) : null,
                'truncado' => count($itens) > self::MAX_ITENS,
                'itens' => array_slice($itens, 0, self::MAX_ITENS),
            ]]];
        } catch (Throwable $e) {
            error_log('[BiVisaoGeralService::detalhe] '.$e->getMessage());

            return ['status' => 500, 'body' => ['success' => false, 'error' => 'Não foi possível carregar a lista']];
        }
    }

    /** @return array<string, mixed> */
    private function montar(int $tenantId, Periodo $p, string $hoje): array
    {
        $fin = $this->repo->financeiro($tenantId, $p->inicio, $p->fim, $hoje);
        $finAnt = $this->repo->financeiro($tenantId, $p->anteriorInicio, $p->anteriorFim, $hoje);
        $checkins = $this->repo->checkinsPorDia($tenantId, $p->inicio, $p->fim);
        $checkinsAnt = $this->repo->checkinsPorDia($tenantId, $p->anteriorInicio, $p->anteriorFim);

        $par = fn ($atual, $anterior) => ['atual' => $atual, 'anterior' => $anterior];

        return [
            'periodo' => $p->toArray(),
            'indicadores' => [
                'alunos_ativos' => $par($this->repo->alunosAtivosEm($tenantId, $p->fim), $this->repo->alunosAtivosEm($tenantId, $p->anteriorFim)),
                'novos_alunos' => $par($this->repo->novosAlunos($tenantId, $p->inicio, $p->fim), $this->repo->novosAlunos($tenantId, $p->anteriorInicio, $p->anteriorFim)),
                'saidas' => $par($this->repo->saidas($tenantId, $p->inicio, $p->fim), $this->repo->saidas($tenantId, $p->anteriorInicio, $p->anteriorFim)),
                'receita_recebida' => $par($fin['recebido'], $finAnt['recebido']),
                'receita_prevista' => $par($fin['previsto'], $finAnt['previsto']),
                'valor_vencido' => $par($fin['vencido'], $finAnt['vencido']),
                'inadimplencia' => $par(self::pct($fin['vencido'], $fin['previsto']), self::pct($finAnt['vencido'], $finAnt['previsto'])),
                'ticket_medio' => $par(self::div($fin['recebido'], $fin['pagantes']), self::div($finAnt['recebido'], $finAnt['pagantes'])),
                'checkins' => $par(array_sum($checkins), array_sum($checkinsAnt)),
            ],
            'parcelas_periodo' => [
                'previsto' => $fin['previsto'],
                'pago' => $fin['previsto_pago'],
                'a_vencer' => $fin['a_vencer'],
                'vencido' => $fin['vencido'],
                'vencido_qtd' => $fin['vencido_qtd'],
            ],
            'receita_mensal' => $this->receitaMensal($tenantId, $p->fim),
            'alunos_ativos_mensal' => $this->ativosMensal($tenantId, $p->fim),
            'checkins_serie' => $this->serieCheckins($checkins, $p),
        ];
    }

    /** Doze meses terminando no mês do fim do período: previsto pelo vencimento, recebido pelo pagamento */
    private function receitaMensal(int $tenantId, string $fim): array
    {
        $ini = date('Y-m-01', strtotime(substr($fim, 0, 7).'-01 -11 months'));
        $ate = date('Y-m-t', strtotime($fim));
        $rec = self::porMes($this->repo->recebidoPorDia($tenantId, $ini, $ate));
        $prev = self::porMes($this->repo->previstoPorDia($tenantId, $ini, $ate));

        $out = [];
        for ($i = 0; $i < 12; $i++) {
            $mes = date('Y-m', strtotime($ini." +{$i} months"));
            $out[] = ['mes' => $mes, 'previsto' => round($prev[$mes] ?? 0, 2), 'recebido' => round($rec[$mes] ?? 0, 2)];
        }

        return $out;
    }

    /** Alunos ativos no fim de cada um dos 5 meses anteriores e no fim do período */
    private function ativosMensal(int $tenantId, string $fim): array
    {
        $out = [];
        for ($i = 5; $i >= 1; $i--) {
            $ref = date('Y-m-t', strtotime(substr($fim, 0, 7)."-01 -{$i} months"));
            $out[] = ['data' => $ref, 'total' => $this->repo->alunosAtivosEm($tenantId, $ref)];
        }
        $out[] = ['data' => $fim, 'total' => $this->repo->alunosAtivosEm($tenantId, $fim)];

        return $out;
    }

    /** @param  array<string, int>  $porDia */
    private function serieCheckins(array $porDia, Periodo $p): array
    {
        if ($p->dias() <= self::MAX_DIAS_SERIE_DIARIA) {
            $pontos = [];
            for ($d = $p->inicio; $d <= $p->fim; $d = date('Y-m-d', strtotime($d.' +1 day'))) {
                $pontos[] = ['data' => $d, 'total' => $porDia[$d] ?? 0];
            }

            return ['agrupamento' => 'dia', 'pontos' => $pontos];
        }

        $meses = self::porMes($porDia);
        $pontos = [];
        for ($m = substr($p->inicio, 0, 7); $m <= substr($p->fim, 0, 7); $m = date('Y-m', strtotime($m.'-01 +1 month'))) {
            $pontos[] = ['data' => $m, 'total' => (int) ($meses[$m] ?? 0)];
        }

        return ['agrupamento' => 'mes', 'pontos' => $pontos];
    }

    /**
     * @param  array<string, int|float>  $porDia
     * @return array<string, float>
     */
    private static function porMes(array $porDia): array
    {
        $out = [];
        foreach ($porDia as $dia => $v) {
            $out[substr($dia, 0, 7)] = ($out[substr($dia, 0, 7)] ?? 0) + $v;
        }

        return $out;
    }

    private static function pct(float $parte, float $total): ?float
    {
        return $total > 0 ? round($parte / $total * 100, 1) : null;
    }

    private static function div(float $a, int $b): ?float
    {
        return $b > 0 ? round($a / $b, 2) : null;
    }
}
