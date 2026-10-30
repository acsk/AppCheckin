<?php

namespace App\Services\Bi;

use App\Repositories\Bi\BiTenantRepository;
use App\Support\AcademyDateTime;
use App\Support\Bi\Periodo;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * BI da academia — Retenção e evasão: o que aconteceu com quem estava ativo no início do período
 * (continuou, cancelou, não renovou), motivos das saídas, recuperação pela Prospecção e quem está em risco.
 */
class BiRetencaoService
{
    private const CACHE_SEGUNDOS = 300;

    /** Dias depois do fim do acesso em que o aluno ainda conta como "renovação atrasada", não como evasão */
    public const TOLERANCIA_RENOVACAO = 7;

    public function __construct(
        private readonly BiTenantRepository $repo,
        private readonly BiFrequenciaService $frequencia,
    ) {}

    /** @return array{status: int, body: array<string, mixed>} */
    public function retencao(?int $tenantId, ?string $preset, ?string $hoje = null): array
    {
        if (! $tenantId) {
            return ['status' => 400, 'body' => ['success' => false, 'error' => 'Academia não identificada']];
        }
        $hoje = $hoje ?: AcademyDateTime::today();
        $periodo = Periodo::resolver($preset, $hoje);

        try {
            $data = Cache::remember(
                "bi:tenant:{$tenantId}:retencao:{$periodo->preset}:{$hoje}",
                self::CACHE_SEGUNDOS,
                fn () => $this->montar($tenantId, $periodo),
            );

            return ['status' => 200, 'body' => ['success' => true, 'data' => $data]];
        } catch (Throwable $e) {
            error_log('[BiRetencaoService::retencao] '.$e->getMessage());

            return ['status' => 500, 'body' => ['success' => false, 'error' => 'Não foi possível calcular a retenção']];
        }
    }

    /**
     * Ativos na véspera do início, separados pelo que aconteceu até o fim: continuam (ativos no fim),
     * cancelaram (alguma matrícula cancelada no período), renovacao_atrasada (acesso acabou há até
     * TOLERANCIA_RENOVACAO dias) e nao_renovaram (o resto). As quatro listas somam os ativos do início.
     *
     * @return array{ativos_inicio: int, continuam: list<array>, cancelaram: list<array>, nao_renovaram: list<array>, renovacao_atrasada: list<array>}
     */
    public function composicao(int $t, string $inicio, string $fim): array
    {
        $ativosInicio = $this->repo->alunosAtivosLista($t, date('Y-m-d', strtotime($inicio.' -1 day')));
        $ativosFim = array_fill_keys(array_column($this->repo->alunosAtivosLista($t, $fim), 'aluno_id'), true);
        $cancelaram = $this->repo->alunosComCancelamento($t, $inicio, $fim);
        $ultimo = $this->repo->ultimoAcesso($t, array_column($ativosInicio, 'aluno_id'));
        $limite = date('Y-m-d', strtotime($fim.' -'.self::TOLERANCIA_RENOVACAO.' days'));

        $out = ['ativos_inicio' => count($ativosInicio), 'continuam' => [], 'cancelaram' => [], 'nao_renovaram' => [], 'renovacao_atrasada' => []];
        foreach ($ativosInicio as $a) {
            $id = $a['aluno_id'];
            $acesso = $ultimo[$id] ?? $a['data'];
            if (isset($ativosFim[$id])) {
                $out['continuam'][] = $a;
            } elseif (isset($cancelaram[$id])) {
                $out['cancelaram'][] = ['data' => $cancelaram[$id], 'detalhe' => 'Cancelou em'] + $a;
            } elseif ($acesso >= $limite) {
                $out['renovacao_atrasada'][] = ['data' => $acesso, 'detalhe' => 'Acesso venceu em'] + $a;
            } else {
                $out['nao_renovaram'][] = ['data' => $acesso, 'detalhe' => 'Acesso venceu em'] + $a;
            }
        }

        return $out;
    }

    /** @return array<string, mixed> */
    private function montar(int $t, Periodo $p): array
    {
        $c = $this->composicao($t, $p->inicio, $p->fim);
        $ant = $this->composicao($t, $p->anteriorInicio, $p->anteriorFim);
        $par = fn ($atual, $anterior) => ['atual' => $atual, 'anterior' => $anterior];
        $n = fn (array $x, string $k) => count($x[$k]);
        $evasao = fn (array $x) => self::pct($n($x, 'cancelaram') + $n($x, 'nao_renovaram'), $x['ativos_inicio']);
        $retencao = fn (array $x) => self::pct($n($x, 'continuam'), $x['ativos_inicio']);

        $recuperados = count($this->repo->recuperadosLista($t, $p->inicio, $p->fim));
        $recuperadosAnt = count($this->repo->recuperadosLista($t, $p->anteriorInicio, $p->anteriorFim));
        $funil = $this->repo->funilProspeccao($t, $p->inicio, $p->fim);
        $atencao = $this->frequencia->alunosEmAtencao($t, $p->fim);
        $emRisco = count(array_unique(array_merge(array_column($atencao['sem_checkin'], 'aluno_id'), array_column($atencao['em_queda'], 'aluno_id'))));

        return [
            'periodo' => $p->toArray(),
            'indicadores' => [
                'ativos_inicio' => $par($c['ativos_inicio'], $ant['ativos_inicio']),
                'retencao' => $par($retencao($c), $retencao($ant)),
                'evasao' => $par($evasao($c), $evasao($ant)),
                'cancelaram' => $par($n($c, 'cancelaram'), $n($ant, 'cancelaram')),
                'nao_renovaram' => $par($n($c, 'nao_renovaram'), $n($ant, 'nao_renovaram')),
                'renovacao_atrasada' => $par($n($c, 'renovacao_atrasada'), null),
                'recuperados' => $par($recuperados, $recuperadosAnt),
                'em_risco' => $par($emRisco, null),
            ],
            'composicao' => [
                'ativos_inicio' => $c['ativos_inicio'],
                'continuam' => $n($c, 'continuam'),
                'cancelaram' => $n($c, 'cancelaram'),
                'nao_renovaram' => $n($c, 'nao_renovaram'),
                'renovacao_atrasada' => $n($c, 'renovacao_atrasada'),
            ],
            'motivos' => $this->motivos($t, $p),
            'funil' => $funil + ['taxa_recuperacao' => self::pct($funil['recuperadas'], $funil['criadas'])],
            'risco' => ['sem_checkin' => count($atencao['sem_checkin']), 'em_queda' => count($atencao['em_queda'])],
            'mensal' => $this->mensal($t, $p->fim),
        ];
    }

    /** Saídas do período por motivo do cancelamento, mais frequente primeiro */
    private function motivos(int $t, Periodo $p): array
    {
        $por = [];
        foreach ($this->repo->saidasLista($t, $p->inicio, $p->fim) as $s) {
            $k = $s['motivo_codigo'] ?? '';
            $por[$k] ??= ['codigo' => $s['motivo_codigo'], 'nome' => $s['motivo'], 'alunos' => 0];
            $por[$k]['alunos']++;
        }
        usort($por, fn ($a, $b) => [$b['alunos'], $a['nome']] <=> [$a['alunos'], $b['nome']]);

        return array_values($por);
    }

    /** Doze meses terminando no mês do fim do período (o mês corrente vai até o fim do período) */
    private function mensal(int $t, string $fim): array
    {
        $ini = date('Y-m-01', strtotime(substr($fim, 0, 7).'-01 -11 months'));
        $out = [];
        for ($i = 0; $i < 12; $i++) {
            $mIni = date('Y-m-d', strtotime($ini." +{$i} months"));
            $mFim = min(date('Y-m-t', strtotime($mIni)), $fim);
            $c = $this->composicao($t, $mIni, $mFim);
            $sairam = count($c['cancelaram']) + count($c['nao_renovaram']);
            $out[] = [
                'mes' => substr($mIni, 0, 7),
                'ativos_inicio' => $c['ativos_inicio'],
                'cancelaram' => count($c['cancelaram']),
                'nao_renovaram' => count($c['nao_renovaram']),
                'recuperados' => count($this->repo->recuperadosLista($t, $mIni, $mFim)),
                'evasao' => self::pct($sairam, $c['ativos_inicio']),
            ];
        }

        return $out;
    }

    private static function pct(int $parte, int $total): ?float
    {
        return $total > 0 ? round($parte * 100 / $total, 1) : null;
    }
}
