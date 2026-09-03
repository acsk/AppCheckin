<?php

namespace App\Services\Admin;

use Illuminate\Support\Facades\DB;

/**
 * Contas a receber (paridade com Slim ContasReceberController).
 */
class AdminContasReceberService
{
    /**
     * @param  array<string, mixed>  $query
     * @return array{status: int, body: array<string, mixed>}
     */
    public function index(int $tenantId, array $query): array
    {
        $sql = '
            SELECT
                cr.*,
                u.nome as aluno_nome,
                u.email as aluno_email,
                p.nome as plano_nome,
                p.duracao_dias,
                admin_criou.nome as criado_por_nome,
                admin_baixa.nome as baixa_por_nome
            FROM contas_receber cr
            INNER JOIN usuarios u ON cr.usuario_id = u.id
            INNER JOIN planos p ON cr.plano_id = p.id
            LEFT JOIN usuarios admin_criou ON cr.criado_por = admin_criou.id
            LEFT JOIN usuarios admin_baixa ON cr.baixa_por = admin_baixa.id
            WHERE cr.tenant_id = ?
        ';
        $bindings = [$tenantId];

        if (! empty($query['status'])) {
            $sql .= ' AND cr.status = ?';
            $bindings[] = $query['status'];
        }
        if (! empty($query['usuario_id'])) {
            $sql .= ' AND cr.usuario_id = ?';
            $bindings[] = $query['usuario_id'];
        }
        if (! empty($query['mes_referencia'])) {
            $sql .= ' AND cr.referencia_mes = ?';
            $bindings[] = $query['mes_referencia'];
        }

        $sql .= ' ORDER BY cr.data_vencimento DESC, cr.created_at DESC';

        $contas = $this->rows($sql, $bindings);

        return [
            'status' => 200,
            'body' => [
                'contas' => $contas,
                'total' => count($contas),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array{status: int, body: array<string, mixed>}
     */
    public function relatorio(int $tenantId, array $query): array
    {
        $sql = "
            SELECT
                cr.*,
                u.nome as aluno_nome,
                u.email as aluno_email,
                p.nome as plano_nome,
                p.valor as plano_valor,
                fp.nome as forma_pagamento_nome,
                fp.percentual_desconto,
                sc.nome as status_nome,
                sc.cor as status_cor
            FROM contas_receber cr
            INNER JOIN usuarios u ON cr.usuario_id = u.id
            INNER JOIN planos p ON cr.plano_id = p.id
            LEFT JOIN formas_pagamento fp ON cr.forma_pagamento_id = fp.id
            LEFT JOIN status_conta sc ON cr.status COLLATE utf8mb4_unicode_ci = sc.nome COLLATE utf8mb4_unicode_ci
            WHERE cr.tenant_id = ?
        ";
        $bindings = [$tenantId];

        $dataInicio = $query['data_inicio'] ?? null;
        $dataFim = $query['data_fim'] ?? null;

        if ($dataInicio && $dataFim) {
            $sql .= ' AND cr.data_vencimento BETWEEN ? AND ?';
            $bindings[] = $dataInicio;
            $bindings[] = $dataFim;
        } elseif ($dataInicio) {
            $sql .= ' AND cr.data_vencimento >= ?';
            $bindings[] = $dataInicio;
        } elseif ($dataFim) {
            $sql .= ' AND cr.data_vencimento <= ?';
            $bindings[] = $dataFim;
        }

        $status = $query['status'] ?? null;
        if ($status && $status !== 'todos') {
            $sql .= ' AND cr.status = ?';
            $bindings[] = $status;
        }

        $formasPagamento = $query['formas_pagamento'] ?? null;
        if ($formasPagamento && $formasPagamento !== 'todas') {
            $formasArray = explode(',', (string) $formasPagamento);
            $placeholders = implode(',', array_fill(0, count($formasArray), '?'));
            $sql .= " AND cr.forma_pagamento_id IN ($placeholders)";
            $bindings = array_merge($bindings, $formasArray);
        }

        $sql .= ' ORDER BY cr.data_vencimento DESC';

        $contas = $this->rows($sql, $bindings);

        $totalGeral = 0.0;
        $totalPago = 0.0;
        $totalPendente = 0.0;
        $totalCancelado = 0.0;
        $totalDescontos = 0.0;
        $totalLiquido = 0.0;
        $contasPorStatus = [
            'pago' => 0,
            'pendente' => 0,
            'cancelado' => 0,
            'vencido' => 0,
        ];
        $contasPorFormaPagamento = [];

        foreach ($contas as $conta) {
            $valor = (float) $conta['valor'];
            $valorDesconto = (float) ($conta['valor_desconto'] ?? 0);
            $valorLiquido = (float) ($conta['valor_liquido'] ?? $valor);

            $totalGeral += $valor;

            if ($conta['status'] === 'pago') {
                $totalPago += $valorLiquido;
                $totalDescontos += $valorDesconto;
                $totalLiquido += $valorLiquido;
                $contasPorStatus['pago']++;

                $formaNome = $conta['forma_pagamento_nome'] ?? 'Não informado';
                if (! isset($contasPorFormaPagamento[$formaNome])) {
                    $contasPorFormaPagamento[$formaNome] = [
                        'quantidade' => 0,
                        'total' => 0,
                        'total_liquido' => 0,
                        'total_desconto' => 0,
                    ];
                }
                $contasPorFormaPagamento[$formaNome]['quantidade']++;
                $contasPorFormaPagamento[$formaNome]['total'] += $valor;
                $contasPorFormaPagamento[$formaNome]['total_liquido'] += $valorLiquido;
                $contasPorFormaPagamento[$formaNome]['total_desconto'] += $valorDesconto;
            } elseif ($conta['status'] === 'pendente') {
                $totalPendente += $valor;
                $contasPorStatus['pendente']++;
                if ($conta['data_vencimento'] < date('Y-m-d')) {
                    $contasPorStatus['vencido']++;
                }
            } elseif ($conta['status'] === 'cancelado') {
                $totalCancelado += $valor;
                $contasPorStatus['cancelado']++;
            }
        }

        return [
            'status' => 200,
            'body' => [
                'contas' => $contas,
                'resumo' => [
                    'total_contas' => count($contas),
                    'total_geral' => number_format($totalGeral, 2, '.', ''),
                    'total_pago' => number_format($totalPago, 2, '.', ''),
                    'total_pendente' => number_format($totalPendente, 2, '.', ''),
                    'total_cancelado' => number_format($totalCancelado, 2, '.', ''),
                    'total_descontos' => number_format($totalDescontos, 2, '.', ''),
                    'total_liquido' => number_format($totalLiquido, 2, '.', ''),
                    'contas_por_status' => $contasPorStatus,
                    'contas_por_forma_pagamento' => $contasPorFormaPagamento,
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array{status: int, body: array<string, mixed>}
     */
    public function estatisticas(int $tenantId, array $query): array
    {
        $mesReferencia = $query['mes_referencia'] ?? date('Y-m');

        $porStatus = $this->rows(
            'SELECT status, COUNT(*) as quantidade, SUM(valor) as total
             FROM contas_receber
             WHERE tenant_id = ? AND referencia_mes = ?
             GROUP BY status',
            [$tenantId, $mesReferencia]
        );

        $vencidas = DB::selectOne(
            'SELECT COUNT(*) as quantidade, SUM(valor) as total
             FROM contas_receber
             WHERE tenant_id = ?
             AND status IN (\'pendente\', \'vencido\')
             AND data_vencimento < CURDATE()',
            [$tenantId]
        );

        $aVencer = DB::selectOne(
            'SELECT COUNT(*) as quantidade, SUM(valor) as total
             FROM contas_receber
             WHERE tenant_id = ?
             AND status = \'pendente\'
             AND data_vencimento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)',
            [$tenantId]
        );

        return [
            'status' => 200,
            'body' => [
                'por_status' => $porStatus,
                'vencidas' => $vencidas ? (array) $vencidas : null,
                'a_vencer_7_dias' => $aVencer ? (array) $aVencer : null,
                'mes_referencia' => $mesReferencia,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function darBaixa(int $tenantId, int $contaId, ?int $adminId, array $data): array
    {
        $conta = DB::selectOne(
            'SELECT cr.*, p.duracao_dias, p.valor as plano_valor
             FROM contas_receber cr
             INNER JOIN planos p ON cr.plano_id = p.id
             WHERE cr.id = ? AND cr.tenant_id = ?',
            [$contaId, $tenantId]
        );

        if (! $conta) {
            return ['status' => 404, 'body' => ['error' => 'Conta não encontrada']];
        }

        $conta = (array) $conta;

        if ($conta['status'] === 'pago') {
            return ['status' => 400, 'body' => ['error' => 'Conta já está paga']];
        }

        $dataPagamento = $data['data_pagamento'] ?? date('Y-m-d');
        $formaPagamentoId = $data['forma_pagamento_id'] ?? null;
        $observacoes = $data['observacoes'] ?? null;

        $valorLiquido = (float) $conta['valor'];
        $valorDesconto = 0.0;

        if ($formaPagamentoId) {
            $forma = DB::selectOne(
                'SELECT percentual_desconto FROM formas_pagamento WHERE id = ? AND ativo = 1',
                [$formaPagamentoId]
            );
            if ($forma && (float) $forma->percentual_desconto > 0) {
                $valorDesconto = ((float) $conta['valor'] * (float) $forma->percentual_desconto) / 100;
                $valorLiquido = (float) $conta['valor'] - $valorDesconto;
            }
        }

        DB::update(
            'UPDATE contas_receber
             SET status = \'pago\',
                 data_pagamento = ?,
                 forma_pagamento_id = ?,
                 valor_liquido = ?,
                 valor_desconto = ?,
                 observacoes = ?,
                 baixa_por = ?,
                 updated_at = NOW()
             WHERE id = ?',
            [$dataPagamento, $formaPagamentoId, $valorLiquido, $valorDesconto, $observacoes, $adminId, $contaId]
        );

        $proximaContaId = null;
        $proximoVencimento = null;

        if ($conta['recorrente'] && $conta['intervalo_dias']) {
            $proximoVencimento = date('Y-m-d', strtotime($conta['data_vencimento']." +{$conta['intervalo_dias']} days"));
            $proximaReferencia = date('Y-m', strtotime($proximoVencimento));

            DB::insert(
                'INSERT INTO contas_receber
                    (tenant_id, usuario_id, plano_id, historico_plano_id, valor, data_vencimento,
                     status, referencia_mes, recorrente, intervalo_dias, conta_origem_id, criado_por)
                 VALUES (?, ?, ?, ?, ?, ?, \'pendente\', ?, true, ?, ?, ?)',
                [
                    $tenantId,
                    $conta['usuario_id'],
                    $conta['plano_id'],
                    $conta['historico_plano_id'],
                    $conta['plano_valor'],
                    $proximoVencimento,
                    $proximaReferencia,
                    $conta['intervalo_dias'],
                    $contaId,
                    $adminId,
                ]
            );

            $proximaContaId = (int) DB::getPdo()->lastInsertId();

            DB::update(
                'UPDATE contas_receber SET proxima_conta_id = ? WHERE id = ?',
                [$proximaContaId, $contaId]
            );
        }

        $contaAtualizada = DB::selectOne(
            'SELECT cr.*, p.duracao_dias, p.valor as plano_valor
             FROM contas_receber cr
             INNER JOIN planos p ON cr.plano_id = p.id
             WHERE cr.id = ? AND cr.tenant_id = ?',
            [$contaId, $tenantId]
        );

        return [
            'status' => 200,
            'body' => [
                'message' => 'Baixa realizada com sucesso',
                'conta' => $contaAtualizada ? (array) $contaAtualizada : null,
                'proxima_conta_id' => $proximaContaId,
                'proxima_vencimento' => $proximaContaId ? $proximoVencimento : null,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function cancelar(int $tenantId, int $contaId, ?int $adminId, array $data): array
    {
        $conta = DB::selectOne(
            'SELECT * FROM contas_receber WHERE id = ? AND tenant_id = ?',
            [$contaId, $tenantId]
        );

        if (! $conta) {
            return ['status' => 404, 'body' => ['error' => 'Conta não encontrada']];
        }

        if ($conta->status === 'pago') {
            return ['status' => 400, 'body' => ['error' => 'Não é possível cancelar conta já paga']];
        }

        $observacoes = $data['observacoes'] ?? 'Cancelado pelo admin';

        DB::update(
            'UPDATE contas_receber
             SET status = \'cancelado\', observacoes = ?, baixa_por = ?, updated_at = NOW()
             WHERE id = ?',
            [$observacoes, $adminId, $contaId]
        );

        return [
            'status' => 200,
            'body' => ['message' => 'Conta cancelada com sucesso'],
        ];
    }

    /**
     * @param  list<mixed>  $bindings
     * @return list<array<string, mixed>>
     */
    private function rows(string $sql, array $bindings): array
    {
        return array_map(
            static fn ($row) => (array) $row,
            DB::select($sql, $bindings),
        );
    }
}
