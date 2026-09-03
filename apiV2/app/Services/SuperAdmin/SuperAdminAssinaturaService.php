<?php

namespace App\Services\SuperAdmin;

use App\Repositories\AdminAssinaturaRepository;
use Throwable;

class SuperAdminAssinaturaService
{
    public function __construct(
        private readonly AdminAssinaturaRepository $assinaturas,
    ) {}

    /**
     * @param  array<string, mixed>  $params
     * @return array{status: int, body: array<string, mixed>}
     */
    public function listar(array $params): array
    {
        try {
            $page = max(1, (int) ($params['page'] ?? 1));
            $perPage = min(100, max(1, (int) ($params['per_page'] ?? 20)));
            $tenantId = isset($params['tenant_id']) ? (int) $params['tenant_id'] : null;

            $filtros = [
                'status' => $params['status'] ?? '',
                'tipo_cobranca' => $params['tipo_cobranca'] ?? '',
                'busca' => $params['busca'] ?? '',
            ];
            if ($tenantId !== null) {
                $filtros['tenant_id'] = $tenantId;
            }

            $result = $this->assinaturas->listar(null, $filtros, $page, $perPage);
            $total = $result['total'];
            $totalPages = (int) ceil($total / $perPage);

            $assinaturas = array_map(
                fn (array $row) => $this->mapearLinha($row),
                $result['rows'],
            );

            return [
                'status' => 200,
                'body' => [
                    'success' => true,
                    'total' => $total,
                    'page' => $page,
                    'per_page' => $perPage,
                    'total_pages' => $totalPages,
                    'assinaturas' => $assinaturas,
                ],
            ];
        } catch (Throwable $e) {
            error_log('[SuperAdminAssinaturaService::listar] '.$e->getMessage());

            return [
                'status' => 500,
                'body' => [
                    'success' => false,
                    'error' => 'Erro ao listar assinaturas',
                    'detail' => $e->getMessage(),
                ],
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function mapearLinha(array $row): array
    {
        $statusGateway = $row['status_gateway'] ?? 'pendente';

        return [
            'id' => (int) $row['id'],
            'tenant_id' => (int) ($row['tenant_id'] ?? 0),
            'tenant_nome' => $row['tenant_nome'] ?? '',
            'aluno_id' => (int) $row['aluno_id'],
            'aluno_nome' => $row['aluno_nome'] ?? '',
            'aluno_email' => $row['aluno_email'] ?? '',
            'matricula_id' => $row['matricula_id'] ? (int) $row['matricula_id'] : null,
            'valor' => (float) $row['valor'],
            'tipo_cobranca' => $row['tipo_cobranca'] ?? 'recorrente',
            'status' => [
                'codigo' => $row['status_codigo'] ?? $statusGateway,
                'nome' => $row['status_nome'] ?? ucfirst($statusGateway),
                'cor' => $row['status_cor'] ?? '#FFA500',
            ],
            'status_gateway' => $row['status_gateway'],
            'plano_nome' => $row['plano_nome'] ?? '',
            'modalidade_nome' => $row['modalidade_nome'] ?? '',
            'ciclo' => [
                'nome' => $row['ciclo_nome'] ?? 'Mensal',
                'meses' => (int) ($row['ciclo_meses'] ?? 1),
            ],
            'gateway' => $row['gateway_nome'] ?? 'Mercado Pago',
            'data_inicio' => $row['data_inicio'],
            'data_fim' => $row['data_fim'],
            'proxima_cobranca' => $row['proxima_cobranca'],
            'ultima_cobranca' => $row['ultima_cobranca'],
            'external_reference' => $row['external_reference'],
            'mp_preapproval_id' => $row['mp_preapproval_id'],
            'payment_url' => $row['payment_url'],
            'criado_em' => $row['criado_em'],
        ];
    }
}
