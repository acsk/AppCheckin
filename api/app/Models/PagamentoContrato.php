<?php

namespace App\Models;

use PDO;

class PagamentoContrato
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Criar novo pagamento
     */
    public function criar(array $dados): int
    {
        $sql = "INSERT INTO pagamentos_contrato 
                (tenant_plano_id, valor, data_vencimento, data_pagamento, status_pagamento_id, forma_pagamento_id, comprovante, observacoes)
                VALUES 
                (:tenant_plano_id, :valor, :data_vencimento, :data_pagamento, :status_pagamento_id, :forma_pagamento_id, :comprovante, :observacoes)";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'tenant_plano_id' => $dados['tenant_plano_id'] ?? $dados['contrato_id'],
            'valor' => $dados['valor'],
            'data_vencimento' => $dados['data_vencimento'],
            'data_pagamento' => $dados['data_pagamento'] ?? null,
            'status_pagamento_id' => $dados['status_pagamento_id'] ?? 1, // Default: Aguardando
            'forma_pagamento_id' => $dados['forma_pagamento_id'],
            'comprovante' => $dados['comprovante'] ?? null,
            'observacoes' => $dados['observacoes'] ?? null
        ]);
        
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Listar pagamentos de um contrato
     */
    public function listarPorContrato(int $contratoId): array
    {
        $sql = "SELECT p.*, sp.nome as status_nome, fp.nome as forma_pagamento_nome
                FROM pagamentos_contrato p
                INNER JOIN status_pagamento sp ON p.status_pagamento_id = sp.id
                LEFT JOIN formas_pagamento fp ON p.forma_pagamento_id = fp.id
                WHERE p.tenant_plano_id = :tenant_plano_id
                ORDER BY p.data_vencimento DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['tenant_plano_id' => $contratoId]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Buscar pagamento por ID
     */
    public function buscarPorId(int $id): ?array
    {
        $sql = "SELECT p.*, sp.nome as status_nome, fp.nome as forma_pagamento_nome
                FROM pagamentos_contrato p
                INNER JOIN status_pagamento sp ON p.status_pagamento_id = sp.id
                LEFT JOIN formas_pagamento fp ON p.forma_pagamento_id = fp.id
                WHERE p.id = :id";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ?: null;
    }

    /**
     * Confirmar pagamento
     */
    public function confirmarPagamento(int $id, ?string $dataPagamento = null, ?int $formaPagamentoId = null, ?string $comprovante = null, ?string $observacoes = null): bool
    {
        $sql = "UPDATE pagamentos_contrato 
                SET status_pagamento_id = 2,
                    data_pagamento = :data_pagamento,
                    forma_pagamento_id = COALESCE(:forma_pagamento_id, forma_pagamento_id),
                    comprovante = COALESCE(:comprovante, comprovante),
                    observacoes = COALESCE(:observacoes, observacoes)
                WHERE id = :id";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            'id' => $id,
            'data_pagamento' => $dataPagamento ?? date('Y-m-d'),
            'forma_pagamento_id' => $formaPagamentoId,
            'comprovante' => $comprovante,
            'observacoes' => $observacoes
        ]);
    }

    /**
     * Cancelar pagamento
     */
    public function cancelar(int $id, ?string $observacoes = null): bool
    {
        $sql = "UPDATE pagamentos_contrato 
                SET status_pagamento_id = 4,
                    observacoes = COALESCE(:observacoes, observacoes)
                WHERE id = :id";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            'id' => $id,
            'observacoes' => $observacoes
        ]);
    }

    /**
     * Marcar pagamentos atrasados
     */
    public function marcarAtrasados(): int
    {
        $sql = "UPDATE pagamentos_contrato 
                SET status_pagamento_id = 3
                WHERE status_pagamento_id = 1 
                AND data_vencimento < CURDATE()
                AND data_pagamento IS NULL";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        
        return $stmt->rowCount();
    }

    /**
     * Verificar se contrato tem pagamentos pendentes ou atrasados
     */
    public function temPagamentosPendentes(int $contratoId): bool
    {
        $sql = "SELECT COUNT(*) as total
                FROM pagamentos_contrato
                WHERE tenant_plano_id = :tenant_plano_id
                AND status_pagamento_id IN (1, 3)";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['tenant_plano_id' => $contratoId]);
        
        return (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'] > 0;
    }

    /**
     * Buscar último pagamento de um contrato
     */
    public function buscarUltimoPagamento(int $contratoId): ?array
    {
        $sql = "SELECT p.*, sp.nome as status_nome
                FROM pagamentos_contrato p
                INNER JOIN status_pagamento sp ON p.status_pagamento_id = sp.id
                WHERE p.tenant_plano_id = :tenant_plano_id
                ORDER BY p.data_vencimento DESC
                LIMIT 1";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['tenant_plano_id' => $contratoId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ?: null;
    }

    /**
     * Listar todos os pagamentos com filtros
     */
    public function listarTodos(array $filtros = []): array
    {
        $sql = "SELECT p.*, 
                       sp.nome as status_nome,
                       t.nome as academia_nome,
                       ps.nome as plano_nome,
                       c.data_inicio as contrato_inicio,
                       c.data_vencimento as contrato_vencimento
                FROM pagamentos_contrato p
                INNER JOIN status_pagamento sp ON p.status_pagamento_id = sp.id
                INNER JOIN tenant_planos_sistema c ON p.tenant_plano_id = c.id
                INNER JOIN tenants t ON c.tenant_id = t.id
                INNER JOIN planos_sistema ps ON c.plano_sistema_id = ps.id
                WHERE 1=1";
        
        $params = [];
        
        // Filtro por status de pagamento
        if (!empty($filtros['status_pagamento_id'])) {
            $sql .= " AND p.status_pagamento_id = :status_pagamento_id";
            $params['status_pagamento_id'] = $filtros['status_pagamento_id'];
        }
        
        // Filtro por tenant/academia
        if (!empty($filtros['tenant_id'])) {
            $sql .= " AND c.tenant_id = :tenant_id";
            $params['tenant_id'] = $filtros['tenant_id'];
        }
        
        // Filtro por período de vencimento
        if (!empty($filtros['data_inicio'])) {
            $sql .= " AND p.data_vencimento >= :data_inicio";
            $params['data_inicio'] = $filtros['data_inicio'];
        }
        
        if (!empty($filtros['data_fim'])) {
            $sql .= " AND p.data_vencimento <= :data_fim";
            $params['data_fim'] = $filtros['data_fim'];
        }
        
        $sql .= " ORDER BY p.data_vencimento DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Resumo de pagamentos
     */
    public function resumo(array $filtros = []): array
    {
        $sql = "SELECT 
                    sp.nome as status,
                    COUNT(*) as quantidade,
                    SUM(p.valor) as total,
                    SUM(CASE WHEN p.data_vencimento < CURDATE() AND p.status_pagamento_id != 2 THEN p.valor ELSE 0 END) as total_atrasado
                FROM pagamentos_contrato p
                INNER JOIN status_pagamento sp ON p.status_pagamento_id = sp.id
                INNER JOIN tenant_planos_sistema c ON p.tenant_plano_id = c.id
                WHERE 1=1";
        
        $params = [];
        
        if (!empty($filtros['tenant_id'])) {
            $sql .= " AND c.tenant_id = :tenant_id";
            $params['tenant_id'] = $filtros['tenant_id'];
        }
        
        if (!empty($filtros['data_inicio'])) {
            $sql .= " AND p.data_vencimento >= :data_inicio";
            $params['data_inicio'] = $filtros['data_inicio'];
        }
        
        if (!empty($filtros['data_fim'])) {
            $sql .= " AND p.data_vencimento <= :data_fim";
            $params['data_fim'] = $filtros['data_fim'];
        }
        
        $sql .= " GROUP BY sp.id, sp.nome";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Listar pagamentos com valor 0 que ainda não foram pagos
     * Estes devem ser baixados automaticamente via job mensal
     */
    public function listarPagamentosComValorZero(int $limite = 100): array
    {
        $sql = "SELECT pc.id, pc.tenant_plano_id as contrato_id, pc.valor, pc.data_vencimento,
                       pc.status_pagamento_id, sp.nome as status_nome,
                       tps.tenant_id, t.nome as tenant_nome
                FROM pagamentos_contrato pc
                INNER JOIN tenant_planos_sistema tps ON pc.tenant_plano_id = tps.id
                INNER JOIN tenants t ON tps.tenant_id = t.id
                LEFT JOIN status_pagamento sp ON pc.status_pagamento_id = sp.id
                WHERE pc.valor = 0 
                AND pc.status_pagamento_id = 1  -- Aguardando
                ORDER BY pc.created_at ASC
                LIMIT :limite";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Baixar pagamento com valor 0 (marcar como pago)
     * Gera automaticamente o próximo pagamento baseado no plano
     */
    public function baixarPagamentoValorZero(int $pagamentoId): bool
    {
        // Buscar dados do pagamento e contrato
        $sqlBuscar = "SELECT pc.id, pc.tenant_plano_id, pc.valor, pc.data_vencimento,
                             tp.id as contrato_id,
                             ps.valor as valor_plano, ps.duracao_dias
                      FROM pagamentos_contrato pc
                      INNER JOIN tenant_planos_sistema tp ON pc.tenant_plano_id = tp.id
                      INNER JOIN planos_sistema ps ON tp.plano_sistema_id = ps.id
                      WHERE pc.id = :id";
        
        $stmt = $this->pdo->prepare($sqlBuscar);
        $stmt->execute(['id' => $pagamentoId]);
        $pagamento = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$pagamento) {
            return false;
        }

        // Baixar o pagamento atual
        $sql = "UPDATE pagamentos_contrato 
                SET status_pagamento_id = 2,
                    data_pagamento = CURDATE(),
                    observacoes = CONCAT(COALESCE(observacoes, ''), '\n', 'Baixado automaticamente via job - valor R$ 0,00'),
                    updated_at = NOW()
                WHERE id = :id";
        
        $stmt = $this->pdo->prepare($sql);
        $resultado = $stmt->execute(['id' => $pagamentoId]);

        if ($resultado) {
            // Gerar próximo pagamento
            $proximaDataVencimento = date('Y-m-d', strtotime("+{$pagamento['duracao_dias']} days", strtotime($pagamento['data_vencimento'])));
            $valorProximo = $pagamento['valor_plano'] ?? 0;

            $sqlNovoPageamento = "INSERT INTO pagamentos_contrato 
                                  (tenant_plano_id, valor, data_vencimento, status_pagamento_id, observacoes)
                                  VALUES 
                                  (:tenant_plano_id, :valor, :data_vencimento, 1, :observacoes)";
            
            try {
                $stmt = $this->pdo->prepare($sqlNovoPageamento);
                $stmt->execute([
                    'tenant_plano_id' => $pagamento['tenant_plano_id'],
                    'valor' => $valorProximo,
                    'data_vencimento' => $proximaDataVencimento,
                    'observacoes' => 'Gerado automaticamente após pagamento com valor R$ 0,00'
                ]);
            } catch (\Exception $e) {
                // Log do erro mas não falha a baixa
                error_log("Erro ao gerar próximo pagamento: " . $e->getMessage());
            }
        }

        return $resultado;
    }

    /**
     * Baixar múltiplos pagamentos em transação
     */
    public function baixarPagamentosBatch(array $pagamentoIds): array
    {
        if (empty($pagamentoIds)) {
            return ['sucesso' => 0, 'erro' => 0, 'total' => 0];
        }

        $sucesso = 0;
        $erro = 0;

        try {
            $this->pdo->beginTransaction();

            foreach ($pagamentoIds as $pagamentoId) {
                try {
                    if ($this->baixarPagamentoValorZero($pagamentoId)) {
                        $sucesso++;
                    } else {
                        $erro++;
                    }
                } catch (\Exception $e) {
                    $erro++;
                }
            }

            $this->pdo->commit();
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return [
            'sucesso' => $sucesso,
            'erro' => $erro,
            'total' => count($pagamentoIds)
        ];
    }
}
