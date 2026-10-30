<?php

namespace App\Services\Bi;

use App\Repositories\Bi\BiTenantRepository;
use App\Support\AcademyDateTime;
use App\Support\Bi\Periodo;
use App\Support\Bi\Serie;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * BI da academia — Frequência e ocupação: check-ins, participação, ocupação das aulas com limite,
 * mapa de horários, turmas e professores, e alunos ativos sem check-in ou com frequência em queda.
 */
class BiFrequenciaService
{
    private const CACHE_SEGUNDOS = 300;

    /** Sem check-in há este número de dias (ou nunca) = alerta; matrícula mais nova que isso fica de fora */
    public const DIAS_SEM_CHECKIN = 14;

    /** Janela recente e base (dias) para comparar a frequência do próprio aluno */
    public const JANELA_RECENTE = 28;

    public const JANELA_BASE = 84;

    /** Recente abaixo desta fração da média da base = queda */
    public const LIMITE_QUEDA = 0.6;

    /** Check-ins mínimos na base para avaliar queda (sem histórico não há o que comparar) */
    public const MIN_CHECKINS_BASE = 4;

    public function __construct(
        private readonly BiTenantRepository $repo,
    ) {}

    /** @return array{status: int, body: array<string, mixed>} */
    public function frequencia(?int $tenantId, ?string $preset, ?string $hoje = null): array
    {
        if (! $tenantId) {
            return ['status' => 400, 'body' => ['success' => false, 'error' => 'Academia não identificada']];
        }
        $hoje = $hoje ?: AcademyDateTime::today();
        $periodo = Periodo::resolver($preset, $hoje);

        try {
            $data = Cache::remember(
                "bi:tenant:{$tenantId}:frequencia:{$periodo->preset}:{$hoje}",
                self::CACHE_SEGUNDOS,
                fn () => $this->montar($tenantId, $periodo),
            );

            return ['status' => 200, 'body' => ['success' => true, 'data' => $data]];
        } catch (Throwable $e) {
            error_log('[BiFrequenciaService::frequencia] '.$e->getMessage());

            return ['status' => 500, 'body' => ['success' => false, 'error' => 'Não foi possível calcular a frequência']];
        }
    }

    /**
     * Alunos ativos no fim do período que pedem atenção pela frequência.
     *
     * @return array{sem_checkin: list<array<string, mixed>>, em_queda: list<array<string, mixed>>}
     */
    public function alunosEmAtencao(int $tenantId, string $fim): array
    {
        $ativos = $this->repo->alunosAtivosLista($tenantId, $fim);
        $iniBase = self::menos($fim, self::JANELA_RECENTE + self::JANELA_BASE - 1);
        $iniRecente = self::menos($fim, self::JANELA_RECENTE - 1);
        $limiteSem = self::menos($fim, self::DIAS_SEM_CHECKIN);
        $c = $this->repo->checkinsDosAlunos($tenantId, array_column($ativos, 'aluno_id'), $iniBase, $fim);

        $sem = [];
        $queda = [];
        foreach ($ativos as $a) {
            $id = $a['aluno_id'];
            $ultimo = $c['ultimo'][$id] ?? null;
            $base = ['aluno_id' => $id, 'aluno_nome' => $a['aluno_nome'], 'plano_nome' => $a['plano_nome'],
                'modalidade_nome' => $a['modalidade_nome'], 'matricula_id' => $a['matricula_id'], 'data' => $ultimo];

            if (($ultimo === null || $ultimo <= $limiteSem) && $a['matricula_inicio'] <= $limiteSem) {
                $dias = $ultimo ? (int) (new \DateTime($ultimo))->diff(new \DateTime($fim))->days : null;
                $sem[] = $base + ['dias_sem_checkin' => $dias, 'detalhe' => $dias === null ? 'Nunca fez check-in' : "Último check-in, há {$dias} dias"];

                continue;
            }

            $recente = 0;
            $antes = 0;
            foreach ($c['por_dia'][$id] ?? [] as $dia => $n) {
                $dia >= $iniRecente ? $recente += $n : $antes += $n;
            }
            $semanaRecente = $recente / (self::JANELA_RECENTE / 7);
            $semanaBase = $antes / (self::JANELA_BASE / 7);
            if ($antes >= self::MIN_CHECKINS_BASE && $semanaRecente < self::LIMITE_QUEDA * $semanaBase) {
                $queda[] = $base + [
                    'semana_recente' => round($semanaRecente, 1),
                    'semana_antes' => round($semanaBase, 1),
                    'variacao' => round(($semanaRecente - $semanaBase) / $semanaBase * 100),
                    'detalhe' => sprintf('%sx por semana, antes %sx', self::fmt($semanaRecente), self::fmt($semanaBase)),
                ];
            }
        }
        usort($sem, fn ($x, $y) => ($y['dias_sem_checkin'] ?? PHP_INT_MAX) <=> ($x['dias_sem_checkin'] ?? PHP_INT_MAX));
        usort($queda, fn ($x, $y) => $x['variacao'] <=> $y['variacao']);

        return ['sem_checkin' => $sem, 'em_queda' => $queda];
    }

    /** @return array<string, mixed> */
    private function montar(int $t, Periodo $p): array
    {
        $porDia = $this->repo->checkinsPorDia($t, $p->inicio, $p->fim);
        $checkins = array_sum($porDia);
        $checkinsAnt = array_sum($this->repo->checkinsPorDia($t, $p->anteriorInicio, $p->anteriorFim));
        $part = $this->repo->participantes($t, $p->inicio, $p->fim);
        $partAnt = $this->repo->participantes($t, $p->anteriorInicio, $p->anteriorFim);
        $ativos = $this->repo->alunosAtivosEm($t, $p->fim);
        $aulas = $this->repo->aulas($t, $p->inicio, $p->fim);
        $aulasAnt = $this->repo->aulas($t, $p->anteriorInicio, $p->anteriorFim);
        $atencao = $this->alunosEmAtencao($t, $p->fim);
        $semanas = $p->dias() / 7;
        $par = fn ($atual, $anterior) => ['atual' => $atual, 'anterior' => $anterior];

        return [
            'periodo' => $p->toArray(),
            'indicadores' => [
                'checkins' => $par($checkins, $checkinsAnt),
                'participantes' => $par($part, $partAnt),
                'participacao' => $par($ativos ? round(min($part, $ativos) / $ativos * 100, 1) : null, null),
                'frequencia_semanal' => $par($part ? round($checkins / $part / $semanas, 1) : null, $partAnt ? round($checkinsAnt / $partAnt / $semanas, 1) : null),
                'ocupacao' => $par(self::ocupacao($aulas), self::ocupacao($aulasAnt)),
                'sem_checkin' => $par(count($atencao['sem_checkin']), null),
                'em_queda' => $par(count($atencao['em_queda']), null),
            ],
            'checkins_serie' => Serie::diariaOuMensal($porDia, $p),
            'mapa' => $this->mapa($this->repo->checkinsPorDiaHora($t, $p->inicio, $p->fim), $p),
            'turmas' => $this->turmas($aulas),
            'por_professor' => $this->professores($aulas),
            'atencao' => [
                'sem_checkin' => array_slice($atencao['sem_checkin'], 0, 8),
                'em_queda' => array_slice($atencao['em_queda'], 0, 8),
            ],
        ];
    }

    /** Média da ocupação das aulas com limite (cada aula pesa igual). Null sem aula com limite. */
    private static function ocupacao(array $aulas): ?float
    {
        $taxas = array_map(fn ($a) => $a['checkins'] / $a['limite'], array_filter($aulas, fn ($a) => $a['limite']));

        return $taxas ? round(array_sum($taxas) / count($taxas) * 100, 1) : null;
    }

    /**
     * Média de check-ins por dia da semana (1 = segunda) e hora: total ÷ quantas vezes o dia da semana
     * aparece no período.
     */
    private function mapa(array $linhas, Periodo $p): array
    {
        $ocorrencias = array_fill(1, 7, 0);
        for ($d = $p->inicio; $d <= $p->fim; $d = self::mais($d, 1)) {
            $ocorrencias[(int) date('N', strtotime($d))]++;
        }
        $soma = [];
        foreach ($linhas as $l) {
            $k = date('N', strtotime($l['dia'])).'-'.$l['hora'];
            $soma[$k] = ($soma[$k] ?? 0) + $l['total'];
        }
        $out = [];
        foreach ($soma as $k => $total) {
            [$dia, $hora] = array_map('intval', explode('-', $k));
            $out[] = ['dia_semana' => $dia, 'hora' => $hora, 'media' => round($total / max(1, $ocorrencias[$dia]), 1), 'total' => $total];
        }
        usort($out, fn ($a, $b) => [$a['dia_semana'], $a['hora']] <=> [$b['dia_semana'], $b['hora']]);

        return $out;
    }

    /** Turmas = modalidade + horário + professor, somando as aulas do período */
    private function turmas(array $aulas): array
    {
        $g = [];
        foreach ($aulas as $a) {
            $k = ($a['modalidade_id'] ?? 0).'|'.$a['horario'].'|'.($a['professor_id'] ?? 0);
            $g[$k] ??= ['modalidade' => $a['modalidade'] ?? 'Sem modalidade', 'horario' => $a['horario'], 'professor' => $a['professor'],
                'aulas' => 0, 'checkins' => 0, 'limite' => null, 'taxas' => []];
            $g[$k]['aulas']++;
            $g[$k]['checkins'] += $a['checkins'];
            if ($a['limite']) {
                $g[$k]['limite'] = max($g[$k]['limite'] ?? 0, $a['limite']);
                $g[$k]['taxas'][] = $a['checkins'] / $a['limite'];
            }
        }
        $out = array_map(fn ($x) => [
            'modalidade' => $x['modalidade'], 'horario' => $x['horario'], 'professor' => $x['professor'],
            'aulas' => $x['aulas'], 'checkins' => $x['checkins'],
            'media_por_aula' => round($x['checkins'] / $x['aulas'], 1),
            'limite' => $x['limite'],
            'ocupacao' => $x['taxas'] ? round(array_sum($x['taxas']) / count($x['taxas']) * 100, 1) : null,
        ], array_values($g));
        usort($out, fn ($a, $b) => [$b['ocupacao'] ?? -1, $b['media_por_aula']] <=> [$a['ocupacao'] ?? -1, $a['media_por_aula']]);

        return $out;
    }

    private function professores(array $aulas): array
    {
        $g = [];
        foreach ($aulas as $a) {
            $k = $a['professor_id'] ?? 0;
            $g[$k] ??= ['nome' => $a['professor'] ?? 'Sem professor', 'aulas' => 0, 'checkins' => 0];
            $g[$k]['aulas']++;
            $g[$k]['checkins'] += $a['checkins'];
        }
        $out = array_map(fn ($x) => $x + ['media_por_aula' => round($x['checkins'] / $x['aulas'], 1)], array_values($g));
        usort($out, fn ($a, $b) => $b['checkins'] <=> $a['checkins']);

        return $out;
    }

    private static function menos(string $d, int $dias): string
    {
        return date('Y-m-d', strtotime($d." -{$dias} days"));
    }

    private static function mais(string $d, int $dias): string
    {
        return date('Y-m-d', strtotime($d." +{$dias} days"));
    }

    private static function fmt(float $v): string
    {
        return str_replace('.', ',', (string) round($v, 1));
    }
}
