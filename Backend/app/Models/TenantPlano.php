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
     */
    public function criar($dados)
    {
        $sql = "INSERT INTO tenant_planos 
                (tenant_id, plano_id, data_inicio, data_vencimento, forma_pagamento, observacoes) 
                VALUES 
                (:tenant_id, :plano_id, :data_inicio, :data_vencimento, :forma_pagamento, :observacoes)";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'tenant_id' => $dados['tenant_id'],
            'plano_id' => $dados['plano_id'],
            'data_inicio' => $dados['data_inicio'],
            'data_vencimento' => $dados['data_vencimento'],
            'forma_pagamento' => $dados['forma_pagamento'],
            'observacoes' => $dados['observacoes'] ?? null
        ]);
        
        return $this->pdo->lastInsertId();
    }

    /**
     * Busca o contrato ativo de um tenant
     */
    public function buscarContratoAtivo($tenant_id)
    {
        $sql = "SELECT tp.*, p.nome as plano_nome, p.valor, p.max_usuarios, p.max_turmas
                FROM tenant_planos tp
                INNER JOIN planos p ON tp.plano_id = p.id
                WHERE tp.tenant_id = :tenant_id 
                AND tp.status = 'ativo'
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
                FROM tenant_planos tp
                INNER JOIN planos p ON tp.plano_id = p.id
                WHERE tp.tenant_id = :tenant_id
                ORDER BY tp.created_at DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['tenant_id' => $tenant_id]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Desativa o contrato ativo atual do tenant
     */
    public function desativarContratoAtivo($tenant_id)
    {
        $sql = "UPDATE tenant_planos 
                SET status = 'inativo' 
                WHERE tenant_id = :tenant_id 
                AND status = 'ativo'";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute(['tenant_id' => $tenant_id]);
    }

    /**
     * Troca o plano do tenant
     * Desativa o contrato atual e cria um novo
     */
    public function trocarPlano($tenant_id, $novo_plano_id, $forma_pagamento, $observacoes = null)
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
                'plano_id' => $novo_plano_id,
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
        $sql = "UPDATE tenant_planos 
                SET status = 'cancelado' 
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
                FROM tenant_planos 
                WHERE tenant_id = :tenant_id 
                AND status = 'ativo'";
        
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
        
        $sql = "SELECT tp.*, t.nome as tenant_nome, t.email, p.nome as plano_nome, p.valor
                FROM tenant_planos tp
                INNER JOIN tenants t ON tp.tenant_id = t.id
                INNER JOIN planos p ON tp.plano_id = p.id
                WHERE tp.status = 'ativo'
                AND tp.data_vencimento <= :data_limite
                AND tp.data_vencimento >= CURDATE()
                ORDER BY tp.data_vencimento ASC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['data_limite' => $data_limite]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Busca contratos vencidos
     */
    public function buscarContratosVencidos()
    {
        $sql = "SELECT tp.*, t.nome as tenant_nome, t.email, p.nome as plano_nome
                FROM tenant_planos tp
                INNER JOIN tenants t ON tp.tenant_id = t.id
                INNER JOIN planos p ON tp.plano_id = p.id
                WHERE tp.status = 'ativo'
                AND tp.data_vencimento < CURDATE()
                ORDER BY tp.data_vencimento ASC";
        
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
        $sql = "SELECT * FROM tenant_planos WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $contrato_id]);
        $contrato_atual = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$contrato_atual) {
            throw new \Exception('Contrato não encontrado');
        }

        try {
            $this->pdo->beginTransaction();

            // Desativa o contrato atual
            $sql = "UPDATE tenant_planos SET status = 'inativo' WHERE id = :id";
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
                    p.nome as plano_nome, 
                    p.valor,
                    p.duracao_dias,
                    p.checkins_mensais,
                    p.max_alunos
                FROM tenant_planos tp
                INNER JOIN tenants t ON tp.tenant_id = t.id
                INNER JOIN planos p ON tp.plano_id = p.id
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
                    t.nome as academia_nome,
                    p.nome as plano_nome, 
                    p.valor
                FROM tenant_planos tp
                INNER JOIN tenants t ON tp.tenant_id = t.id
                INNER JOIN planos p ON tp.plano_id = p.id
                WHERE tp.id = :id";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Cancelar contrato (atualizar status para 'cancelado')
     */
    public function cancelar($id)
    {
        $sql = "UPDATE tenant_planos 
                SET status = 'cancelado', 
                    updated_at = NOW()
                WHERE id = :id";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute(['id' => $id]);
    }
}
