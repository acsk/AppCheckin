<?php

namespace App\Services\Bi;

use App\Repositories\Bi\BiTenantRepository;
use App\Support\AcademyDateTime;
use App\Support\Bi\Periodo;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * BI da academia — Alunos e matrículas: entradas, retornos e saídas, retenção, permanência de quem saiu,
 * distribuição por plano e modalidade e o histórico mensal. Fórmulas em BiTenantRepository.
 */
class BiAlunosService
{
    private const CACHE_SEGUNDOS = 300;

    /** Faixas de permanência (meses, limite superior exclusivo) */
    private const FAIXAS_PERMANENCIA = [[1, 'Menos de 1 mês'], [3, '1 a 3 meses'], [6, '3 a 6 meses'], [12, '6 a 12 meses'], [PHP_INT_MAX, 'Mais de 1 ano']];

    private const DIAS_MES = 30.44;

    public function __construct(
        private readonly BiTenantRepository $repo,
    ) {}

    /** @return array{status: int, body: array<string, mixed>} */
    public function alunos(?int $tenantId, ?string $preset, ?string $hoje = null): array
    {
        if (! $tenantId) {
            return ['status' => 400, 'body' => ['success' => false, 'error' => 'Academia não identificada']];
        }
        $hoje = $hoje ?: AcademyDateTime::today();
        $periodo = Periodo::resolver($preset, $hoje);

        try {
            $data = Cache::remember(
                "bi:tenant:{$tenantId}:alunos:{$periodo->preset}:{$hoje}",
                self::CACHE_SEGUNDOS,
                fn () => $this->montar($tenantId, $periodo),
            );

            return ['status' => 200, 'body' => ['success' => true, 'data' => $data]];
        } catch (Throwable $e) {
            error_log('[BiAlunosService::alunos] '.$e->getMessage());

            return ['status' => 500, 'body' => ['success' => false, 'error' => 'Não foi possível calcular os indicadores de alunos']];
        }
    }

    /** @return array<string, mixed> */
    private function montar(int $t, Periodo $p): array
    {
        $ativos = $this->repo->alunosAtivosLista($t, $p->fim);
        $saidas = $this->repo->saidasLista($t, $p->inicio, $p->fim);
        $par = fn ($atual, $anterior) => ['atual' => $atual, 'anterior' => $anterior];

        $novos = $this->repo->novosAlunos($t, $p->inicio, $p->fim);
        $novosAnt = $this->repo->novosAlunos($t, $p->anteriorInicio, $p->anteriorFim);
        $reat = $this->repo->reativados($t, $p->inicio, $p->fim);
        $reatAnt = $this->repo->reativados($t, $p->anteriorInicio, $p->anteriorFim);
        $saidasAnt = $this->repo->saidas($t, $p->anteriorInicio, $p->anteriorFim);
        $permanencia = $this->permanencia($t, $saidas);
        $permanenciaAnt = $this->permanencia($t, $this->repo->saidasLista($t, $p->anteriorInicio, $p->anteriorFim));

        return [
            'periodo' => $p->toArray(),
            'indicadores' => [
                'alunos_ativos' => $par(count($ativos), $this->repo->alunosAtivosEm($t, $p->anteriorFim)),
                'novos_alunos' => $par($novos, $novosAnt),
                'reativados' => $par($reat, $reatAnt),
                'saidas' => $par(count($saidas), $saidasAnt),
                'saldo' => $par($novos + $reat - count($saidas), $novosAnt + $reatAnt - $saidasAnt),
                'retencao' => $par($this->retencao($t, $p->inicio, $p->fim), $this->retencao($t, $p->anteriorInicio, $p->anteriorFim)),
                'permanencia_meses' => $par($permanencia['media'], $permanenciaAnt['media']),
            ],
            'por_plano' => self::contar($ativos, 'plano_nome', 'Sem plano'),
            'por_modalidade' => self::contar($ativos, 'modalidade_nome', 'Sem modalidade'),
            'permanencia_saidas' => $permanencia['faixas'],
            'mensal' => $this->mensal($t, $p->fim),
        ];
    }

    /**
     * Dos ativos na véspera do período, % que continuam ativos no fim. Null sem base.
     */
    private function retencao(int $t, string $inicio, string $fim): ?float
    {
        $base = array_column($this->repo->alunosAtivosLista($t, date('Y-m-d', strtotime($inicio.' -1 day'))), 'aluno_id');
        if ($base === []) {
            return null;
        }
        $ficaram = array_intersect($base, array_column($this->repo->alunosAtivosLista($t, $fim), 'aluno_id'));

        return round(count($ficaram) / count($base) * 100, 1);
    }

    /**
     * Tempo da primeira matrícula até o cancelamento de quem saiu: média em meses e faixas.
     *
     * @param  list<array<string, mixed>>  $saidas
     * @return array{media: ?float, faixas: list<array{faixa: string, alunos: int}>}
     */
    private function permanencia(int $t, array $saidas): array
    {
        $primeira = $this->repo->primeiraMatricula($t, array_column($saidas, 'aluno_id'));
        $meses = [];
        foreach ($saidas as $s) {
            if (isset($primeira[$s['aluno_id']])) {
                $dias = (int) (new \DateTime($primeira[$s['aluno_id']]))->diff(new \DateTime($s['data']))->days;
                $meses[] = $dias / self::DIAS_MES;
            }
        }

        $faixas = [];
        $de = 0;
        foreach (self::FAIXAS_PERMANENCIA as [$ate, $rotulo]) {
            $faixas[] = ['faixa' => $rotulo, 'alunos' => count(array_filter($meses, fn ($m) => $m >= $de && $m < $ate))];
            $de = $ate;
        }

        return ['media' => $meses ? round(array_sum($meses) / count($meses), 1) : null, 'faixas' => $faixas];
    }

    /**
     * Doze meses terminando no mês do fim do período: ativos no último dia de cada mês (o último ponto é o
     * fim do período) e entradas, retornos e saídas do mês.
     */
    private function mensal(int $t, string $fim): array
    {
        $ini = date('Y-m-01', strtotime(substr($fim, 0, 7).'-01 -11 months'));
        $porMes = function (array $lista) {
            $out = [];
            foreach ($lista as $x) {
                $out[substr($x['data'], 0, 7)] = ($out[substr($x['data'], 0, 7)] ?? 0) + 1;
            }

            return $out;
        };
        $novos = $porMes($this->repo->novosAlunosLista($t, $ini, $fim));
        $voltaram = $porMes($this->repo->reativadosLista($t, $ini, $fim));
        $sairam = $porMes($this->repo->saidasLista($t, $ini, $fim));

        $out = [];
        for ($i = 0; $i < 12; $i++) {
            $mes = date('Y-m', strtotime($ini." +{$i} months"));
            $ref = $i === 11 ? $fim : date('Y-m-t', strtotime($mes.'-01'));
            $out[] = [
                'mes' => $mes,
                'ativos' => $this->repo->alunosAtivosEm($t, $ref),
                'novos' => $novos[$mes] ?? 0,
                'reativados' => $voltaram[$mes] ?? 0,
                'saidas' => $sairam[$mes] ?? 0,
            ];
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $itens
     * @return list<array{nome: string, alunos: int}>
     */
    private static function contar(array $itens, string $campo, string $vazio): array
    {
        $c = [];
        foreach ($itens as $x) {
            $k = $x[$campo] ?? $vazio;
            $c[$k] = ($c[$k] ?? 0) + 1;
        }
        arsort($c);

        return array_map(fn ($k, $v) => ['nome' => (string) $k, 'alunos' => $v], array_keys($c), array_values($c));
    }
}
