<?php

namespace App\Services;

/**
 * Detecta inconsistências do bug de migração/alteração de plano:
 * parcelas fantasma R$ 0, MP cancelado → crédito, vencimento deslocado, assinatura da migração.
 */
class AuditoriaCreditoMigracaoService
{
    private \PDO $db;

    public function __construct(\PDO $db)
    {
        $this->db = $db;
    }

    /**
     * @return array{resumo: array<string, int>, registros: list<array<string, mixed>>}
     */
    public function auditar(int $tenantId): array
    {
        /** @var array<int, array<string, mixed>> */
        $porMatricula = [];

        $add = function (int $matriculaId, string $alunoNome, string $tipo, string $severidade, string $descricao, array $extra = []) use (&$porMatricula): void {
            if (!isset($porMatricula[$matriculaId])) {
                $porMatricula[$matriculaId] = [
                    'matricula_id' => $matriculaId,
                    'aluno_nome' => $alunoNome,
                    'status' => $extra['status'] ?? null,
                    'data_inicio' => $extra['data_inicio'] ?? null,
                    'data_vencimento' => $extra['data_vencimento'] ?? null,
                    'data_vencimento_esperada' => null,
                    'problemas' => [],
                ];
            }
            if (!empty($extra['status'])) {
                $porMatricula[$matriculaId]['status'] = $extra['status'];
            }
            if (!empty($extra['data_inicio'])) {
                $porMatricula[$matriculaId]['data_inicio'] = $extra['data_inicio'];
            }
            if (!empty($extra['data_vencimento'])) {
                $porMatricula[$matriculaId]['data_vencimento'] = $extra['data_vencimento'];
            }
            if (!empty($extra['data_vencimento_esperada'])) {
                $porMatricula[$matriculaId]['data_vencimento_esperada'] = $extra['data_vencimento_esperada'];
            }
            foreach ($porMatricula[$matriculaId]['problemas'] as $p) {
                if ($p['tipo'] === $tipo && $p['descricao'] === $descricao) {
                    return;
                }
            }
            $porMatricula[$matriculaId]['problemas'][] = [
                'tipo' => $tipo,
                'severidade' => $severidade,
                'descricao' => $descricao,
            ];
        };

        // 1) Parcelas fantasma de migração
        $stmt = $this->db->prepare("
            SELECT pp.id, pp.matricula_id, a.nome AS aluno_nome, pp.valor, pp.credito_aplicado,
                   pp.data_pagamento, pp.observacoes, sm.codigo AS status_matricula,
                   m.data_inicio, m.data_vencimento
            FROM pagamentos_plano pp
            INNER JOIN alunos a ON a.id = pp.aluno_id
            INNER JOIN matriculas m ON m.id = pp.matricula_id
            INNER JOIN status_matricula sm ON sm.id = m.status_id
            WHERE pp.tenant_id = ?
              AND pp.valor <= 0
              AND pp.data_pagamento IS NOT NULL
              AND (
                  pp.observacoes LIKE 'Migração de plano —%'
                  OR (COALESCE(pp.credito_aplicado, 0) > 0 AND pp.observacoes LIKE '%Migração de plano%')
              )
              AND pp.status_pagamento_id IN (2, 4)
            ORDER BY pp.id DESC
            LIMIT 200
        ");
        $stmt->execute([$tenantId]);
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
            $add(
                (int) $r['matricula_id'],
                $r['aluno_nome'],
                'parcela_fantasma_migracao',
                'alta',
                sprintf('Parcela #%d fantasma (R$ %s, crédito R$ %s)', $r['id'], $r['valor'], $r['credito_aplicado'] ?? '0'),
                [
                    'status' => $r['status_matricula'],
                    'data_inicio' => $r['data_inicio'],
                    'data_vencimento' => $r['data_vencimento'],
                ]
            );
        }

        // 2) Pagamento pago cancelado e convertido em crédito (valor > 0)
        $stmt = $this->db->prepare("
            SELECT pp.id, pp.matricula_id, a.nome AS aluno_nome, pp.valor, pp.data_pagamento,
                   sm.codigo AS status_matricula, m.data_inicio, m.data_vencimento
            FROM pagamentos_plano pp
            INNER JOIN alunos a ON a.id = pp.aluno_id
            INNER JOIN matriculas m ON m.id = pp.matricula_id
            INNER JOIN status_matricula sm ON sm.id = m.status_id
            WHERE pp.tenant_id = ?
              AND pp.status_pagamento_id = 4
              AND pp.data_pagamento IS NOT NULL
              AND pp.valor > 0
              AND (pp.observacoes IS NULL OR pp.observacoes NOT LIKE '%DUPLICADO%')
              AND (
                  (pp.observacoes LIKE '%Convertido em crédito%' AND (
                      pp.observacoes LIKE '%migração%' OR pp.observacoes LIKE '%alteração%'
                  ))
                  OR (pp.tipo_baixa_id = 4 AND pp.observacoes LIKE '%Mercado Pago%')
              )
            ORDER BY pp.id DESC
            LIMIT 200
        ");
        $stmt->execute([$tenantId]);
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
            $add(
                (int) $r['matricula_id'],
                $r['aluno_nome'],
                'pagamento_cancelado_credito',
                'alta',
                sprintf('Pagamento #%d pago cancelado (R$ %s em %s)', $r['id'], $r['valor'], $r['data_pagamento']),
                [
                    'status' => $r['status_matricula'],
                    'data_inicio' => $r['data_inicio'],
                    'data_vencimento' => $r['data_vencimento'],
                ]
            );
        }

        // 3) Créditos ativos do bug (ciclo encerrado / último pagamento)
        $stmt = $this->db->prepare("
            SELECT ca.id, ca.matricula_origem_id, a.nome AS aluno_nome, ca.valor,
                   sm.codigo AS status_matricula, m.data_inicio, m.data_vencimento
            FROM creditos_aluno ca
            INNER JOIN alunos a ON a.id = ca.aluno_id
            LEFT JOIN matriculas m ON m.id = ca.matricula_origem_id
            LEFT JOIN status_matricula sm ON sm.id = m.status_id
            WHERE ca.tenant_id = ?
              AND ca.status_credito_id = 1
              AND ca.matricula_origem_id IS NOT NULL
              AND (
                  ca.motivo LIKE '%ciclo encerrado%'
                  OR ca.motivo LIKE '%último pagamento%'
              )
            ORDER BY ca.id DESC
            LIMIT 200
        ");
        $stmt->execute([$tenantId]);
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
            $matId = (int) $r['matricula_origem_id'];
            $add(
                $matId,
                $r['aluno_nome'],
                'credito_indevido_ativo',
                'alta',
                sprintf('Crédito #%d ativo (R$ %s)', $r['id'], $r['valor']),
                [
                    'status' => $r['status_matricula'],
                    'data_inicio' => $r['data_inicio'],
                    'data_vencimento' => $r['data_vencimento'],
                ]
            );
        }

        // 4) Vencimento divergente do último pagamento legítimo
        $stmt = $this->db->prepare("
            SELECT m.id AS matricula_id, m.aluno_id, a.nome AS aluno_nome,
                   sm.codigo AS status_matricula, m.data_inicio, m.data_vencimento,
                   m.proxima_data_vencimento, pp.id AS pagamento_id, pp.data_pagamento,
                   pl.duracao_dias, pl.nome AS plano_nome
            FROM matriculas m
            INNER JOIN alunos a ON a.id = m.aluno_id
            INNER JOIN status_matricula sm ON sm.id = m.status_id
            INNER JOIN (
                SELECT matricula_id, MAX(data_pagamento) AS ultimo_pago
                FROM pagamentos_plano
                WHERE tenant_id = ?
                  AND status_pagamento_id = 2
                  AND data_pagamento IS NOT NULL
                  AND valor > 0
                  AND (observacoes IS NULL OR observacoes NOT LIKE '%Migração de plano%')
                GROUP BY matricula_id
            ) ult ON ult.matricula_id = m.id
            INNER JOIN pagamentos_plano pp ON pp.matricula_id = m.id
                AND pp.data_pagamento = ult.ultimo_pago
                AND pp.status_pagamento_id = 2
                AND pp.valor > 0
            INNER JOIN planos pl ON pl.id = pp.plano_id
            WHERE m.tenant_id = ?
            GROUP BY m.id, m.aluno_id, a.nome, sm.codigo, m.data_inicio, m.data_vencimento,
                     m.proxima_data_vencimento, pp.id, pp.data_pagamento, pl.duracao_dias, pl.nome
            ORDER BY m.id DESC
            LIMIT 500
        ");
        $stmt->execute([$tenantId, $tenantId]);
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
            $esperada = $this->calcularVencimentoEsperado(
                (string) $r['data_pagamento'],
                (int) ($r['duracao_dias'] ?? 0),
                (int) $r['matricula_id'],
                (int) $r['pagamento_id']
            );
            if (!$esperada) {
                continue;
            }
            $atual = (string) ($r['data_vencimento'] ?? '');
            $proxima = (string) ($r['proxima_data_vencimento'] ?? '');
            if ($atual === $esperada && $proxima === $esperada) {
                continue;
            }
            $add(
                (int) $r['matricula_id'],
                $r['aluno_nome'],
                'vencimento_divergente',
                'alta',
                sprintf(
                    'Vencimento %s / próxima %s — esperado %s (pagamento #%s em %s)',
                    $atual ?: '-',
                    $proxima ?: '-',
                    $esperada,
                    $r['pagamento_id'],
                    $r['data_pagamento']
                ),
                [
                    'status' => $r['status_matricula'],
                    'data_inicio' => $r['data_inicio'],
                    'data_vencimento' => $atual,
                    'data_vencimento_esperada' => $esperada,
                ]
            );
        }

        // 5) Assinatura criada na migração (data_inicio após último pagamento)
        try {
            $stmt = $this->db->prepare("
                SELECT ass.id AS assinatura_id, ass.matricula_id, a.nome AS aluno_nome,
                       ass.data_inicio AS ass_inicio, ass.data_fim AS ass_fim,
                       sm.codigo AS status_matricula, m.data_inicio, m.data_vencimento,
                       ult.data_pagamento AS ultimo_pago
                FROM assinaturas ass
                INNER JOIN matriculas m ON m.id = ass.matricula_id
                INNER JOIN alunos a ON a.id = m.aluno_id
                INNER JOIN status_matricula sm ON sm.id = m.status_id
                INNER JOIN (
                    SELECT matricula_id, MAX(data_pagamento) AS data_pagamento
                    FROM pagamentos_plano
                    WHERE tenant_id = ?
                      AND status_pagamento_id = 2
                      AND data_pagamento IS NOT NULL
                      AND valor > 0
                    GROUP BY matricula_id
                ) ult ON ult.matricula_id = ass.matricula_id
                WHERE ass.tenant_id = ?
                  AND ass.data_inicio > ult.data_pagamento
                ORDER BY ass.id DESC
                LIMIT 200
            ");
            $stmt->execute([$tenantId, $tenantId]);
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
                $add(
                    (int) $r['matricula_id'],
                    $r['aluno_nome'],
                    'assinatura_migracao',
                    'media',
                    sprintf(
                        'Assinatura #%s da migração (%s → %s); último pago %s',
                        $r['assinatura_id'],
                        $r['ass_inicio'],
                        $r['ass_fim'] ?? '-',
                        $r['ultimo_pago']
                    ),
                    [
                        'status' => $r['status_matricula'],
                        'data_inicio' => $r['data_inicio'],
                        'data_vencimento' => $r['data_vencimento'],
                    ]
                );
            }
        } catch (\Throwable $e) {
            // tabela assinaturas pode não existir em alguns ambientes
        }

        $registros = array_values($porMatricula);
        usort($registros, fn ($a, $b) => $a['matricula_id'] <=> $b['matricula_id']);

        $contagens = [
            'parcela_fantasma_migracao' => 0,
            'pagamento_cancelado_credito' => 0,
            'credito_indevido_ativo' => 0,
            'vencimento_divergente' => 0,
            'assinatura_migracao' => 0,
        ];
        foreach ($registros as $reg) {
            foreach ($reg['problemas'] as $p) {
                if (isset($contagens[$p['tipo']])) {
                    $contagens[$p['tipo']]++;
                }
            }
        }

        return [
            'resumo' => array_merge(
                ['total_matriculas' => count($registros)],
                $contagens
            ),
            'registros' => $registros,
        ];
    }

    private function calcularVencimentoEsperado(string $dataPagamento, int $duracaoDias, int $matriculaId, int $pagamentoId): ?string
    {
        if ($dataPagamento === '') {
            return null;
        }

        $stmt = $this->db->prepare("
            SELECT data_vencimento FROM pagamentos_plano
            WHERE matricula_id = ? AND id > ? AND data_vencimento > ?
              AND (observacoes IS NULL OR observacoes NOT LIKE '%Migração de plano%')
            ORDER BY data_vencimento ASC LIMIT 1
        ");
        $stmt->execute([$matriculaId, $pagamentoId, $dataPagamento]);
        $prox = $stmt->fetchColumn();
        if ($prox) {
            return (string) $prox;
        }

        $dt = new \DateTime($dataPagamento);
        if ($duracaoDias > 0 && $duracaoDias <= 31) {
            $dt->modify("+{$duracaoDias} days");
        } else {
            $dt->modify('+30 days');
        }

        return $dt->format('Y-m-d');
    }
}
