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

    /**
     * Anomalias de datas em matrículas
     * GET /admin/auditoria/anomalias-datas
     *
     * Detecta:
     * 1. proxima_data_vencimento NULL em matrículas ativas
     * 2. proxima_data_vencimento desatualizada vs parcelas pendentes
     * 3. Matrículas ativas com vencimento já expirado
     * 4. Matrículas canceladas/vencidas que têm parcelas futuras pendentes
     * 5. Matrículas duplicadas (mesmo aluno + modalidade ativas)
     */
    public function anomaliasDatas(Request $request, Response $response): Response
    {
        $tenantId = $request->getAttribute('tenant_id');
        $db = require __DIR__ . '/../../config/database.php';

        try {
            $anomalias = [];

            // 1. proxima_data_vencimento NULL em matrículas ativas
            $sql = "
                SELECT m.id AS matricula_id, a.nome AS aluno_nome, p.nome AS plano_nome,
                       m.data_vencimento, m.proxima_data_vencimento, sm.codigo AS status
                FROM matriculas m
                INNER JOIN status_matricula sm ON sm.id = m.status_id
                INNER JOIN alunos a ON a.id = m.aluno_id
                INNER JOIN planos p ON p.id = m.plano_id
                WHERE m.tenant_id = :tid
                  AND sm.codigo = 'ativa'
                  AND m.proxima_data_vencimento IS NULL
                ORDER BY a.nome
            ";
            $stmt = $db->prepare($sql);
            $stmt->execute(['tid' => $tenantId]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            if ($rows) {
                $anomalias[] = [
                    'tipo' => 'proxima_data_vencimento_null',
                    'descricao' => 'Matrículas ativas com proxima_data_vencimento NULL',
                    'severidade' => 'alta',
                    'total' => count($rows),
                    'registros' => $rows,
                ];
            }

            // 2. proxima_data_vencimento desatualizada (não bate com próxima parcela pendente)
            $sql = "
                SELECT m.id AS matricula_id, a.nome AS aluno_nome, p.nome AS plano_nome,
                       m.proxima_data_vencimento AS vencimento_matricula,
                       MIN(pp.data_vencimento) AS proxima_parcela_pendente,
                       sm.codigo AS status
                FROM matriculas m
                INNER JOIN status_matricula sm ON sm.id = m.status_id
                INNER JOIN alunos a ON a.id = m.aluno_id
                INNER JOIN planos p ON p.id = m.plano_id
                INNER JOIN pagamentos_plano pp ON pp.matricula_id = m.id
                    AND pp.status_pagamento_id IN (1, 3)
                WHERE m.tenant_id = :tid
                  AND sm.codigo = 'ativa'
                  AND m.proxima_data_vencimento IS NOT NULL
                GROUP BY m.id, a.nome, p.nome, m.proxima_data_vencimento, sm.codigo
                HAVING m.proxima_data_vencimento != MIN(pp.data_vencimento)
                ORDER BY a.nome
            ";
            $stmt = $db->prepare($sql);
            $stmt->execute(['tid' => $tenantId]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            if ($rows) {
                $anomalias[] = [
                    'tipo' => 'proxima_data_vencimento_desatualizada',
                    'descricao' => 'Matrículas ativas onde proxima_data_vencimento não corresponde à próxima parcela pendente',
                    'severidade' => 'media',
                    'total' => count($rows),
                    'registros' => $rows,
                ];
            }

            // 3. Matrículas ativas com vencimento expirado (> 5 dias)
            $sql = "
                SELECT m.id AS matricula_id, a.nome AS aluno_nome, p.nome AS plano_nome,
                       COALESCE(m.proxima_data_vencimento, m.data_vencimento) AS vencimento_efetivo,
                       DATEDIFF(CURDATE(), COALESCE(m.proxima_data_vencimento, m.data_vencimento)) AS dias_vencido,
                       sm.codigo AS status
                FROM matriculas m
                INNER JOIN status_matricula sm ON sm.id = m.status_id
                INNER JOIN alunos a ON a.id = m.aluno_id
                INNER JOIN planos p ON p.id = m.plano_id
                WHERE m.tenant_id = :tid
                  AND sm.codigo = 'ativa'
                  AND COALESCE(m.proxima_data_vencimento, m.data_vencimento) < DATE_SUB(CURDATE(), INTERVAL 5 DAY)
                ORDER BY dias_vencido DESC
            ";
            $stmt = $db->prepare($sql);
            $stmt->execute(['tid' => $tenantId]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            if ($rows) {
                $anomalias[] = [
                    'tipo' => 'ativa_vencimento_expirado',
                    'descricao' => 'Matrículas ativas com vencimento expirado há mais de 5 dias',
                    'severidade' => 'alta',
                    'total' => count($rows),
                    'registros' => $rows,
                ];
            }

            // 4. Matrículas canceladas/vencidas com parcelas futuras pendentes
            $sql = "
                SELECT m.id AS matricula_id, a.nome AS aluno_nome, p.nome AS plano_nome,
                       sm.codigo AS status,
                       m.proxima_data_vencimento, m.data_vencimento,
                       COUNT(pp.id) AS parcelas_futuras_pendentes,
                       MIN(pp.data_vencimento) AS proxima_parcela
                FROM matriculas m
                INNER JOIN status_matricula sm ON sm.id = m.status_id
                INNER JOIN alunos a ON a.id = m.aluno_id
                INNER JOIN planos p ON p.id = m.plano_id
                INNER JOIN pagamentos_plano pp ON pp.matricula_id = m.id
                    AND pp.status_pagamento_id IN (1, 3)
                    AND pp.data_vencimento >= CURDATE()
                WHERE m.tenant_id = :tid
                  AND sm.codigo IN ('cancelada', 'vencida')
                GROUP BY m.id, a.nome, p.nome, sm.codigo, m.proxima_data_vencimento, m.data_vencimento
                ORDER BY a.nome
            ";
            $stmt = $db->prepare($sql);
            $stmt->execute(['tid' => $tenantId]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            if ($rows) {
                $anomalias[] = [
                    'tipo' => 'cancelada_com_parcelas_futuras',
                    'descricao' => 'Matrículas canceladas/vencidas que possuem parcelas futuras pendentes',
                    'severidade' => 'alta',
                    'total' => count($rows),
                    'registros' => $rows,
                ];
            }

            // 5. Matrículas duplicadas (mesmo aluno + modalidade, ambas ativas)
            $sql = "
                SELECT a.nome AS aluno_nome, mo.nome AS modalidade_nome,
                       GROUP_CONCAT(m.id ORDER BY m.id) AS matricula_ids,
                       GROUP_CONCAT(p.nome ORDER BY m.id SEPARATOR ' | ') AS planos,
                       COUNT(*) AS total
                FROM matriculas m
                INNER JOIN status_matricula sm ON sm.id = m.status_id
                INNER JOIN alunos a ON a.id = m.aluno_id
                INNER JOIN planos p ON p.id = m.plano_id
                INNER JOIN modalidades mo ON mo.id = p.modalidade_id
                WHERE m.tenant_id = :tid
                  AND sm.codigo = 'ativa'
                GROUP BY m.aluno_id, a.nome, p.modalidade_id, mo.nome
                HAVING COUNT(*) > 1
                ORDER BY a.nome
            ";
            $stmt = $db->prepare($sql);
            $stmt->execute(['tid' => $tenantId]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            if ($rows) {
                $anomalias[] = [
                    'tipo' => 'matriculas_duplicadas',
                    'descricao' => 'Mesmo aluno com múltiplas matrículas ativas na mesma modalidade',
                    'severidade' => 'media',
                    'total' => count($rows),
                    'registros' => $rows,
                ];
            }

            // 6. Matrículas ativas sem nenhuma parcela
            $sql = "
                SELECT m.id AS matricula_id, a.nome AS aluno_nome, p.nome AS plano_nome,
                       m.data_inicio, m.data_vencimento, m.proxima_data_vencimento,
                       m.tipo_cobranca, sm.codigo AS status
                FROM matriculas m
                INNER JOIN status_matricula sm ON sm.id = m.status_id
                INNER JOIN alunos a ON a.id = m.aluno_id
                INNER JOIN planos p ON p.id = m.plano_id
                LEFT JOIN pagamentos_plano pp ON pp.matricula_id = m.id AND pp.status_pagamento_id != 4
                WHERE m.tenant_id = :tid
                  AND sm.codigo = 'ativa'
                  AND pp.id IS NULL
                ORDER BY a.nome
            ";
            $stmt = $db->prepare($sql);
            $stmt->execute(['tid' => $tenantId]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            if ($rows) {
                $anomalias[] = [
                    'tipo' => 'ativa_sem_parcelas',
                    'descricao' => 'Matrículas ativas sem nenhuma parcela (não-cancelada)',
                    'severidade' => 'media',
                    'total' => count($rows),
                    'registros' => $rows,
                ];
            }

            $totalAnomalias = array_sum(array_column($anomalias, 'total'));

            $response->getBody()->write(json_encode([
                'resumo' => [
                    'total_anomalias' => $totalAnomalias,
                    'tipos_encontrados' => count($anomalias),
                ],
                'anomalias' => $anomalias,
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Erro ao verificar anomalias: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Repara proxima_data_vencimento de matrículas ativas onde o valor diverge da próxima parcela pendente.
     *
     * Para cada matrícula ativa que possui parcelas pendentes (status 1 ou 3), define
     * proxima_data_vencimento = MIN(data_vencimento das parcelas pendentes).
     *
     * POST /admin/auditoria/reparar-proxima-data-vencimento
     */
    public function repararProximaDataVencimento(Request $request, Response $response): Response
    {
        $tenantId = $request->getAttribute('tenant_id');
        $db = require __DIR__ . '/../../config/database.php';

        $queryParams = $request->getQueryParams();
        $dryRun = isset($queryParams['dry-run']) || isset($queryParams['dry_run']);

        try {
            // Buscar todas as matrículas ativas com divergência entre proxima_data_vencimento e MIN(parcela pendente)
            $sqlDetect = "
                SELECT m.id AS matricula_id,
                       a.nome AS aluno_nome,
                       m.proxima_data_vencimento AS valor_atual,
                       MIN(pp.data_vencimento) AS valor_correto
                FROM matriculas m
                INNER JOIN status_matricula sm ON sm.id = m.status_id AND sm.codigo = 'ativa'
                INNER JOIN alunos a ON a.id = m.aluno_id
                INNER JOIN pagamentos_plano pp ON pp.matricula_id = m.id
                    AND pp.status_pagamento_id IN (1, 3)
                WHERE m.tenant_id = :tid
                GROUP BY m.id, a.nome, m.proxima_data_vencimento
                HAVING m.proxima_data_vencimento IS NULL OR m.proxima_data_vencimento != MIN(pp.data_vencimento)
                ORDER BY a.nome
            ";
            $stmt = $db->prepare($sqlDetect);
            $stmt->execute(['tid' => $tenantId]);
            $casos = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $reparados = [];
            if (!$dryRun && !empty($casos)) {
                $stmtUpdate = $db->prepare("
                    UPDATE matriculas
                    SET proxima_data_vencimento = ?, updated_at = NOW()
                    WHERE id = ? AND tenant_id = ?
                ");
                foreach ($casos as $caso) {
                    $stmtUpdate->execute([$caso['valor_correto'], $caso['matricula_id'], $tenantId]);
                    if ($stmtUpdate->rowCount() > 0) {
                        $reparados[] = $caso;
                    }
                }
            }

            $response->getBody()->write(json_encode([
                'dry_run' => $dryRun,
                'total_divergentes' => count($casos),
                'total_reparados' => $dryRun ? 0 : count($reparados),
                'casos' => $dryRun ? $casos : $reparados,
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Erro ao reparar datas: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }
}
