<?php

namespace App\Services\Admin;

use App\Services\AuditoriaCreditoMigracaoService;
use Illuminate\Support\Facades\DB;

class AdminAuditoriaService
{
    public function __construct(
        private readonly AuditoriaCreditoMigracaoService $creditoMigracao,
    ) {}

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function pagamentosDuplicados(int $tenantId): array
    {
        try {
            $grupos = $this->select("
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
            ", ['tenant_id' => $tenantId]);

            $contagem = $this->selectOne("
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
            ", ['tenant_id' => $tenantId]) ?? [];

            return [
                'status' => 200,
                'body' => [
                    'resumo' => [
                        'total_grupos_duplicados' => (int) ($contagem['total_grupos_duplicados'] ?? 0),
                        'total_pagamentos_envolvidos' => (int) ($contagem['total_pagamentos_envolvidos'] ?? 0),
                    ],
                    'grupos' => $grupos,
                ],
            ];
        } catch (\Exception $e) {
            return $this->error('Erro ao buscar pagamentos duplicados: '.$e->getMessage(), 500);
        }
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function pagamentosDuplicadosDetalhe(
        int $tenantId,
        ?int $filtroAlunoId,
        ?int $filtroMatriculaId,
        ?int $filtroAno,
        ?int $filtroMes,
    ): array {
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
                $sql .= ' AND pp.aluno_id = :aluno_id';
                $params['aluno_id'] = $filtroAlunoId;
            }
            if ($filtroMatriculaId) {
                $sql .= ' AND pp.matricula_id = :matricula_id';
                $params['matricula_id'] = $filtroMatriculaId;
            }
            if ($filtroAno) {
                $sql .= ' AND YEAR(pp.data_vencimento) = :ano';
                $params['ano'] = $filtroAno;
            }
            if ($filtroMes) {
                $sql .= ' AND MONTH(pp.data_vencimento) = :mes';
                $params['mes'] = $filtroMes;
            }

            $sql .= ' ORDER BY a.nome, pp.data_vencimento, pp.id';

            $pagamentos = $this->select($sql, $params);

            return [
                'status' => 200,
                'body' => [
                    'total' => count($pagamentos),
                    'pagamentos' => $pagamentos,
                ],
            ];
        } catch (\Exception $e) {
            return $this->error('Erro ao buscar detalhe de duplicados: '.$e->getMessage(), 500);
        }
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function anomaliasDatas(int $tenantId): array
    {
        try {
            $anomalias = [];

            // 1. proxima_data_vencimento NULL em matrículas ativas
            $rows = $this->select("
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
            ", ['tid' => $tenantId]);
            if ($rows) {
                $anomalias[] = [
                    'tipo' => 'proxima_data_vencimento_null',
                    'descricao' => 'Matrículas ativas com proxima_data_vencimento NULL',
                    'severidade' => 'alta',
                    'total' => count($rows),
                    'registros' => $rows,
                ];
            }

            // 2. proxima_data_vencimento desatualizada
            // Pacote: proxima = fim do período do contrato; parcela pendente futura
            // é renovação do pacote (ex. bimestre) — não é divergência.
            $rows = $this->select("
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
                  AND m.pacote_contrato_id IS NULL
                GROUP BY m.id, a.nome, p.nome, m.proxima_data_vencimento, sm.codigo
                HAVING ABS(DATEDIFF(m.proxima_data_vencimento, MIN(pp.data_vencimento))) > 3
                ORDER BY a.nome
            ", ['tid' => $tenantId]);
            if ($rows) {
                $anomalias[] = [
                    'tipo' => 'proxima_data_vencimento_desatualizada',
                    'descricao' => 'Matrículas ativas (fora de pacote) onde proxima_data_vencimento diverge da próxima parcela pendente em mais de 3 dias (tolera atraso de pagamento vs ciclo)',
                    'severidade' => 'media',
                    'total' => count($rows),
                    'registros' => $rows,
                ];
            }

            // 3. Matrículas ativas com vencimento expirado (> 5 dias)
            $rows = $this->select("
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
            ", ['tid' => $tenantId]);
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
            $rows = $this->select("
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
            ", ['tid' => $tenantId]);
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
            $rows = $this->select("
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
            ", ['tid' => $tenantId]);
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
            $rows = $this->select("
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
            ", ['tid' => $tenantId]);
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

            return [
                'status' => 200,
                'body' => [
                    'resumo' => [
                        'total_anomalias' => $totalAnomalias,
                        'tipos_encontrados' => count($anomalias),
                    ],
                    'anomalias' => $anomalias,
                ],
            ];
        } catch (\Exception $e) {
            return $this->error('Erro ao verificar anomalias: '.$e->getMessage(), 500);
        }
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function repararProximaDataVencimento(int $tenantId, bool $dryRun): array
    {
        try {
            $casos = $this->select("
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
                  AND m.pacote_contrato_id IS NULL
                GROUP BY m.id, a.nome, m.proxima_data_vencimento
                HAVING m.proxima_data_vencimento IS NULL
                    OR ABS(DATEDIFF(m.proxima_data_vencimento, MIN(pp.data_vencimento))) > 3
                ORDER BY a.nome
            ", ['tid' => $tenantId]);

            $reparados = [];
            if (! $dryRun && ! empty($casos)) {
                foreach ($casos as $caso) {
                    $affected = DB::update('
                        UPDATE matriculas
                        SET proxima_data_vencimento = ?, updated_at = NOW()
                        WHERE id = ? AND tenant_id = ?
                    ', [$caso['valor_correto'], $caso['matricula_id'], $tenantId]);
                    if ($affected > 0) {
                        $reparados[] = $caso;
                    }
                }
            }

            return [
                'status' => 200,
                'body' => [
                    'dry_run' => $dryRun,
                    'total_divergentes' => count($casos),
                    'total_reparados' => $dryRun ? 0 : count($reparados),
                    'casos' => $dryRun ? $casos : $reparados,
                ],
            ];
        } catch (\Exception $e) {
            return $this->error('Erro ao reparar datas: '.$e->getMessage(), 500);
        }
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function checkinsAcimaDoLimite(int $tenantId, int $ano, int $mes): array
    {
        $primeiroDia = new \DateTime(sprintf('%04d-%02d-01', $ano, $mes));
        $diasNoMes = (int) $primeiroDia->format('t');
        $bonusCincoSemanas = ((int) ceil($diasNoMes / 7) >= 5) ? 1 : 0;

        try {
            $rowsMensal = $this->select("
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
                    INNER JOIN alunos  a ON a.id  = c.aluno_id
                    INNER JOIN turmas  t ON t.id  = c.turma_id
                    INNER JOIN dias    d ON d.id  = t.dia_id
                    WHERE t.tenant_id = :tenant_id
                      AND (c.presente IS NULL OR c.presente = 1)
                      AND YEAR(DATE(d.data))  = :ano
                      AND MONTH(DATE(d.data)) = :mes
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
            ", [
                'tenant_id' => $tenantId,
                'tenant_id2' => $tenantId,
                'ano' => $ano,
                'mes' => $mes,
            ]);

            $violacoesMensais = [];
            foreach ($rowsMensal as $r) {
                $limite = (int) $r['checkins_semanais'] * 4 + $bonusCincoSemanas;
                if ((int) $r['total_checkins'] > $limite) {
                    $violacoesMensais[] = [
                        'aluno_id' => (int) $r['aluno_id'],
                        'aluno_nome' => $r['aluno_nome'],
                        'modalidade_id' => (int) $r['modalidade_id'],
                        'modalidade' => $r['modalidade_nome'],
                        'plano' => $r['plano_nome'],
                        'limite_mensal' => $limite,
                        'total_checkins' => (int) $r['total_checkins'],
                        'excesso' => (int) $r['total_checkins'] - $limite,
                        'checkin_ids' => $r['checkin_ids'],
                    ];
                }
            }

            $rowsSemanal = $this->select("
                SELECT
                    a.id              AS aluno_id,
                    u.nome            AS aluno_nome,
                    t.modalidade_id,
                    mo.nome           AS modalidade_nome,
                    YEARWEEK(DATE(d.data), 0) AS semana_ano,
                    MIN(DATE(d.data))          AS semana_inicio,
                    MAX(DATE(d.data))          AS semana_fim,
                    COUNT(*)          AS total_checkins,
                    GROUP_CONCAT(c.id ORDER BY c.id SEPARATOR ',') AS checkin_ids,
                    p.nome            AS plano_nome,
                    p.checkins_semanais
                FROM checkins c
                INNER JOIN alunos     a  ON a.id  = c.aluno_id
                INNER JOIN usuarios   u  ON u.id  = a.usuario_id
                INNER JOIN turmas     t  ON t.id  = c.turma_id
                INNER JOIN dias       d  ON d.id  = t.dia_id
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
                  AND YEAR(DATE(d.data))  = :ano
                  AND MONTH(DATE(d.data)) = :mes
                  AND COALESCE(pc.permite_reposicao, 0) = 0
                GROUP BY a.id, u.nome, t.modalidade_id, mo.nome,
                         YEARWEEK(DATE(d.data), 0),
                         p.nome, p.checkins_semanais
                HAVING COUNT(*) > p.checkins_semanais
                ORDER BY u.nome, semana_ano
            ", [
                'tenant_id' => $tenantId,
                'tenant_id2' => $tenantId,
                'ano' => $ano,
                'mes' => $mes,
            ]);

            $violacoesSemanais = array_map(fn ($r) => [
                'aluno_id' => (int) $r['aluno_id'],
                'aluno_nome' => $r['aluno_nome'],
                'modalidade_id' => (int) $r['modalidade_id'],
                'modalidade' => $r['modalidade_nome'],
                'plano' => $r['plano_nome'],
                'semana_ano' => $r['semana_ano'],
                'semana_inicio' => $r['semana_inicio'],
                'semana_fim' => $r['semana_fim'],
                'limite_semanal' => (int) $r['checkins_semanais'],
                'total_checkins' => (int) $r['total_checkins'],
                'excesso' => (int) $r['total_checkins'] - (int) $r['checkins_semanais'],
                'checkin_ids' => $r['checkin_ids'],
            ], $rowsSemanal);

            return [
                'status' => 200,
                'body' => [
                    'periodo' => [
                        'ano' => $ano,
                        'mes' => $mes,
                        'bonus_cinco_semanas' => (bool) $bonusCincoSemanas,
                    ],
                    'resumo' => [
                        'total_violacoes_mensais' => count($violacoesMensais),
                        'total_violacoes_semanais' => count($violacoesSemanais),
                    ],
                    'violacoes_mensais' => $violacoesMensais,
                    'violacoes_semanais' => $violacoesSemanais,
                ],
            ];
        } catch (\Exception $e) {
            return $this->error('Erro ao verificar check-ins acima do limite: '.$e->getMessage(), 500);
        }
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function checkinsMultiplosNoDia(
        int $tenantId,
        string $dataInicio,
        string $dataFim,
        ?int $filtroAlunoId,
        ?int $filtroModId,
        bool $mesmaModalidade,
    ): array {
        try {
            if ($mesmaModalidade) {
                $selectExtra = 't.modalidade_id, mo.nome AS modalidade_nome,';
                $groupBy = 'a.id, u.nome, DATE(d.data), t.modalidade_id, mo.nome';
            } else {
                $selectExtra = 'NULL AS modalidade_id, NULL AS modalidade_nome,';
                $groupBy = 'a.id, u.nome, DATE(d.data)';
            }

            $sql = "
                SELECT
                    a.id        AS aluno_id,
                    u.nome      AS aluno_nome,
                    DATE(d.data) AS data,
                    {$selectExtra}
                    COUNT(*) AS total_checkins,
                    GROUP_CONCAT(c.id      ORDER BY c.created_at SEPARATOR ',')   AS checkin_ids,
                    GROUP_CONCAT(mo.nome   ORDER BY c.created_at SEPARATOR ' | ') AS modalidades_do_dia
                FROM checkins c
                INNER JOIN alunos     a  ON a.id  = c.aluno_id
                INNER JOIN usuarios   u  ON u.id  = a.usuario_id
                INNER JOIN turmas     t  ON t.id  = c.turma_id
                INNER JOIN dias       d  ON d.id  = t.dia_id
                LEFT  JOIN modalidades mo ON mo.id = t.modalidade_id
                WHERE t.tenant_id = :tenant_id
                  AND (c.presente IS NULL OR c.presente = 1)
                  AND DATE(d.data) BETWEEN :data_inicio AND :data_fim
            ";

            $queryParams = [
                'tenant_id' => $tenantId,
                'data_inicio' => $dataInicio,
                'data_fim' => $dataFim,
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

            $rows = $this->select($sql, $queryParams);

            $resultados = array_map(fn ($r) => [
                'aluno_id' => (int) $r['aluno_id'],
                'aluno_nome' => $r['aluno_nome'],
                'data' => $r['data'],
                'modalidade_id' => $r['modalidade_id'] !== null ? (int) $r['modalidade_id'] : null,
                'modalidade' => $r['modalidade_nome'] ?? null,
                'modalidades_do_dia' => $r['modalidades_do_dia'],
                'total_checkins' => (int) $r['total_checkins'],
                'checkin_ids' => $r['checkin_ids'],
            ], $rows);

            return [
                'status' => 200,
                'body' => [
                    'filtros' => [
                        'data_inicio' => $dataInicio,
                        'data_fim' => $dataFim,
                        'mesma_modalidade' => $mesmaModalidade,
                    ],
                    'total' => count($resultados),
                    'registros' => $resultados,
                ],
            ];
        } catch (\Exception $e) {
            return $this->error('Erro ao verificar check-ins múltiplos no dia: '.$e->getMessage(), 500);
        }
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function creditoMigracaoPlano(int $tenantId): array
    {
        try {
            return [
                'status' => 200,
                'body' => $this->creditoMigracao->auditar($tenantId),
            ];
        } catch (\Exception $e) {
            return $this->error('Erro ao auditar crédito/migração de plano: '.$e->getMessage(), 500);
        }
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function repararVencimentoMatricula(int $tenantId, int $matriculaId): array
    {
        if ($matriculaId <= 0) {
            return [
                'status' => 422,
                'body' => [
                    'ok' => false,
                    'message' => 'ID da matrícula inválido',
                ],
            ];
        }

        try {
            $resultado = $this->creditoMigracao->repararVencimentoMatricula($tenantId, $matriculaId);

            return [
                'status' => ($resultado['ok'] ?? false) ? 200 : 400,
                'body' => $resultado,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 500,
                'body' => [
                    'ok' => false,
                    'message' => 'Erro ao reparar vencimento: '.$e->getMessage(),
                ],
            ];
        }
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    private function error(string $message, int $status): array
    {
        return [
            'status' => $status,
            'body' => [
                'type' => 'error',
                'message' => $message,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $bindings
     * @return list<array<string, mixed>>
     */
    private function select(string $sql, array $bindings = []): array
    {
        return array_map(
            static fn ($row) => (array) $row,
            DB::select($sql, $bindings)
        );
    }

    /**
     * @param  array<string, mixed>  $bindings
     * @return array<string, mixed>|null
     */
    private function selectOne(string $sql, array $bindings = []): ?array
    {
        $rows = $this->select($sql, $bindings);

        return $rows[0] ?? null;
    }
}
