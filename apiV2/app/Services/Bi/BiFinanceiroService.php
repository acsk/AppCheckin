<?php

namespace App\Services\Bi;

use App\Repositories\Bi\BiTenantRepository;
use App\Support\AcademyDateTime;
use App\Support\Bi\Periodo;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * BI da academia — Financeiro: indicadores do período contra o anterior equivalente, receita por plano
 * e por forma de pagamento e o histórico mensal. Mesmas fórmulas da Visão geral (BiTenantRepository).
 */
class BiFinanceiroService
{
    private const CACHE_SEGUNDOS = 300;

    public function __construct(
        private readonly BiTenantRepository $repo,
    ) {}

    /** @return array{status: int, body: array<string, mixed>} */
    public function financeiro(?int $tenantId, ?string $preset, ?string $hoje = null): array
    {
        if (! $tenantId) {
            return ['status' => 400, 'body' => ['success' => false, 'error' => 'Academia não identificada']];
        }
        $hoje = $hoje ?: AcademyDateTime::today();
        $periodo = Periodo::resolver($preset, $hoje);

        try {
            $data = Cache::remember(
                "bi:tenant:{$tenantId}:financeiro:{$periodo->preset}:{$hoje}",
                self::CACHE_SEGUNDOS,
                fn () => $this->montar($tenantId, $periodo, $hoje),
            );

            return ['status' => 200, 'body' => ['success' => true, 'data' => $data]];
        } catch (Throwable $e) {
            error_log('[BiFinanceiroService::financeiro] '.$e->getMessage());

            return ['status' => 500, 'body' => ['success' => false, 'error' => 'Não foi possível calcular o financeiro']];
        }
    }

    /** @return array<string, mixed> */
    private function montar(int $tenantId, Periodo $p, string $hoje): array
    {
        $fin = $this->repo->financeiro($tenantId, $p->inicio, $p->fim, $hoje);
        $ant = $this->repo->financeiro($tenantId, $p->anteriorInicio, $p->anteriorFim, $hoje);
        $par = fn ($atual, $anterior) => ['atual' => $atual, 'anterior' => $anterior];

        return [
            'periodo' => $p->toArray(),
            'indicadores' => [
                'receita_prevista' => $par($fin['previsto'], $ant['previsto']),
                'receita_recebida' => $par($fin['recebido'], $ant['recebido']),
                'a_vencer' => $par($fin['a_vencer'], $ant['a_vencer']),
                'valor_vencido' => $par($fin['vencido'], $ant['vencido']),
                'inadimplencia' => $par(self::pct($fin['vencido'], $fin['previsto']), self::pct($ant['vencido'], $ant['previsto'])),
                'ticket_medio' => $par(self::div($fin['recebido'], $fin['pagantes']), self::div($ant['recebido'], $ant['pagantes'])),
                'descontos' => $par($this->repo->descontosRecebidos($tenantId, $p->inicio, $p->fim), $this->repo->descontosRecebidos($tenantId, $p->anteriorInicio, $p->anteriorFim)),
            ],
            'por_plano' => $this->repo->recebidoPorGrupo($tenantId, 'plano', $p->inicio, $p->fim),
            'por_forma' => $this->repo->recebidoPorGrupo($tenantId, 'forma', $p->inicio, $p->fim),
            'mensal' => $this->mensal($tenantId, $p->fim, $hoje),
        ];
    }

    /** Doze meses terminando no mês do fim do período */
    private function mensal(int $tenantId, string $fim, string $hoje): array
    {
        $ini = date('Y-m-01', strtotime(substr($fim, 0, 7).'-01 -11 months'));
        $porMes = $this->repo->financeiroPorMes($tenantId, $ini, date('Y-m-t', strtotime($fim)), $hoje);

        $out = [];
        for ($i = 0; $i < 12; $i++) {
            $mes = date('Y-m', strtotime($ini." +{$i} months"));
            $m = $porMes[$mes] ?? ['previsto' => 0.0, 'a_vencer' => 0.0, 'vencido' => 0.0, 'recebido' => 0.0, 'pagantes' => 0];
            $out[] = $m + [
                'mes' => $mes,
                'inadimplencia' => self::pct($m['vencido'], $m['previsto']),
                'ticket_medio' => self::div($m['recebido'], $m['pagantes']),
            ];
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
