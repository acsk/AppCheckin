<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AuditoriaController
{
    /**
     * Pagamentos duplicados no mesmo mês (resumo agrupado)
     * GET /admin/auditoria/pagamentos-duplicados
     */
    public function pagamentosDuplicados(Request $request, Response $response): Response
    {
        $tenantId = $request->getAttribute('tenant_id');
        $db = require __DIR__ . '/../../config/database.php';

        try {
            // Resumo: grupos com mais de 1 parcela na mesma data de vencimento
            $sql = "
                SELECT
                    pp.aluno_id,
                    a.nome AS aluno_nome,
                    pp.matricula_id,
                    pp.plano_id,
                    p.nome AS plano_nome,
                    pp.data_vencimento,
                    COUNT(*) AS total_parcelas,
                    GROUP_CONCAT(pp.id ORDER BY pp.id) AS ids_pagamentos,
                    GROUP_CONCAT(pp.valor ORDER BY pp.id) AS valores,
                    GROUP_CONCAT(
                        CASE pp.status_pagamento_id
                            WHEN 1 THEN 'Aguardando'
                            WHEN 2 THEN 'Pago'
                            WHEN 3 THEN 'Atrasado'
                            WHEN 4 THEN 'Cancelado'
                            ELSE CONCAT('Status_', pp.status_pagamento_id)
                        END
                        ORDER BY pp.id
                    ) AS statuses
                FROM pagamentos_plano pp
                LEFT JOIN alunos a ON a.id = pp.aluno_id
                LEFT JOIN planos p ON p.id = pp.plano_id
                WHERE pp.tenant_id = :tenant_id
                  AND pp.status_pagamento_id != 4
                GROUP BY pp.aluno_id, a.nome, pp.matricula_id, pp.plano_id, p.nome,
                         pp.data_vencimento
                HAVING COUNT(*) > 1
                ORDER BY pp.data_vencimento DESC, a.nome
            ";

            $stmt = $db->prepare($sql);
            $stmt->execute(['tenant_id' => $tenantId]);
            $grupos = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Contagem geral
            $sqlContagem = "
                SELECT
                    COUNT(*) AS total_grupos_duplicados,
                    SUM(total) AS total_pagamentos_envolvidos
                FROM (
                    SELECT COUNT(*) AS total
                    FROM pagamentos_plano
                    WHERE tenant_id = :tenant_id
                      AND status_pagamento_id != 4
                    GROUP BY aluno_id, matricula_id, plano_id,
                             data_vencimento
                    HAVING COUNT(*) > 1
                ) sub
            ";

            $stmtContagem = $db->prepare($sqlContagem);
            $stmtContagem->execute(['tenant_id' => $tenantId]);
            $contagem = $stmtContagem->fetch(\PDO::FETCH_ASSOC);

            $response->getBody()->write(json_encode([
                'resumo' => [
                    'total_grupos_duplicados' => (int) ($contagem['total_grupos_duplicados'] ?? 0),
                    'total_pagamentos_envolvidos' => (int) ($contagem['total_pagamentos_envolvidos'] ?? 0),
                ],
                'grupos' => $grupos
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Erro ao buscar pagamentos duplicados: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Detalhe completo dos pagamentos duplicados
     * GET /admin/auditoria/pagamentos-duplicados/detalhe
     */
    public function pagamentosDuplicadosDetalhe(Request $request, Response $response): Response
    {
        $tenantId = $request->getAttribute('tenant_id');
        $db = require __DIR__ . '/../../config/database.php';

        $queryParams = $request->getQueryParams();
        $filtroAlunoId = !empty($queryParams['aluno_id']) ? (int) $queryParams['aluno_id'] : null;
        $filtroMatriculaId = !empty($queryParams['matricula_id']) ? (int) $queryParams['matricula_id'] : null;
        $filtroAno = !empty($queryParams['ano']) ? (int) $queryParams['ano'] : null;
        $filtroMes = !empty($queryParams['mes']) ? (int) $queryParams['mes'] : null;

        try {
            $sql = "
                SELECT
                    pp.id,
                    pp.aluno_id,
                    a.nome AS aluno_nome,
                    pp.matricula_id,
                    pp.plano_id,
                    p.nome AS plano_nome,
                    pp.valor,
                    pp.data_vencimento,
                    pp.data_pagamento,
                    CASE pp.status_pagamento_id
                        WHEN 1 THEN 'Aguardando'
                        WHEN 2 THEN 'Pago'
                        WHEN 3 THEN 'Atrasado'
                        WHEN 4 THEN 'Cancelado'
                        ELSE CONCAT('Status_', pp.status_pagamento_id)
                    END AS status,
                    pp.credito_id,
                    pp.credito_aplicado,
                    pp.observacoes,
                    pp.created_at
                FROM pagamentos_plano pp
                INNER JOIN (
                    SELECT aluno_id, matricula_id, plano_id, data_vencimento
                    FROM pagamentos_plano
                    WHERE tenant_id = :tenant_id_sub
                      AND status_pagamento_id != 4
                    GROUP BY aluno_id, matricula_id, plano_id, data_vencimento
                    HAVING COUNT(*) > 1
                ) dup ON pp.aluno_id = dup.aluno_id
                     AND pp.matricula_id = dup.matricula_id
                     AND pp.plano_id = dup.plano_id
                     AND pp.data_vencimento = dup.data_vencimento
                LEFT JOIN alunos a ON a.id = pp.aluno_id
                LEFT JOIN planos p ON p.id = pp.plano_id
                WHERE pp.tenant_id = :tenant_id
                  AND pp.status_pagamento_id != 4
            ";

            $params = [
                'tenant_id' => $tenantId,
                'tenant_id_sub' => $tenantId,
            ];

            if ($filtroAlunoId) {
                $sql .= " AND pp.aluno_id = :aluno_id";
                $params['aluno_id'] = $filtroAlunoId;
            }
            if ($filtroMatriculaId) {
                $sql .= " AND pp.matricula_id = :matricula_id";
                $params['matricula_id'] = $filtroMatriculaId;
            }
            if ($filtroAno) {
                $sql .= " AND YEAR(pp.data_vencimento) = :ano";
                $params['ano'] = $filtroAno;
            }
            if ($filtroMes) {
                $sql .= " AND MONTH(pp.data_vencimento) = :mes";
                $params['mes'] = $filtroMes;
            }

            $sql .= " ORDER BY a.nome, pp.data_vencimento, pp.id";

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $pagamentos = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $response->getBody()->write(json_encode([
                'total' => count($pagamentos),
                'pagamentos' => $pagamentos
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Erro ao buscar detalhe de duplicados: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }
}
