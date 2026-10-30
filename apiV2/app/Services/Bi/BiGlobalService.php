<?php

namespace App\Services\Bi;

use App\Repositories\Bi\BiGlobalRepository;
use App\Repositories\Bi\BiTenantRepository;
use App\Services\SuperAdmin\CobrancaContratoService;
use App\Support\AcademyDateTime;
use App\Support\Bi\Periodo;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * BI Global (superadmin): a base de academias somada, o ranking por academia e a receita da plataforma.
 * Cada número de academia é o mesmo do BI dela (BiTenantRepository); o total é a soma.
 */
class BiGlobalService
{
    private const CACHE_SEGUNDOS = 600;

    public function __construct(
        private readonly BiGlobalRepository $global,
        private readonly BiTenantRepository $tenant,
    ) {}

    /** @return array{status: int, body: array<string, mixed>} */
    public function global(?string $preset, ?string $hoje = null): array
    {
        $hoje = $hoje ?: AcademyDateTime::today();
        $periodo = Periodo::resolver($preset, $hoje);

        try {
            $data = Cache::remember(
                "bi:global:{$periodo->preset}:{$hoje}",
                self::CACHE_SEGUNDOS,
                fn () => $this->montar($periodo, $hoje),
            );

            return ['status' => 200, 'body' => ['success' => true, 'data' => $data]];
        } catch (Throwable $e) {
            error_log('[BiGlobalService::global] '.$e->getMessage());

            return ['status' => 500, 'body' => ['success' => false, 'error' => 'Não foi possível calcular o BI Global']];
        }
    }

    /** @return array<string, mixed> */
    private function montar(Periodo $p, string $hoje): array
    {
        $sistema = CobrancaContratoService::sistemaTenantId();
        $academias = [];
        foreach ($this->global->academias($sistema) as $a) {
            $t = (int) $a->id;
            $fin = $this->tenant->financeiro($t, $p->inicio, $p->fim, $hoje);
            $ativos = $this->tenant->alunosAtivosEm($t, $p->fim);
            $ativosAnt = $this->tenant->alunosAtivosEm($t, $p->anteriorFim);
            $academias[] = [
                'tenant_id' => $t,
                'nome' => (string) $a->nome,
                'ativa' => (bool) $a->ativo,
                'alunos_ativos' => $ativos,
                'alunos_ativos_anterior' => $ativosAnt,
                'novos_alunos' => $this->tenant->novosAlunos($t, $p->inicio, $p->fim),
                'saidas' => $this->tenant->saidas($t, $p->inicio, $p->fim),
                'receita_recebida' => $fin['recebido'],
                'estornos' => $this->tenant->estornos($t, $p->inicio, $p->fim)['valor'],
                'valor_vencido' => $fin['vencido'],
                'checkins' => array_sum($this->tenant->checkinsPorDia($t, $p->inicio, $p->fim)),
            ];
        }
        usort($academias, fn ($a, $b) => [$b['alunos_ativos'], $b['receita_recebida'], $a['nome']] <=> [$a['alunos_ativos'], $a['receita_recebida'], $b['nome']]);

        $soma = fn (string $k) => array_sum(array_column($academias, $k));
        $plataforma = $this->global->receitaPlataforma($p->inicio, $p->fim, $hoje);
        $plataformaAnt = $this->global->receitaPlataforma($p->anteriorInicio, $p->anteriorFim, $hoje);
        $par = fn ($atual, $anterior) => ['atual' => $atual, 'anterior' => $anterior];

        return [
            'periodo' => $p->toArray(),
            'indicadores' => [
                'academias_ativas' => $par(count(array_filter($academias, fn ($a) => $a['ativa'])), null),
                'academias_com_alunos' => $par(count(array_filter($academias, fn ($a) => $a['alunos_ativos'] > 0)), count(array_filter($academias, fn ($a) => $a['alunos_ativos_anterior'] > 0))),
                'alunos_ativos' => $par($soma('alunos_ativos'), $soma('alunos_ativos_anterior')),
                'novos_alunos' => $par($soma('novos_alunos'), null),
                'saidas' => $par($soma('saidas'), null),
                'receita_academias' => $par(round($soma('receita_recebida'), 2), null),
                'checkins' => $par($soma('checkins'), null),
                'receita_plataforma' => $par($plataforma['recebido'], $plataformaAnt['recebido']),
                'plataforma_vencido' => $par($plataforma['vencido'], null),
                'academias_pagantes' => $par($plataforma['academias_pagantes'], $plataformaAnt['academias_pagantes']),
            ],
            'academias' => $academias,
            'mensal' => $this->mensal($sistema, $p->fim),
        ];
    }

    /** Doze meses terminando no mês do fim do período */
    private function mensal(int $sistema, string $fim): array
    {
        $ini = date('Y-m-01', strtotime(substr($fim, 0, 7).'-01 -11 months'));
        $refs = [];
        for ($i = 0; $i < 12; $i++) {
            $mes = date('Y-m', strtotime($ini." +{$i} months"));
            $refs[$mes] = min(date('Y-m-t', strtotime($mes.'-01')), $fim);
        }
        $ativos = $this->global->alunosAtivosNoFimDoMes($sistema, $refs);
        $receita = $this->global->recebidoPorMes($sistema, $ini, $fim);
        $checkins = $this->global->checkinsPorMes($sistema, $ini, $fim);

        return array_map(fn ($mes) => [
            'mes' => $mes,
            'alunos_ativos' => $ativos[$mes] ?? 0,
            'receita' => $receita[$mes] ?? 0.0,
            'checkins' => $checkins[$mes] ?? 0,
        ], array_keys($refs));
    }
}
