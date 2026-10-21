<?php

namespace App\Services\Admin\Prospeccao;

use App\Repositories\ProspeccaoRepository;
use App\Support\AcademyDateTime;
use App\Support\ProspeccaoStatus;

/**
 * Score de recuperação (0–100), heurística determinística — sem IA.
 *
 * Critério sem dado não pontua nem conta no total possível: o score é reescalado para 0–100 sobre
 * os pesos disponíveis, e `confianca` informa quanto do peso total pôde ser avaliado. Nada é presumido.
 * O score nunca libera contato: restrições (Não contatar) são verificadas à parte.
 */
class ProspeccaoScoreService
{
    public const PARAMETRO = 'prospeccao_score_config';

    /** @var array{pesos: array<string, int>, motivos_reversiveis: list<string>} */
    public const CONFIG_PADRAO = [
        'pesos' => [
            'frequencia' => 25,
            'recencia' => 20,
            'pagamentos' => 20,
            'permanencia' => 15,
            'motivo' => 10,
            'interesse' => 10,
        ],
        'motivos_reversiveis' => ['financeiro', 'horario', 'tempo', 'temporario'],
    ];

    public const CRITERIOS = [
        'frequencia' => 'Frequência anterior',
        'recencia' => 'Cancelamento recente',
        'pagamentos' => 'Histórico de pagamentos',
        'permanencia' => 'Tempo de permanência',
        'motivo' => 'Motivo reversível',
        'interesse' => 'Interesse recente',
    ];

    /** Matrícula com menos dias que isto não permite medir frequência. */
    public const DIAS_MINIMOS_FREQUENCIA = 14;

    public const DIAS_INTERESSE_RECENTE = 30;

    public function __construct(
        private readonly ProspeccaoRepository $repo,
    ) {}

    public static function classificacao(?int $score): ?string
    {
        if ($score === null) {
            return null;
        }

        return $score >= 80 ? 'alto' : ($score >= 50 ? 'medio' : 'baixo');
    }

    /**
     * @return array{pesos: array<string, int>, motivos_reversiveis: list<string>}
     */
    public function config(int $tenantId): array
    {
        return self::normalizarConfig($this->repo->valorParametro($tenantId, self::PARAMETRO));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function salvarConfig(int $tenantId, array $data, ?int $usuarioId): array
    {
        $pesos = $data['pesos'] ?? null;
        if (! is_array($pesos)) {
            return ['status' => 422, 'body' => ['error' => 'Informe os pesos dos critérios']];
        }
        foreach ($pesos as $criterio => $peso) {
            if (! isset(self::CRITERIOS[$criterio]) || ! is_numeric($peso) || (int) $peso < 0 || (int) $peso > 100) {
                return ['status' => 422, 'body' => ['error' => 'Peso inválido para '.$criterio.' (use 0 a 100)']];
            }
        }
        $motivos = $data['motivos_reversiveis'] ?? [];
        if (! is_array($motivos) || array_diff($motivos, array_keys(ProspeccaoStatus::MOTIVOS)) !== []) {
            return ['status' => 422, 'body' => ['error' => 'Motivo reversível inválido']];
        }

        $config = self::normalizarConfig(['pesos' => $pesos, 'motivos_reversiveis' => array_values($motivos)]);
        if (array_sum($config['pesos']) <= 0) {
            return ['status' => 422, 'body' => ['error' => 'Ao menos um critério precisa ter peso']];
        }
        $this->repo->salvarParametro($tenantId, self::PARAMETRO, json_encode($config), $usuarioId);

        return ['status' => 200, 'body' => ['config' => $config, 'message' => 'Configuração salva. Use "Recalcular" para aplicar às oportunidades.']];
    }

    /**
     * @return array{pesos: array<string, int>, motivos_reversiveis: list<string>}
     */
    public static function normalizarConfig(mixed $valor): array
    {
        $dados = is_string($valor) ? json_decode($valor, true) : $valor;
        $dados = is_array($dados) ? $dados : [];
        $pesos = [];
        foreach (self::CONFIG_PADRAO['pesos'] as $criterio => $padrao) {
            $peso = $dados['pesos'][$criterio] ?? $padrao;
            $pesos[$criterio] = is_numeric($peso) ? max(0, min(100, (int) $peso)) : $padrao;
        }
        $motivos = $dados['motivos_reversiveis'] ?? self::CONFIG_PADRAO['motivos_reversiveis'];
        $motivos = is_array($motivos) ? array_values(array_intersect($motivos, array_keys(ProspeccaoStatus::MOTIVOS))) : [];

        return ['pesos' => $pesos, 'motivos_reversiveis' => $motivos];
    }

    /**
     * Cálculo puro. Cada fator vai de 0 a 1 ou é null (dado indisponível).
     *
     * @param  array{
     *   checkins: int, dias_matricula: int, checkins_semanais: ?int,
     *   dias_desde_cancelamento: int,
     *   parcelas_em_dia: int, parcelas_avaliadas: int,
     *   dias_permanencia: ?int,
     *   motivo_categoria: ?string,
     *   interesse_recente: bool
     * }  $dados
     * @param  array{pesos: array<string, int>, motivos_reversiveis: list<string>}  $config
     * @return array{score: ?int, confianca: int, criterios: list<array{criterio: string, rotulo: string, peso: int, fator: ?float, pontos: ?float, detalhe: string}>}
     */
    public static function calcular(array $dados, array $config): array
    {
        $fatores = [];

        if ($dados['dias_matricula'] < self::DIAS_MINIMOS_FREQUENCIA || ! $dados['checkins_semanais']) {
            $fatores['frequencia'] = [null, 'Período curto ou plano sem limite semanal para comparar'];
        } else {
            $esperado = $dados['checkins_semanais'] * $dados['dias_matricula'] / 7;
            $fatores['frequencia'] = [min(1, $dados['checkins'] / $esperado), $dados['checkins'].' check-ins de ~'.(int) round($esperado).' possíveis'];
        }

        $dias = $dados['dias_desde_cancelamento'];
        $fatores['recencia'] = [$dias <= 30 ? 1.0 : ($dias <= 90 ? 0.6 : ($dias <= 180 ? 0.3 : 0.0)), $dias.' dias desde o cancelamento'];

        $fatores['pagamentos'] = $dados['parcelas_avaliadas'] > 0
            ? [$dados['parcelas_em_dia'] / $dados['parcelas_avaliadas'], $dados['parcelas_em_dia'].' de '.$dados['parcelas_avaliadas'].' parcelas pagas em dia']
            : [null, 'Sem parcelas vencidas para avaliar'];

        $perm = $dados['dias_permanencia'];
        $fatores['permanencia'] = $perm === null
            ? [null, 'Sem data de início']
            : [$perm >= 365 ? 1.0 : ($perm >= 180 ? 0.66 : ($perm >= 90 ? 0.33 : 0.0)), (int) floor($perm / 30).' meses como aluno'];

        $motivo = $dados['motivo_categoria'];
        $fatores['motivo'] = $motivo === null
            ? [null, 'Motivo ainda não registrado pela equipe']
            : [in_array($motivo, $config['motivos_reversiveis'], true) ? 1.0 : 0.0, ProspeccaoStatus::MOTIVOS[$motivo] ?? $motivo];

        $fatores['interesse'] = $dados['interesse_recente']
            ? [1.0, 'Demonstrou interesse nos últimos '.self::DIAS_INTERESSE_RECENTE.' dias']
            : [0.0, 'Sem interesse registrado nos últimos '.self::DIAS_INTERESSE_RECENTE.' dias'];

        $criterios = [];
        $obtido = 0.0;
        $possivel = 0;
        $total = 0;
        foreach ($config['pesos'] as $criterio => $peso) {
            [$fator, $detalhe] = $fatores[$criterio];
            $total += $peso;
            $pontos = $fator === null ? null : round($fator * $peso, 1);
            if ($fator !== null) {
                $obtido += $fator * $peso;
                $possivel += $peso;
            }
            $criterios[] = ['criterio' => $criterio, 'rotulo' => self::CRITERIOS[$criterio], 'peso' => $peso, 'fator' => $fator === null ? null : round($fator, 3), 'pontos' => $pontos, 'detalhe' => $detalhe];
        }

        return [
            'score' => $possivel > 0 ? (int) round($obtido / $possivel * 100) : null,
            'confianca' => $total > 0 ? (int) round($possivel / $total * 100) : 0,
            'criterios' => $criterios,
        ];
    }

    /**
     * Recalcula e persiste o score das oportunidades informadas (ids do tenant).
     *
     * @param  list<int>  $oportunidadeIds
     */
    public function recalcular(int $tenantId, array $oportunidadeIds): int
    {
        if ($oportunidadeIds === []) {
            return 0;
        }

        $config = $this->config($tenantId);
        $hoje = AcademyDateTime::todayStart();
        $agora = AcademyDateTime::nowFormatted();
        $limiteInteresse = (clone $hoje)->modify('-'.self::DIAS_INTERESSE_RECENTE.' days')->format('Y-m-d');
        $atualizadas = 0;

        foreach (array_chunk($oportunidadeIds, 200) as $lote) {
            foreach ($this->repo->dadosScore($tenantId, $lote, $hoje->format('Y-m-d'), $limiteInteresse) as $id => $dados) {
                $r = self::calcular($dados, $config);
                $this->repo->salvarScore($tenantId, $id, $r['score'], $r['confianca'], $r['criterios'], $agora);
                $atualizadas++;
            }
        }

        return $atualizadas;
    }
}
