<?php

namespace App\Models;

use PDO;

class TenantPlano
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Cria um novo contrato de plano para o tenant
     * REGRA: Só pode haver um contrato ativo por tenant
     */
    public function criar($dados)
    {
        // Verificar se já existe um contrato ativo para este tenant
        if ($this->buscarContratoAtivo($dados['tenant_id'])) {
            throw new \Exception('Esta academia já possui um contrato ativo. Desative ou cancele o contrato atual antes de criar um novo.');
        }

        $sql = "INSERT INTO tenant_planos_sistema 
                (tenant_id, plano_id, plano_sistema_id, status_id, data_inicio, observacoes) 
                VALUES 
                (:tenant_id, :plano_id, :plano_sistema_id, :status_id, :data_inicio, :observacoes)";
        
        $stmt = $this->pdo->prepare($sql);
        $planoSistemaId = $dados['plano_sistema_id'] ?? $dados['plano_id'] ?? null;
        $stmt->execute([
            'tenant_id' => $dados['tenant_id'],
            'plano_id' => $planoSistemaId, // Usar o mesmo valor para compatibilidade
            'plano_sistema_id' => $planoSistemaId,
            'status_id' => $dados['status_id'] ?? 1, // Default: 1 = Ativo
            'data_inicio' => $dados['data_inicio'],
            'observacoes' => $dados['observacoes'] ?? null
        ]);
        
        return $this->pdo->lastInsertId();
    }

    /**
     * Busca o contrato ativo de um tenant
     */
    public function buscarContratoAtivo($tenant_id)
    {
        $sql = "SELECT tp.*, 
                       ps.nome as plano_nome, ps.valor, ps.max_alunos, ps.max_admins, ps.features,
                       sc.nome as status_nome, sc.id as status_id
                FROM tenant_planos_sistema tp
                INNER JOIN planos_sistema ps ON tp.plano_sistema_id = ps.id
                INNER JOIN status_contrato sc ON tp.status_id = sc.id
                WHERE tp.tenant_id = :tenant_id 
                AND tp.status_id = 1
                LIMIT 1";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['tenant_id' => $tenant_id]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Busca histórico de contratos de um tenant
     */
    public function buscarHistorico($tenant_id)
    {
        $sql = "SELECT tp.*, p.nome as plano_nome, p.valor
                FROM tenant_planos_sistema tp
                INNER JOIN planos p ON tp.plano_id = p.id
                WHERE tp.tenant_id = :tenant_id
                ORDER BY tp.created_at DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['tenant_id' => $tenant_id]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Desativa o contrato ativo atual do tenant (muda para Cancelado)
     */
    public function desativarContratoAtivo($tenant_id)
    {
        $sql = "UPDATE tenant_planos_sistema 
                SET status_id = 3 
                WHERE tenant_id = :tenant_id 
                AND status_id = 1";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute(['tenant_id' => $tenant_id]);
    }

    /**
     * Troca o plano do tenant
     * Desativa o contrato atual e cria um novo
     */
    public function trocarPlano($tenant_id, $novo_plano_sistema_id, $forma_pagamento, $observacoes = null)
    {
        try {
            $this->pdo->beginTransaction();

            // Desativa o contrato atual
            $this->desativarContratoAtivo($tenant_id);

            // Calcula as datas (mensal)
            $data_inicio = date('Y-m-d');
            $data_vencimento = date('Y-m-d', strtotime('+1 month'));

            // Cria novo contrato
            $novo_contrato_id = $this->criar([
                'tenant_id' => $tenant_id,
                'plano_sistema_id' => $novo_plano_sistema_id,
                'data_inicio' => $data_inicio,
                'data_vencimento' => $data_vencimento,
                'forma_pagamento' => $forma_pagamento,
                'observacoes' => $observacoes
            ]);

            $this->pdo->commit();
            
            return [
                'success' => true,
                'contrato_id' => $novo_contrato_id,
                'data_inicio' => $data_inicio,
                'data_vencimento' => $data_vencimento
            ];
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Cancela um contrato
     */
    public function cancelarContrato($contrato_id)
    {
        $sql = "UPDATE tenant_planos_sistema 
                SET status_id = 3 
                WHERE id = :id";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute(['id' => $contrato_id]);
    }

    /**
     * Verifica se o tenant possui contrato ativo
     */
    public function possuiContratoAtivo($tenant_id)
    {
        $sql = "SELECT COUNT(*) as total 
                FROM tenant_planos_sistema 
                WHERE tenant_id = :tenant_id 
                AND status_id = 1";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['tenant_id' => $tenant_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['total'] > 0;
    }

    /**
     * Busca contratos que estão próximos do vencimento
     * @param int $dias Dias antes do vencimento para buscar
     */
    public function buscarContratosProximosVencimento($dias = 7)
    {
        $data_limite = date('Y-m-d', strtotime("+$dias days"));
        
        $sql = "SELECT tp.*, 
                       t.nome as tenant_nome, t.email, 
                       ps.nome as plano_nome, ps.valor,
                       sc.nome as status_nome,
                       MIN(pc.data_vencimento) as proximo_vencimento
                FROM tenant_planos_sistema tp
                INNER JOIN tenants t ON tp.tenant_id = t.id
                INNER JOIN planos_sistema ps ON tp.plano_sistema_id = ps.id
                INNER JOIN status_contrato sc ON tp.status_id = sc.id
                LEFT JOIN pagamentos_contrato pc ON tp.id = pc.contrato_id AND pc.status_pagamento_id = 1
                WHERE tp.status_id = 1
                AND pc.data_vencimento <= :data_limite
                AND pc.data_vencimento >= CURDATE()
                GROUP BY tp.id
                ORDER BY proximo_vencimento ASC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['data_limite' => $data_limite]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Busca contratos vencidos
     */
    public function buscarContratosVencidos()
    {
        $sql = "SELECT tp.*, 
                       t.nome as tenant_nome, t.email, 
                       ps.nome as plano_nome,
                       sc.nome as status_nome,
                       MIN(pc.data_vencimento) as data_vencimento_mais_antiga
                FROM tenant_planos_sistema tp
                INNER JOIN tenants t ON tp.tenant_id = t.id
                INNER JOIN planos_sistema ps ON tp.plano_sistema_id = ps.id
                INNER JOIN status_contrato sc ON tp.status_id = sc.id
                LEFT JOIN pagamentos_contrato pc ON tp.id = pc.contrato_id AND pc.status_pagamento_id IN (1, 4)
                WHERE tp.status_id IN (1, 4)
                AND pc.data_vencimento < CURDATE()
                GROUP BY tp.id
                ORDER BY data_vencimento_mais_antiga ASC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Renova um contrato por mais um período (mensal)
     */
    public function renovarContrato($contrato_id, $observacoes = null)
    {
        // Busca o contrato atual
        $sql = "SELECT * FROM tenant_planos_sistema WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $contrato_id]);
        $contrato_atual = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$contrato_atual) {
            throw new \Exception('Contrato não encontrado');
        }

        try {
            $this->pdo->beginTransaction();

            // Desativa o contrato atual (muda para Cancelado)
            $sql = "UPDATE tenant_planos_sistema SET status_id = 3 WHERE id = :id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['id' => $contrato_id]);

            // Calcula novas datas
            $data_inicio = date('Y-m-d', strtotime($contrato_atual['data_vencimento'] . ' +1 day'));
            $data_vencimento = date('Y-m-d', strtotime($data_inicio . ' +1 month'));

            // Cria novo contrato (renovação)
            $novo_contrato_id = $this->criar([
                'tenant_id' => $contrato_atual['tenant_id'],
                'plano_id' => $contrato_atual['plano_id'],
                'data_inicio' => $data_inicio,
                'data_vencimento' => $data_vencimento,
                'forma_pagamento' => $contrato_atual['forma_pagamento'],
                'observacoes' => $observacoes ?? 'Renovação automática'
            ]);

            $this->pdo->commit();
            
            return [
                'success' => true,
                'contrato_id' => $novo_contrato_id,
                'data_inicio' => $data_inicio,
                'data_vencimento' => $data_vencimento
            ];
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Listar TODOS os contratos com informações de academia e plano
     */
    public function listarTodos()
    {
        $sql = "SELECT 
                    tp.*, 
                    t.nome as academia_nome,
                    t.id as academia_id,
                    ps.nome as plano_nome, 
                    ps.valor,
                    ps.duracao_dias,
                    ps.max_alunos,
                    ps.max_admins,
                    ps.features,
                    sc.nome as status_nome,
                    sc.id as status_id
                FROM tenant_planos_sistema tp
                INNER JOIN tenants t ON tp.tenant_id = t.id
                INNER JOIN planos_sistema ps ON tp.plano_sistema_id = ps.id
                INNER JOIN status_contrato sc ON tp.status_id = sc.id
                ORDER BY tp.created_at DESC";
        
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Buscar contrato por ID
     */
    public function buscarPorId($id)
    {
        $sql = "SELECT tp.*, 
                    t.id as tenant_id,
                    t.nome as tenant_nome,
                    ps.id as plano_sistema_id,
                    ps.nome as plano_nome, 
                    ps.valor as plano_valor,
                    ps.descricao as plano_descricao,
                    ps.duracao_dias,
                    sc.id as status_id,
                    sc.nome as status_nome
                FROM tenant_planos_sistema tp
                INNER JOIN tenants t ON tp.tenant_id = t.id
                INNER JOIN planos_sistema ps ON tp.plano_sistema_id = ps.id
                INNER JOIN status_contrato sc ON tp.status_id = sc.id
                WHERE tp.id = :id";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Cancelar contrato (atualizar status_id para 3 - Cancelado)
     */
    public function cancelar($id)
    {
        $sql = "UPDATE tenant_planos_sistema 
                SET status_id = 3, 
                    updated_at = NOW()
                WHERE id = :id";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Listar contratos por tenant
     */
    public function listarPorTenant($tenant_id)
    {
        $sql = "SELECT 
                    tp.*, 
                    ps.nome as plano_nome, 
                    ps.valor,
                    ps.duracao_dias,
                    ps.max_alunos,
                    ps.max_admins,
                    ps.features,
                    sc.nome as status_nome,
                    sc.id as status_id
                FROM tenant_planos_sistema tp
                INNER JOIN planos_sistema ps ON tp.plano_sistema_id = ps.id
                INNER JOIN status_contrato sc ON tp.status_id = sc.id
                WHERE tp.tenant_id = :tenant_id
                ORDER BY tp.created_at DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['tenant_id' => $tenant_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Renovar contrato (atualizar data de vencimento)
     */
    public function renovar($contrato_id, $nova_data_vencimento)
    {
        $sql = "UPDATE tenant_planos_sistema 
                SET data_vencimento = :data_vencimento,
                    updated_at = NOW()
                WHERE id = :id";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            'id' => $contrato_id,
            'data_vencimento' => $nova_data_vencimento
        ]);
    }

    /**
     * Listar contratos próximos do vencimento
     */
    public function proximosVencimento($dias = 7)
    {
        $sql = "SELECT 
                    tp.*, 
                    t.nome as academia_nome,
                    t.id as academia_id,
                    ps.nome as plano_nome, 
                    ps.valor,
                    sc.nome as status_nome,
                    MIN(pc.data_vencimento) as proximo_vencimento,
                    DATEDIFF(MIN(pc.data_vencimento), CURDATE()) as dias_restantes
                FROM tenant_planos_sistema tp
                INNER JOIN tenants t ON tp.tenant_id = t.id
                INNER JOIN planos_sistema ps ON tp.plano_sistema_id = ps.id
                INNER JOIN status_contrato sc ON tp.status_id = sc.id
                LEFT JOIN pagamentos_contrato pc ON tp.id = pc.contrato_id AND pc.status_pagamento_id = 1
                WHERE tp.status_id = 1
                AND pc.data_vencimento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL :dias DAY)
                GROUP BY tp.id
                ORDER BY proximo_vencimento ASC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['dias' => $dias]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Listar contratos vencidos
     */
    public function vencidos()
    {
        $sql = "SELECT 
                    tp.*, 
                    t.nome as academia_nome,
                    t.id as academia_id,
                    ps.nome as plano_nome, 
                    ps.valor,
                    sc.nome as status_nome,
                    MIN(pc.data_vencimento) as data_vencimento_antiga,
                    DATEDIFF(CURDATE(), MIN(pc.data_vencimento)) as dias_vencido
                FROM tenant_planos_sistema tp
                INNER JOIN tenants t ON tp.tenant_id = t.id
                INNER JOIN planos_sistema ps ON tp.plano_sistema_id = ps.id
                INNER JOIN status_contrato sc ON tp.status_id = sc.id
                LEFT JOIN pagamentos_contrato pc ON tp.id = pc.contrato_id AND pc.status_pagamento_id IN (1, 4)
                WHERE tp.status_id = 1
                AND pc.data_vencimento < CURDATE()
                GROUP BY tp.id
                ORDER BY data_vencimento_antiga ASC";
        
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Atualizar status de um contrato
     */
    public function atualizarStatus(int $contratoId, int $statusId): bool
    {
        $sql = "UPDATE tenant_planos_sistema 
                SET status_id = :status_id
                WHERE id = :id";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            'id' => $contratoId,
            'status_id' => $statusId
        ]);
    }
}
