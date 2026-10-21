<?php

namespace App\Services\Admin\Prospeccao;

use App\Repositories\ProspeccaoRepository;
use App\Support\AcademyDateTime;
use App\Support\ProspeccaoStatus;

/**
 * Indicadores da recuperação. Contagens são de ALUNOS distintos (nunca de matrículas).
 *
 * Coorte do período = oportunidades cuja data de cancelamento de referência está no período.
 * - cancelados: alunos da coorte (já sem diárias e sem quem continua ativo em outra matrícula)
 * - elegiveis: cancelados menos "Não contatar"
 * - por status: situação ATUAL das oportunidades da coorte
 * - recuperados: oportunidades da coorte que viraram matrícula ativa (em qualquer data)
 * - taxa de conversão: recuperados ÷ elegíveis
 * - receita mensal recuperada: valor mensal das matrículas de conversão da coorte que estão ativas hoje
 * - série mensal: recuperações pela data em que aconteceram (convertida_em)
 */
class ProspeccaoDashboardService
{
    public function __construct(
        private readonly ProspeccaoRepository $repo,
    ) {}

    /**
     * @param  array<string, mixed>  $q  {de?, ate?, modalidade_id?}
     * @return array{status: int, body: array<string, mixed>}
     */
    public function indicadores(int $tenantId, array $q): array
    {
        $periodo = $this->periodo($q);
        if (isset($periodo['error'])) {
            return ['status' => 422, 'body' => $periodo];
        }
        [$de, $ate, $modalidadeId] = [$periodo['de'], $periodo['ate'], $periodo['modalidade_id']];

        $porStatus = $this->repo->coortePorStatus($tenantId, $de, $ate, $modalidadeId);
        $cancelados = $this->repo->coorteAlunos($tenantId, $de, $ate, $modalidadeId, false);
        $elegiveis = $this->repo->coorteAlunos($tenantId, $de, $ate, $modalidadeId, true);
        $recuperados = $porStatus[ProspeccaoStatus::RECUPERADO] ?? 0;
        $contatados = 0;
        foreach ([ProspeccaoStatus::CONTATADO, ProspeccaoStatus::INTERESSADO, ProspeccaoStatus::EM_NEGOCIACAO, ProspeccaoStatus::RETORNO_AGENDADO, ProspeccaoStatus::RECUPERADO, ProspeccaoStatus::SEM_INTERESSE] as $s) {
            $contatados += $porStatus[$s] ?? 0;
        }

        $motivos = [];
        foreach ($this->repo->coortePorMotivo($tenantId, $de, $ate, $modalidadeId) as $codigo => $total) {
            $motivos[] = ['motivo' => $codigo ?: null, 'rotulo' => $codigo ? (ProspeccaoStatus::MOTIVOS[$codigo] ?? $codigo) : 'Não informado', 'total' => $total];
        }
        usort($motivos, fn ($a, $b) => $b['total'] <=> $a['total']);

        $hoje = AcademyDateTime::todayStart();

        return ['status' => 200, 'body' => [
            'periodo' => ['de' => $de, 'ate' => $ate, 'modalidade_id' => $modalidadeId],
            'indicadores' => [
                'cancelados' => $cancelados,
                'elegiveis' => $elegiveis,
                'nao_contatados' => $porStatus[ProspeccaoStatus::NAO_CONTATADO] ?? 0,
                'contatados' => $contatados,
                'interessados' => $porStatus[ProspeccaoStatus::INTERESSADO] ?? 0,
                'em_negociacao' => $porStatus[ProspeccaoStatus::EM_NEGOCIACAO] ?? 0,
                'retorno_agendado' => $porStatus[ProspeccaoStatus::RETORNO_AGENDADO] ?? 0,
                'recuperados' => $recuperados,
                'sem_interesse' => $porStatus[ProspeccaoStatus::SEM_INTERESSE] ?? 0,
                'nao_contatar' => $porStatus[ProspeccaoStatus::NAO_CONTATAR] ?? 0,
                'taxa_conversao' => $elegiveis > 0 ? round($recuperados / $elegiveis * 100, 1) : null,
                'receita_mensal_recuperada' => round($this->repo->receitaRecuperadaAtiva($tenantId, $de, $ate, $modalidadeId), 2),
            ],
            'agenda' => $this->repo->contagemAgenda($tenantId, $hoje->format('Y-m-d H:i:s'), (clone $hoje)->modify('+1 day')->format('Y-m-d H:i:s')),
            'graficos' => [
                'recuperacoes_por_mes' => $this->serieMensal($this->repo->recuperacoesPorMes($tenantId, $de, $ate, $modalidadeId), $de, $ate),
                'por_status' => array_map(fn ($s) => ['status' => $s, 'rotulo' => ProspeccaoStatus::ROTULOS[$s], 'total' => $porStatus[$s] ?? 0], array_keys(ProspeccaoStatus::ROTULOS)),
                'por_modalidade' => $this->repo->recuperacoesPorModalidade($tenantId, $de, $ate, $modalidadeId),
                'motivos' => $motivos,
            ],
            'filtros' => ['modalidades' => $this->repo->modalidades($tenantId)],
        ]];
    }

    /**
     * Relatório agregado por mês e por pessoa da equipe. Não expõe dados pessoais de alunos.
     *
     * @param  array<string, mixed>  $q
     * @return array{status: int, body: array<string, mixed>}
     */
    public function relatorio(int $tenantId, array $q): array
    {
        $periodo = $this->periodo($q);
        if (isset($periodo['error'])) {
            return ['status' => 422, 'body' => $periodo];
        }
        [$de, $ate, $modalidadeId] = [$periodo['de'], $periodo['ate'], $periodo['modalidade_id']];

        $cancelamentos = $this->serieMensal($this->repo->cancelamentosPorMes($tenantId, $de, $ate, $modalidadeId), $de, $ate);
        $recuperacoes = $this->serieMensal($this->repo->recuperacoesPorMes($tenantId, $de, $ate, $modalidadeId), $de, $ate);
        $meses = [];
        foreach ($cancelamentos as $i => $c) {
            $meses[] = ['mes' => $c['mes'], 'cancelamentos' => $c['total'], 'recuperacoes' => $recuperacoes[$i]['total']];
        }

        return ['status' => 200, 'body' => [
            'periodo' => ['de' => $de, 'ate' => $ate, 'modalidade_id' => $modalidadeId],
            'meses' => $meses,
            'equipe' => $this->repo->contatosPorUsuario($tenantId, $de, $ate),
            'por_modalidade' => $this->repo->recuperacoesPorModalidade($tenantId, $de, $ate, $modalidadeId),
        ]];
    }

    /**
     * Período padrão: últimos 12 meses (início no dia 1º).
     *
     * @param  array<string, mixed>  $q
     * @return array{de: string, ate: string, modalidade_id: ?int}|array{error: string}
     */
    private function periodo(array $q): array
    {
        $hoje = AcademyDateTime::todayStart();
        $de = (string) ($q['de'] ?? (clone $hoje)->modify('first day of -11 months')->format('Y-m-d'));
        $ate = (string) ($q['ate'] ?? $hoje->format('Y-m-d'));
        foreach ([$de, $ate] as $d) {
            $dt = \DateTime::createFromFormat('!Y-m-d', $d);
            if (! $dt || $dt->format('Y-m-d') !== $d) {
                return ['error' => 'Período inválido (use AAAA-MM-DD)'];
            }
        }
        if ($de > $ate) {
            return ['error' => 'A data inicial deve ser anterior à final'];
        }
        if ((new \DateTime($de))->diff(new \DateTime($ate))->days > 731) {
            return ['error' => 'Período máximo de 24 meses'];
        }
        $modalidade = $q['modalidade_id'] ?? null;

        return ['de' => $de, 'ate' => $ate, 'modalidade_id' => ctype_digit((string) $modalidade) ? (int) $modalidade : null];
    }

    /**
     * Preenche os meses sem dados com zero.
     *
     * @param  array<string, int>  $valores  'YYYY-MM' => total
     * @return list<array{mes: string, total: int}>
     */
    private function serieMensal(array $valores, string $de, string $ate): array
    {
        $serie = [];
        $mes = new \DateTime(substr($de, 0, 7).'-01');
        $fim = substr($ate, 0, 7);
        while ($mes->format('Y-m') <= $fim) {
            $chave = $mes->format('Y-m');
            $serie[] = ['mes' => $chave, 'total' => (int) ($valores[$chave] ?? 0)];
            $mes->modify('+1 month');
        }

        return $serie;
    }
}
