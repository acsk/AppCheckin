<?php

namespace App\Models;

use PDO;

class PagamentoPlano
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
        $sql = "INSERT INTO pagamentos_plano 
                (tenant_id, aluno_id, matricula_id, plano_id, valor, data_vencimento, 
                 data_pagamento, status_pagamento_id, forma_pagamento_id, comprovante, observacoes, criado_por)
                VALUES 
                (:tenant_id, :aluno_id, :matricula_id, :plano_id, :valor, :data_vencimento,
                 :data_pagamento, :status_pagamento_id, :forma_pagamento_id, :comprovante, :observacoes, :criado_por)";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'tenant_id' => $dados['tenant_id'],
            'aluno_id' => $dados['aluno_id'],
            'matricula_id' => $dados['matricula_id'],
            'plano_id' => $dados['plano_id'],
            'valor' => $dados['valor'],
            'data_vencimento' => $dados['data_vencimento'],
            'data_pagamento' => $dados['data_pagamento'] ?? null,
            'status_pagamento_id' => $dados['status_pagamento_id'] ?? 1, // Default: Aguardando
            'forma_pagamento_id' => $dados['forma_pagamento_id'] ?? null,
            'comprovante' => $dados['comprovante'] ?? null,
            'observacoes' => $dados['observacoes'] ?? null,
            'criado_por' => $dados['criado_por'] ?? null
        ]);
        
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Listar pagamentos de uma matrícula
     */
    public function listarPorMatricula(int $tenantId, int $matriculaId): array
    {
        $sql = "SELECT 
                    p.*,
                    sp.nome as status_pagamento_nome,
                    fp.nome as forma_pagamento_nome,
                    a.nome as aluno_nome,
                    pl.nome as plano_nome,
                    criador.nome as criado_por_nome,
                    baixador.nome as baixado_por_nome,
                    tb.nome as tipo_baixa_nome
                FROM pagamentos_plano p
                INNER JOIN status_pagamento sp ON p.status_pagamento_id = sp.id
                LEFT JOIN formas_pagamento fp ON p.forma_pagamento_id = fp.id
                INNER JOIN alunos a ON p.aluno_id = a.id
                INNER JOIN planos pl ON p.plano_id = pl.id
                LEFT JOIN usuarios criador ON p.criado_por = criador.id
                LEFT JOIN usuarios baixador ON p.baixado_por = baixador.id
                LEFT JOIN tipos_baixa tb ON p.tipo_baixa_id = tb.id
                WHERE p.tenant_id = :tenant_id AND p.matricula_id = :matricula_id
                ORDER BY p.data_vencimento ASC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'tenant_id' => $tenantId,
            'matricula_id' => $matriculaId
        ]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Listar pagamentos de um usuário (via aluno_id)
     */
    public function listarPorUsuario(int $tenantId, int $usuarioId, ?array $filtros = []): array
    {
        $sql = "SELECT 
                    p.*,
                    sp.nome as status_nome,
                    fp.nome as forma_pagamento_nome,
                    pl.nome as plano_nome,
                    m.data_inicio as matricula_data_inicio
                FROM pagamentos_plano p
                INNER JOIN status_pagamento sp ON p.status_pagamento_id = sp.id
                LEFT JOIN formas_pagamento fp ON p.forma_pagamento_id = fp.id
                INNER JOIN planos pl ON p.plano_id = pl.id
                INNER JOIN matriculas m ON p.matricula_id = m.id
                INNER JOIN alunos a ON p.aluno_id = a.id
                WHERE p.tenant_id = :tenant_id AND a.usuario_id = :usuario_id";
        
        $params = [
            'tenant_id' => $tenantId,
            'usuario_id' => $usuarioId
        ];
        
        if (!empty($filtros['status_pagamento_id'])) {
            $sql .= " AND p.status_pagamento_id = :status_pagamento_id";
            $params['status_pagamento_id'] = $filtros['status_pagamento_id'];
        }
        
        $sql .= " ORDER BY p.data_vencimento DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Listar todos os pagamentos do tenant (com filtros)
     */
    public function listarTodos(int $tenantId, ?array $filtros = []): array
    {
        $sql = "SELECT 
                    p.*,
                    sp.nome as status_nome,
                    fp.nome as forma_pagamento_nome,
                    a.nome as aluno_nome,
                    pl.nome as plano_nome
                FROM pagamentos_plano p
                INNER JOIN status_pagamento sp ON p.status_pagamento_id = sp.id
                LEFT JOIN formas_pagamento fp ON p.forma_pagamento_id = fp.id
                INNER JOIN alunos a ON p.aluno_id = a.id
                INNER JOIN planos pl ON p.plano_id = pl.id
                WHERE p.tenant_id = :tenant_id";
        
        $params = ['tenant_id' => $tenantId];
        
        if (!empty($filtros['status_pagamento_id'])) {
            $sql .= " AND p.status_pagamento_id = :status_pagamento_id";
            $params['status_pagamento_id'] = $filtros['status_pagamento_id'];
        }
        
        if (!empty($filtros['aluno_id'])) {
            $sql .= " AND p.aluno_id = :aluno_id";
            $params['aluno_id'] = $filtros['aluno_id'];
        }
        
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
     * Buscar pagamento por ID
     */
    public function buscarPorId(int $tenantId, int $id): ?array
    {
        $sql = "SELECT 
                    p.*,
                    sp.nome as status_nome,
                    fp.nome as forma_pagamento_nome,
                    a.nome as aluno_nome,
                    pl.nome as plano_nome,
                    m.data_inicio as matricula_data_inicio,
                    m.data_vencimento as matricula_data_vencimento
                FROM pagamentos_plano p
                INNER JOIN status_pagamento sp ON p.status_pagamento_id = sp.id
                LEFT JOIN formas_pagamento fp ON p.forma_pagamento_id = fp.id
                INNER JOIN alunos a ON p.aluno_id = a.id
                INNER JOIN planos pl ON p.plano_id = pl.id
                INNER JOIN matriculas m ON p.matricula_id = m.id
                WHERE p.tenant_id = :tenant_id AND p.id = :id";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'tenant_id' => $tenantId,
            'id' => $id
        ]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ?: null;
    }

    /**
     * Confirmar pagamento (dar baixa)
     */
    public function confirmarPagamento(
        int $tenantId,
        int $id,
        int $adminId,
        ?string $dataPagamento = null,
        ?int $formaPagamentoId = null,
        ?string $comprovante = null,
        ?string $observacoes = null,
        ?int $tipoBaixaId = 1
    ): bool {
        $sql = "UPDATE pagamentos_plano 
                SET status_pagamento_id = 2,
                    data_pagamento = :data_pagamento,
                    forma_pagamento_id = COALESCE(:forma_pagamento_id, forma_pagamento_id),
                    comprovante = COALESCE(:comprovante, comprovante),
                    observacoes = COALESCE(:observacoes, observacoes),
                    baixado_por = :baixado_por,
                    tipo_baixa_id = :tipo_baixa_id,
                    updated_at = NOW()
                WHERE tenant_id = :tenant_id AND id = :id";
        
        $stmt = $this->pdo->prepare($sql);
        $result = $stmt->execute([
            'tenant_id' => $tenantId,
            'id' => $id,
            'data_pagamento' => $dataPagamento ?? date('Y-m-d'),
            'forma_pagamento_id' => $formaPagamentoId,
            'comprovante' => $comprovante,
            'observacoes' => $observacoes,
            'baixado_por' => $adminId,
            'tipo_baixa_id' => $tipoBaixaId
        ]);
        
        return $result;
    }

    /**
     * Cancelar pagamento
     */
    public function cancelar(int $tenantId, int $id, ?string $observacoes = null): bool
    {
        // Buscar dados do pagamento antes de cancelar
        $sqlBusca = "SELECT matricula_id FROM pagamentos_plano WHERE tenant_id = :tenant_id AND id = :id";
        $stmtBusca = $this->pdo->prepare($sqlBusca);
        $stmtBusca->execute(['tenant_id' => $tenantId, 'id' => $id]);
        $pagamento = $stmtBusca->fetch(\PDO::FETCH_ASSOC);
        
        if (!$pagamento) {
            return false;
        }

        $sql = "UPDATE pagamentos_plano 
                SET status_pagamento_id = 4,
                    observacoes = COALESCE(:observacoes, observacoes),
                    updated_at = NOW()
                WHERE tenant_id = :tenant_id AND id = :id";
        
        $stmt = $this->pdo->prepare($sql);
        $result = $stmt->execute([
            'tenant_id' => $tenantId,
            'id' => $id,
            'observacoes' => $observacoes
        ]);

        // Verificar se há pagamentos pendentes para atualizar status da matrícula
        if ($result && $pagamento['matricula_id']) {
            $this->atualizarStatusMatricula($tenantId, $pagamento['matricula_id']);
        }

        return $result;
    }

    /**
     * Atualizar status da matrícula baseado nos pagamentos
     */
    private function atualizarStatusMatricula(int $tenantId, int $matriculaId): void
    {
        // Verificar se ainda há pagamentos pendentes ou atrasados
        $sqlVerifica = "
            SELECT 
                MAX(CASE WHEN status_pagamento_id IN (1, 3) AND data_vencimento < CURDATE() THEN DATEDIFF(CURDATE(), data_vencimento) ELSE 0 END) as dias_atraso,
                SUM(CASE WHEN status_pagamento_id IN (1, 3) THEN 1 ELSE 0 END) as pendentes
            FROM pagamentos_plano
            WHERE tenant_id = :tenant_id AND matricula_id = :matricula_id
        ";
        
        $stmtVerifica = $this->pdo->prepare($sqlVerifica);
        $stmtVerifica->execute(['tenant_id' => $tenantId, 'matricula_id' => $matriculaId]);
        $resultado = $stmtVerifica->fetch(\PDO::FETCH_ASSOC);

        $novoStatus = 'ativa';
        $diasAtraso = (int) $resultado['dias_atraso'];
        $pendentes = (int) $resultado['pendentes'];

        if ($pendentes > 0) {
            if ($diasAtraso >= 5) {
                $novoStatus = 'cancelada';
            } elseif ($diasAtraso >= 1) {
                $novoStatus = 'vencida';
            }
        }

        // Atualizar matrícula
        $sqlUpdate = "
            UPDATE matriculas 
            SET status = :status,
                status_id = (SELECT id FROM status_matricula WHERE codigo = :status_codigo LIMIT 1),
                updated_at = NOW()
            WHERE tenant_id = :tenant_id AND id = :matricula_id
        ";
        
        $stmtUpdate = $this->pdo->prepare($sqlUpdate);
        $stmtUpdate->execute([
            'status' => $novoStatus,
            'status_codigo' => $novoStatus,
            'tenant_id' => $tenantId,
            'matricula_id' => $matriculaId
        ]);
    }

    /**
     * Marcar pagamentos atrasados
     */
    public function marcarAtrasados(int $tenantId): int
    {
        $sql = "UPDATE pagamentos_plano 
                SET status_pagamento_id = 3,
                    updated_at = NOW()
                WHERE tenant_id = :tenant_id
                AND status_pagamento_id = 1 
                AND data_vencimento < CURDATE()
                AND data_pagamento IS NULL";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['tenant_id' => $tenantId]);
        
        return $stmt->rowCount();
    }

    /**
     * Verificar se matrícula tem pagamentos pendentes ou atrasados
     */
    public function temPagamentosPendentes(int $tenantId, int $matriculaId): bool
    {
        $sql = "SELECT COUNT(*) as total
                FROM pagamentos_plano
                WHERE tenant_id = :tenant_id
                AND matricula_id = :matricula_id
                AND status_pagamento_id IN (1, 3)";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'tenant_id' => $tenantId,
            'matricula_id' => $matriculaId
        ]);
        
        return (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'] > 0;
    }

    /**
     * Resumo financeiro dos pagamentos
     */
    public function resumo(int $tenantId, ?array $filtros = []): array
    {
        $sql = "SELECT 
                    COUNT(*) as total_pagamentos,
                    SUM(CASE WHEN status_pagamento_id = 1 THEN 1 ELSE 0 END) as aguardando,
                    SUM(CASE WHEN status_pagamento_id = 2 THEN 1 ELSE 0 END) as pagos,
                    SUM(CASE WHEN status_pagamento_id = 3 THEN 1 ELSE 0 END) as atrasados,
                    SUM(CASE WHEN status_pagamento_id = 4 THEN 1 ELSE 0 END) as cancelados,
                    SUM(CASE WHEN status_pagamento_id = 2 THEN valor ELSE 0 END) as valor_recebido,
                    SUM(CASE WHEN status_pagamento_id IN (1, 3) THEN valor ELSE 0 END) as valor_pendente,
                    SUM(valor) as valor_total
                FROM pagamentos_plano
                WHERE tenant_id = :tenant_id";
        
        $params = ['tenant_id' => $tenantId];
        
        if (!empty($filtros['data_inicio'])) {
            $sql .= " AND data_vencimento >= :data_inicio";
            $params['data_inicio'] = $filtros['data_inicio'];
        }
        
        if (!empty($filtros['data_fim'])) {
            $sql .= " AND data_vencimento <= :data_fim";
            $params['data_fim'] = $filtros['data_fim'];
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Buscar último pagamento de uma matrícula
     */
    public function buscarUltimoPagamento(int $tenantId, int $matriculaId): ?array
    {
        $sql = "SELECT * FROM pagamentos_plano
                WHERE tenant_id = :tenant_id AND matricula_id = :matricula_id
                ORDER BY data_vencimento DESC
                LIMIT 1";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'tenant_id' => $tenantId,
            'matricula_id' => $matriculaId
        ]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ?: null;
    }

    /**
     * Verificar se já existe pagamento para uma data específica
     */
    public function existePagamentoParaData(int $tenantId, int $matriculaId, string $dataVencimento): bool
    {
        $sql = "SELECT COUNT(*) as total
                FROM pagamentos_plano
                WHERE tenant_id = :tenant_id
                AND matricula_id = :matricula_id
                AND data_vencimento = :data_vencimento";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'tenant_id' => $tenantId,
            'matricula_id' => $matriculaId,
            'data_vencimento' => $dataVencimento
        ]);
        
        return (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'] > 0;
    }
}
