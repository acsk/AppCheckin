<?php

namespace App\Services\Admin;

use App\Repositories\DashboardRepository;
use Throwable;

/**
 * Dashboard admin (paridade Slim AdminController::dashboard + DashboardController::cards).
 */
class AdminDashboardService
{
    public function __construct(
        private readonly DashboardRepository $dashboard,
    ) {}

    /**
     * GET /admin/dashboard — objeto plano (sem wrapper success/data).
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    public function index(?int $tenantId): array
    {
        try {
            if (! $tenantId) {
                return [
                    'status' => 200,
                    'body' => $this->statsVazias(),
                ];
            }

            return [
                'status' => 200,
                'body' => $this->dashboard->statsAdmin($tenantId),
            ];
        } catch (Throwable $e) {
            error_log('[AdminDashboardService::index] Erro: '.$e->getMessage());

            return [
                'status' => 500,
                'body' => [
                    'type' => 'error',
                    'message' => 'Erro ao carregar dashboard: '.$e->getMessage(),
                ],
            ];
        }
    }

    /**
     * GET /admin/dashboard/cards
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    public function cards(?int $tenantId): array
    {
        try {
            if (! $tenantId) {
                return [
                    'status' => 200,
                    'body' => [
                        'success' => true,
                        'data' => $this->cardsVazios(),
                    ],
                ];
            }

            return [
                'status' => 200,
                'body' => [
                    'success' => true,
                    'data' => [
                        'total_alunos' => $this->dashboard->totalAlunosCards($tenantId),
                        'receita_mensal' => $this->dashboard->receitaMensalCards($tenantId),
                        'checkins_hoje' => $this->dashboard->checkinsCards($tenantId),
                        'planos_vencendo' => $this->dashboard->planosVencendoCards($tenantId),
                    ],
                ],
            ];
        } catch (Throwable $e) {
            error_log('[AdminDashboardService::cards] Erro: '.$e->getMessage());

            return [
                'status' => 500,
                'body' => [
                    'success' => false,
                    'error' => 'Erro ao carregar cards do dashboard',
                    'message' => $e->getMessage(),
                ],
            ];
        }
    }

    /**
     * @return array<string, int|float>
     */
    private function statsVazias(): array
    {
        return [
            'total_alunos' => 0,
            'alunos_ativos' => 0,
            'alunos_inativos' => 0,
            'novos_alunos_mes' => 0,
            'total_checkins_hoje' => 0,
            'total_checkins_mes' => 0,
            'planos_vencendo' => 0,
            'receita_mensal' => 0.0,
            'contas_pendentes_qtd' => 0,
            'contas_pendentes_valor' => 0.0,
            'contas_vencidas_qtd' => 0,
            'contas_vencidas_valor' => 0.0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function cardsVazios(): array
    {
        return [
            'total_alunos' => ['total' => 0, 'ativos' => 0, 'inativos' => 0],
            'receita_mensal' => ['valor' => 0, 'valor_formatado' => 'R$ 0,00', 'contas_pendentes' => 0],
            'checkins_hoje' => ['hoje' => 0, 'no_mes' => 0],
            'planos_vencendo' => ['vencendo' => 0, 'novos_este_mes' => 0],
        ];
    }
}
