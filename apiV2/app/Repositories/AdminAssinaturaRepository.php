<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

/**
 * Assinaturas do tenant (painel admin).
 *
 * Paridade com AssinaturaController::listarAssinaturasAdmin (Slim).
 */
class AdminAssinaturaRepository
{
    /**
     * @param  array{status?: string, tipo_cobranca?: string, busca?: string}  $filtros
     * @return array{total: int, rows: list<array<string, mixed>>}
     */
    public function listar(int $tenantId, array $filtros, int $page, int $perPage): array
    {
        $where = 'WHERE a.tenant_id = ?';
        $binds = [$tenantId];

        $statusFiltro = trim($filtros['status'] ?? '');
        if ($statusFiltro !== '') {
            $where .= ' AND s.codigo = ?';
            $binds[] = $statusFiltro;
        }

        $tipoCobranca = trim($filtros['tipo_cobranca'] ?? '');
        if ($tipoCobranca !== '') {
            $where .= ' AND a.tipo_cobranca = ?';
            $binds[] = $tipoCobranca;
        }

        $busca = trim($filtros['busca'] ?? '');
        if ($busca !== '') {
            $where .= ' AND (al.nome LIKE ? OR u.nome LIKE ?)';
            $binds[] = "%{$busca}%";
            $binds[] = "%{$busca}%";
        }

        $totalRow = DB::selectOne(
            "SELECT COUNT(*) as total
             FROM assinaturas a
             LEFT JOIN assinatura_status s ON s.id = a.status_id
             LEFT JOIN alunos al ON al.id = a.aluno_id
             LEFT JOIN usuarios u ON u.id = al.usuario_id
             {$where}",
            $binds
        );
        $total = (int) ($totalRow->total ?? 0);

        $offset = ($page - 1) * $perPage;
        $rows = DB::select(
            "SELECT a.id, a.aluno_id, a.matricula_id, a.valor, a.tipo_cobranca,
                    a.data_inicio, a.data_fim, a.proxima_cobranca, a.ultima_cobranca,
                    a.gateway_assinatura_id as mp_preapproval_id,
                    a.external_reference, a.payment_url, a.status_gateway,
                    a.criado_em,
                    s.codigo as status_codigo, s.nome as status_nome, s.cor as status_cor,
                    f.nome as ciclo_nome, f.meses as ciclo_meses,
                    g.nome as gateway_nome,
                    p.nome as plano_nome,
                    mo.nome as modalidade_nome,
                    COALESCE(al.nome, u.nome) as aluno_nome,
                    u.email as aluno_email
             FROM assinaturas a
             LEFT JOIN assinatura_status s ON s.id = a.status_id
             LEFT JOIN assinatura_frequencias f ON f.id = a.frequencia_id
             LEFT JOIN assinatura_gateways g ON g.id = a.gateway_id
             LEFT JOIN planos p ON p.id = a.plano_id
             LEFT JOIN modalidades mo ON mo.id = p.modalidade_id
             LEFT JOIN alunos al ON al.id = a.aluno_id
             LEFT JOIN usuarios u ON u.id = al.usuario_id
             {$where}
             ORDER BY a.criado_em DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $binds
        );

        return [
            'total' => $total,
            'rows' => array_map(fn ($row) => (array) $row, $rows),
        ];
    }
}
