<?php

namespace App\Services;

/**
 * Detecta inconsistências do bug de migração/alteração de plano:
 * parcelas fantasma R$ 0, MP cancelado → crédito, vencimento deslocado por reset de datas.
 *
 * Sinais fracos (1 dia / aniversário do mês / planos multi-mês sem reset) são ignorados.
 */
class AuditoriaCreditoMigracaoService
{
    private const TOLERANCIA_DIAS = 2;

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
              AND (ca.motivo IS NULL OR ca.motivo NOT LIKE '%proporcional%')
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

        // 4) Reset forte de datas (início ≥3 dias após pagamento) e vencimento fora de todos os ciclos válidos
        $stmt = $this->db->prepare("
            SELECT m.id AS matricula_id, a.nome AS aluno_nome,
                   sm.codigo AS status_matricula, m.data_inicio, m.data_vencimento,
                   m.proxima_data_vencimento, pp.id AS pagamento_id, pp.data_pagamento,
                   pp.data_vencimento AS pagamento_vencimento,
                   pl.duracao_dias, pl.nome AS plano_nome,
                   DATEDIFF(m.data_inicio, ult.ultimo_pago) AS dias_apos_pago
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
              AND DATEDIFF(m.data_inicio, ult.ultimo_pago) >= 3
            GROUP BY m.id, a.nome, sm.codigo, m.data_inicio, m.data_vencimento,
                     m.proxima_data_vencimento, pp.id, pp.data_pagamento, pp.data_vencimento,
                     pl.duracao_dias, pl.nome, dias_apos_pago
            ORDER BY m.id DESC
            LIMIT 500
        ");
        $stmt->execute([$tenantId, $tenantId]);
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
            $candidatos = $this->candidatosVencimento(
                (string) $r['data_pagamento'],
                (int) ($r['duracao_dias'] ?? 0),
                (int) $r['matricula_id'],
                (int) $r['pagamento_id'],
                (string) ($r['data_inicio'] ?? ''),
                (string) ($r['pagamento_vencimento'] ?? '')
            );
            if ($candidatos === []) {
                continue;
            }

            $atual = (string) ($r['data_vencimento'] ?? '');
            $proxima = (string) ($r['proxima_data_vencimento'] ?? '');
            $melhor = $this->melhorCandidato($atual, $candidatos);
            if ($melhor === null) {
                continue;
            }

            // Aceita aniversário do pagamento, da parcela, do início ou próxima parcela gerada
            if (
                $this->datasProximas($atual, $melhor['data'])
                || $this->datasProximas($proxima, $melhor['data'])
                || $this->bateAlgumCandidato($atual, $candidatos)
                || $this->bateAlgumCandidato($proxima, $candidatos)
            ) {
                continue;
            }

            $add(
                (int) $r['matricula_id'],
                $r['aluno_nome'],
                'vencimento_divergente',
                'alta',
                sprintf(
                    'Datas resetadas na migração (início %s > pago %s, +%s dias). Vencimento %s / próxima %s — esperado ~%s',
                    $r['data_inicio'],
                    $r['data_pagamento'],
                    $r['dias_apos_pago'],
                    $atual ?: '-',
                    $proxima ?: '-',
                    $melhor['data']
                ),
                [
                    'status' => $r['status_matricula'],
                    'data_inicio' => $r['data_inicio'],
                    'data_vencimento' => $atual,
                    'data_vencimento_esperada' => $melhor['data'],
                ]
            );
        }

        // 5) Assinatura após reset forte — só com sinal forte, ou matrícula ativa com gap ≥3 dias
        try {
            $stmt = $this->db->prepare("
                SELECT ass.id AS assinatura_id, ass.matricula_id, a.nome AS aluno_nome,
                       ass.data_inicio AS ass_inicio, ass.data_fim AS ass_fim,
                       sm.codigo AS status_matricula, m.data_inicio, m.data_vencimento,
                       ult.data_pagamento AS ultimo_pago,
                       DATEDIFF(m.data_inicio, ult.data_pagamento) AS dias_apos_pago
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
                  AND DATEDIFF(ass.data_inicio, ult.data_pagamento) >= 3
                  AND DATEDIFF(m.data_inicio, ult.data_pagamento) >= 3
                ORDER BY ass.id DESC
                LIMIT 200
            ");
            $stmt->execute([$tenantId, $tenantId]);
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
                $matId = (int) $r['matricula_id'];
                $temSinalForte = isset($porMatricula[$matId]) && $this->temSinalForte($porMatricula[$matId]['problemas']);
                $status = (string) ($r['status_matricula'] ?? '');
                if (!$temSinalForte && !in_array($status, ['ativa', 'vencida', 'pendente'], true)) {
                    continue;
                }

                $add(
                    $matId,
                    $r['aluno_nome'],
                    'assinatura_migracao',
                    'media',
                    sprintf(
                        'Assinatura #%s criada após reset (%s → %s); último pago %s (+%s dias)',
                        $r['assinatura_id'],
                        $r['ass_inicio'],
                        $r['ass_fim'] ?? '-',
                        $r['ultimo_pago'],
                        $r['dias_apos_pago']
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

    /**
     * @param list<array{tipo: string, severidade: string, descricao: string}> $problemas
     */
    private function temSinalForte(array $problemas): bool
    {
        foreach ($problemas as $p) {
            if (in_array($p['tipo'], [
                'parcela_fantasma_migracao',
                'pagamento_cancelado_credito',
                'credito_indevido_ativo',
                'vencimento_divergente',
            ], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function candidatosVencimento(
        string $dataPagamento,
        int $duracaoDias,
        int $matriculaId,
        int $pagamentoId,
        string $dataInicio = '',
        string $pagamentoVencimento = ''
    ): array {
        if ($dataPagamento === '') {
            return [];
        }

        $candidatos = [];
        $meses = $this->mesesDoCiclo($duracaoDias);

        $stmt = $this->db->prepare("
            SELECT data_vencimento FROM pagamentos_plano
            WHERE matricula_id = ? AND id > ? AND data_vencimento IS NOT NULL
              AND (observacoes IS NULL OR observacoes NOT LIKE '%Migração de plano%')
            ORDER BY data_vencimento ASC LIMIT 3
        ");
        $stmt->execute([$matriculaId, $pagamentoId]);
        foreach ($stmt->fetchAll(\PDO::FETCH_COLUMN) as $prox) {
            if ($prox) {
                $candidatos[] = (string) $prox;
            }
        }

        // Aniversário do pagamento
        $candidatos[] = $this->somarMeses($dataPagamento, $meses);

        // Aniversário do vencimento da parcela paga (pago antecipado)
        if ($pagamentoVencimento !== '') {
            $candidatos[] = $this->somarMeses($pagamentoVencimento, $meses);
            $candidatos[] = $pagamentoVencimento;
        }

        // Aniversário do início da matrícula (início depois do pagamento)
        if ($dataInicio !== '') {
            $candidatos[] = $this->somarMeses($dataInicio, $meses);
        }

        if ($duracaoDias > 0 && $duracaoDias <= 31) {
            $dt = new \DateTime($dataPagamento);
            $dt->modify("+{$duracaoDias} days");
            $candidatos[] = $dt->format('Y-m-d');
        }

        $dt30 = new \DateTime($dataPagamento);
        $dt30->modify('+30 days');
        $candidatos[] = $dt30->format('Y-m-d');

        return array_values(array_unique(array_filter($candidatos)));
    }

    private function mesesDoCiclo(int $duracaoDias): int
    {
        if ($duracaoDias <= 0) {
            return 1;
        }
        if ($duracaoDias <= 31) {
            return 1;
        }

        return max(1, (int) round($duracaoDias / 30));
    }

    private function somarMeses(string $data, int $meses): string
    {
        $dt = new \DateTime($data);
        $dia = (int) $dt->format('d');
        $dt->modify('first day of this month');
        $dt->modify("+{$meses} months");
        $ultimoDia = (int) $dt->format('t');
        $dt->setDate((int) $dt->format('Y'), (int) $dt->format('m'), min($dia, $ultimoDia));

        return $dt->format('Y-m-d');
    }

    /**
     * @param list<string> $candidatos
     * @return array{data: string, diff: int}|null
     */
    private function melhorCandidato(string $atual, array $candidatos): ?array
    {
        if ($candidatos === []) {
            return null;
        }
        if ($atual === '') {
            return ['data' => $candidatos[0], 'diff' => PHP_INT_MAX];
        }

        $melhor = null;
        foreach ($candidatos as $c) {
            $diff = abs($this->diffDias($atual, $c));
            if ($melhor === null || $diff < $melhor['diff']) {
                $melhor = ['data' => $c, 'diff' => $diff];
            }
        }

        return $melhor;
    }

    /**
     * @param list<string> $candidatos
     */
    private function bateAlgumCandidato(string $data, array $candidatos): bool
    {
        if ($data === '') {
            return false;
        }
        foreach ($candidatos as $c) {
            if ($this->datasProximas($data, $c)) {
                return true;
            }
        }

        return false;
    }

    private function datasProximas(string $a, string $b): bool
    {
        if ($a === '' || $b === '') {
            return false;
        }

        return abs($this->diffDias($a, $b)) <= self::TOLERANCIA_DIAS;
    }

    private function diffDias(string $a, string $b): int
    {
        $da = new \DateTime($a);
        $db = new \DateTime($b);

        return (int) $da->diff($db)->format('%r%a');
    }
}
