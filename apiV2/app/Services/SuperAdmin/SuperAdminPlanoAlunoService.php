<?php

namespace App\Services\SuperAdmin;

use App\Repositories\TenantRepository;
use Illuminate\Support\Facades\DB;

class SuperAdminPlanoAlunoService
{
    public function __construct(
        private readonly TenantRepository $tenants,
    ) {}

    /**
     * GET /superadmin/planos?tenant_id=X&ativos=true
     *
     * @param  array<string, mixed>  $query
     * @return array{status: int, body: array<string, mixed>}
     */
    public function listarPlanosAlunos(array $query): array
    {
        $tenantId = isset($query['tenant_id']) ? (int) $query['tenant_id'] : null;
        $apenasAtivos = isset($query['ativos']) && $query['ativos'] === 'true';

        if (! $tenantId) {
            $tenants = $this->tenants->getAll(['ativo' => true]);

            return [
                'status' => 200,
                'body' => [
                    'planos' => [],
                    'total' => 0,
                    'tenants' => $tenants,
                    'message' => 'Selecione uma academia para ver os planos',
                ],
            ];
        }

        $sql = 'SELECT p.*, t.nome as academia_nome,
                       m.nome as modalidade_nome, m.icone as modalidade_icone, m.cor as modalidade_cor
                FROM planos p
                INNER JOIN tenants t ON p.tenant_id = t.id
                LEFT JOIN modalidades m ON p.modalidade_id = m.id
                WHERE p.tenant_id = ?';
        $bindings = [$tenantId];

        if ($apenasAtivos) {
            $sql .= ' AND p.ativo = 1';
        }

        $sql .= ' ORDER BY p.nome ASC';

        $planos = array_map(fn ($row) => (array) $row, DB::select($sql, $bindings));
        $tenants = $this->tenants->getAll(['ativo' => true]);

        return [
            'status' => 200,
            'body' => [
                'planos' => $planos,
                'total' => count($planos),
                'tenants' => $tenants,
            ],
        ];
    }
}
