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

    /**
     * Check-ins acima do limite contratado (mensal e semanal)
     * GET /admin/auditoria/checkins-acima-do-limite
     *
     * Query params:
     *   ano  (int, default: ano atual)
     *   mes  (int, default: mês atual)
     */
    public function checkinsAcimaDoLimite(Request $request, Response $response): Response
    {
        $tenantId = $request->getAttribute('tenant_id');
        $db = require __DIR__ . '/../../config/database.php';

        $params = $request->getQueryParams();
        $ano = !empty($params['ano']) ? (int) $params['ano'] : (int) date('Y');
        $mes = !empty($params['mes']) ? (int) $params['mes'] : (int) date('m');

        // Bônus se o mês tem 5 semanas (domingo–sábado)
        $primeiroDia = new \DateTime(sprintf('%04d-%02d-01', $ano, $mes));
        $diaSemanaInicio = (int) $primeiroDia->format('w');
        $diasNoMes = (int) $primeiroDia->format('t');
        $bonusCincoSemanas = ((int) ceil(($diasNoMes + $diaSemanaInicio) / 7) >= 5) ? 1 : 0;

        try {
            // ─── 1. Violações MENSAIS (permite_reposicao = 1) ─────────────────
            $sqlMensal = "
                SELECT
                    sub.aluno_id,
                    u.nome              AS aluno_nome,
                    sub.modalidade_id,
                    mo.nome             AS modalidade_nome,
                    sub.total_checkins,
                    sub.checkin_ids,
                    p.nome              AS plano_nome,
                    p.checkins_semanais
                FROM (
                    SELECT
                        a.id            AS aluno_id,
                        t.modalidade_id,
                        COUNT(*)        AS total_checkins,
                        GROUP_CONCAT(c.id ORDER BY c.id SEPARATOR ',') AS checkin_ids
                    FROM checkins c
                    INNER JOIN alunos  a ON a.id = c.aluno_id
                    INNER JOIN turmas  t ON t.id = c.turma_id
                    WHERE t.tenant_id = :tenant_id
                      AND (c.presente IS NULL OR c.presente = 1)
                      AND YEAR(COALESCE(c.data_checkin_date, DATE(c.created_at)))  = :ano
                      AND MONTH(COALESCE(c.data_checkin_date, DATE(c.created_at))) = :mes
                    GROUP BY a.id, t.modalidade_id
                ) sub
                INNER JOIN alunos     a  ON a.id  = sub.aluno_id
                INNER JOIN usuarios   u  ON u.id  = a.usuario_id
                INNER JOIN modalidades mo ON mo.id = sub.modalidade_id
                INNER JOIN matriculas  m  ON m.id = (
                    SELECT m2.id FROM matriculas m2
                    INNER JOIN planos p2 ON p2.id = m2.plano_id
                    WHERE m2.aluno_id    = sub.aluno_id
                      AND m2.tenant_id  = :tenant_id2
                      AND p2.modalidade_id = sub.modalidade_id
                    ORDER BY m2.data_matricula DESC
                    LIMIT 1
                )
                INNER JOIN planos       p  ON p.id  = m.plano_id
                LEFT  JOIN plano_ciclos pc ON pc.id = m.plano_ciclo_id
                WHERE COALESCE(pc.permite_reposicao, 0) = 1
                ORDER BY u.nome
            ";
            $stmtM = $db->prepare($sqlMensal);
            $stmtM->execute([
                'tenant_id'  => $tenantId,
                'tenant_id2' => $tenantId,
                'ano'        => $ano,
                'mes'        => $mes,
            ]);
            $rowsMensal = $stmtM->fetchAll(\PDO::FETCH_ASSOC);

            $violacoesMensais = [];
            foreach ($rowsMensal as $r) {
                $limite = (int) $r['checkins_semanais'] * 4 + $bonusCincoSemanas;
                if ((int) $r['total_checkins'] > $limite) {
                    $violacoesMensais[] = [
                        'aluno_id'       => (int) $r['aluno_id'],
                        'aluno_nome'     => $r['aluno_nome'],
                        'modalidade_id'  => (int) $r['modalidade_id'],
                        'modalidade'     => $r['modalidade_nome'],
                        'plano'          => $r['plano_nome'],
                        'limite_mensal'  => $limite,
                        'total_checkins' => (int) $r['total_checkins'],
                        'excesso'        => (int) $r['total_checkins'] - $limite,
                        'checkin_ids'    => $r['checkin_ids'],
                    ];
                }
            }

            // ─── 2. Violações SEMANAIS (permite_reposicao = 0) ───────────────
            $sqlSemanal = "
                SELECT
                    a.id              AS aluno_id,
                    u.nome            AS aluno_nome,
                    t.modalidade_id,
                    mo.nome           AS modalidade_nome,
                    YEARWEEK(COALESCE(c.data_checkin_date, DATE(c.created_at)), 0) AS semana_ano,
                    MIN(COALESCE(c.data_checkin_date, DATE(c.created_at)))          AS semana_inicio,
                    MAX(COALESCE(c.data_checkin_date, DATE(c.created_at)))          AS semana_fim,
                    COUNT(*)          AS total_checkins,
                    GROUP_CONCAT(c.id ORDER BY c.id SEPARATOR ',') AS checkin_ids,
                    p.nome            AS plano_nome,
                    p.checkins_semanais
                FROM checkins c
                INNER JOIN alunos     a  ON a.id  = c.aluno_id
                INNER JOIN usuarios   u  ON u.id  = a.usuario_id
                INNER JOIN turmas     t  ON t.id  = c.turma_id
                INNER JOIN modalidades mo ON mo.id = t.modalidade_id
                INNER JOIN matriculas  m  ON m.id = (
                    SELECT m2.id FROM matriculas m2
                    INNER JOIN planos p2 ON p2.id = m2.plano_id
                    WHERE m2.aluno_id    = a.id
                      AND m2.tenant_id  = :tenant_id2
                      AND p2.modalidade_id = t.modalidade_id
                    ORDER BY m2.data_matricula DESC
                    LIMIT 1
                )
                INNER JOIN planos       p  ON p.id  = m.plano_id
                LEFT  JOIN plano_ciclos pc ON pc.id = m.plano_ciclo_id
                WHERE t.tenant_id = :tenant_id
                  AND (c.presente IS NULL OR c.presente = 1)
                  AND YEAR(COALESCE(c.data_checkin_date, DATE(c.created_at)))  = :ano
                  AND MONTH(COALESCE(c.data_checkin_date, DATE(c.created_at))) = :mes
                  AND COALESCE(pc.permite_reposicao, 0) = 0
                GROUP BY a.id, u.nome, t.modalidade_id, mo.nome,
                         YEARWEEK(COALESCE(c.data_checkin_date, DATE(c.created_at)), 0),
                         p.nome, p.checkins_semanais
                HAVING COUNT(*) > p.checkins_semanais
                ORDER BY u.nome, semana_ano
            ";
            $stmtS = $db->prepare($sqlSemanal);
            $stmtS->execute([
                'tenant_id'  => $tenantId,
                'tenant_id2' => $tenantId,
                'ano'        => $ano,
                'mes'        => $mes,
            ]);
            $rowsSemanal = $stmtS->fetchAll(\PDO::FETCH_ASSOC);

            $violacoesSemanais = array_map(fn($r) => [
                'aluno_id'       => (int) $r['aluno_id'],
                'aluno_nome'     => $r['aluno_nome'],
                'modalidade_id'  => (int) $r['modalidade_id'],
                'modalidade'     => $r['modalidade_nome'],
                'plano'          => $r['plano_nome'],
                'semana_ano'     => $r['semana_ano'],
                'semana_inicio'  => $r['semana_inicio'],
                'semana_fim'     => $r['semana_fim'],
                'limite_semanal' => (int) $r['checkins_semanais'],
                'total_checkins' => (int) $r['total_checkins'],
                'excesso'        => (int) $r['total_checkins'] - (int) $r['checkins_semanais'],
                'checkin_ids'    => $r['checkin_ids'],
            ], $rowsSemanal);

            $response->getBody()->write(json_encode([
                'periodo' => [
                    'ano'               => $ano,
                    'mes'               => $mes,
                    'bonus_cinco_semanas' => (bool) $bonusCincoSemanas,
                ],
                'resumo' => [
                    'total_violacoes_mensais'  => count($violacoesMensais),
                    'total_violacoes_semanais' => count($violacoesSemanais),
                ],
                'violacoes_mensais'  => $violacoesMensais,
                'violacoes_semanais' => $violacoesSemanais,
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'type'    => 'error',
                'message' => 'Erro ao verificar check-ins acima do limite: ' . $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Check-ins múltiplos no mesmo dia (mesmo aluno, mesma data)
     * GET /admin/auditoria/checkins-multiplos-no-dia
     *
     * Query params:
     *   data_inicio     (Y-m-d, default: primeiro dia do mês atual)
     *   data_fim        (Y-m-d, default: hoje)
     *   aluno_id        (int, opcional)
     *   modalidade_id   (int, opcional)
     *   mesma_modalidade (1 = agrupa também por modalidade, detectando dup na mesma modalidade;
     *                     0 = qualquer dup no mesmo dia, default: 0)
     */
    public function checkinsMultiplosNoDia(Request $request, Response $response): Response
    {
        $tenantId = $request->getAttribute('tenant_id');
        $db = require __DIR__ . '/../../config/database.php';

        $params = $request->getQueryParams();
        $dataInicio      = !empty($params['data_inicio'])    ? $params['data_inicio']           : date('Y-m-01');
        $dataFim         = !empty($params['data_fim'])       ? $params['data_fim']               : date('Y-m-d');
        $filtroAlunoId   = !empty($params['aluno_id'])       ? (int) $params['aluno_id']         : null;
        $filtroModId     = !empty($params['modalidade_id'])  ? (int) $params['modalidade_id']    : null;
        $mesmaModalidade = isset($params['mesma_modalidade']) && $params['mesma_modalidade'] === '1';

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataInicio)) $dataInicio = date('Y-m-01');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataFim))    $dataFim    = date('Y-m-d');

        try {
            if ($mesmaModalidade) {
                $selectExtra = 't.modalidade_id, mo.nome AS modalidade_nome,';
                $groupBy     = 'a.id, u.nome, COALESCE(c.data_checkin_date, DATE(c.created_at)), t.modalidade_id, mo.nome';
            } else {
                $selectExtra = 'NULL AS modalidade_id, NULL AS modalidade_nome,';
                $groupBy     = 'a.id, u.nome, COALESCE(c.data_checkin_date, DATE(c.created_at))';
            }

            $sql = "
                SELECT
                    a.id   AS aluno_id,
                    u.nome AS aluno_nome,
                    COALESCE(c.data_checkin_date, DATE(c.created_at)) AS data,
                    {$selectExtra}
                    COUNT(*) AS total_checkins,
                    GROUP_CONCAT(c.id      ORDER BY c.created_at SEPARATOR ',')   AS checkin_ids,
                    GROUP_CONCAT(mo.nome   ORDER BY c.created_at SEPARATOR ' | ') AS modalidades_do_dia
                FROM checkins c
                INNER JOIN alunos     a  ON a.id  = c.aluno_id
                INNER JOIN usuarios   u  ON u.id  = a.usuario_id
                INNER JOIN turmas     t  ON t.id  = c.turma_id
                LEFT  JOIN modalidades mo ON mo.id = t.modalidade_id
                WHERE t.tenant_id = :tenant_id
                  AND (c.presente IS NULL OR c.presente = 1)
                  AND COALESCE(c.data_checkin_date, DATE(c.created_at)) BETWEEN :data_inicio AND :data_fim
            ";

            $queryParams = [
                'tenant_id'   => $tenantId,
                'data_inicio' => $dataInicio,
                'data_fim'    => $dataFim,
            ];

            if ($filtroAlunoId) {
                $sql .= ' AND a.id = :aluno_id';
                $queryParams['aluno_id'] = $filtroAlunoId;
            }
            if ($filtroModId) {
                $sql .= ' AND t.modalidade_id = :modalidade_id';
                $queryParams['modalidade_id'] = $filtroModId;
            }

            $sql .= " GROUP BY {$groupBy} HAVING COUNT(*) > 1 ORDER BY data DESC, u.nome";

            $stmt = $db->prepare($sql);
            $stmt->execute($queryParams);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $resultados = array_map(fn($r) => [
                'aluno_id'          => (int) $r['aluno_id'],
                'aluno_nome'        => $r['aluno_nome'],
                'data'              => $r['data'],
                'modalidade_id'     => $r['modalidade_id'] !== null ? (int) $r['modalidade_id'] : null,
                'modalidade'        => $r['modalidade_nome'] ?? null,
                'modalidades_do_dia' => $r['modalidades_do_dia'],
                'total_checkins'    => (int) $r['total_checkins'],
                'checkin_ids'       => $r['checkin_ids'],
            ], $rows);

            $response->getBody()->write(json_encode([
                'filtros' => [
                    'data_inicio'      => $dataInicio,
                    'data_fim'         => $dataFim,
                    'mesma_modalidade' => $mesmaModalidade,
                ],
                'total'     => count($resultados),
                'registros' => $resultados,
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'type'    => 'error',
                'message' => 'Erro ao verificar check-ins múltiplos no dia: ' . $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }
}
