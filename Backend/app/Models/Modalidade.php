<?php

namespace App\Models;

use PDO;

class Modalidade
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Listar todas as modalidades de um tenant
     */
    public function listarPorTenant(int $tenantId, bool $apenasAtivas = false): array
    {
        $sql = "SELECT m.*, 
                (SELECT COUNT(*) FROM planos p WHERE p.modalidade_id = m.id AND p.ativo = 1) as planos_count
                FROM modalidades m 
                WHERE m.tenant_id = :tenant_id";
        
        if ($apenasAtivas) {
            $sql .= " AND m.ativo = 1";
        }
        
        $sql .= " ORDER BY m.nome ASC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['tenant_id' => $tenantId]);
        
        $modalidades = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Buscar planos para cada modalidade
        foreach ($modalidades as &$modalidade) {
            $sqlPlanos = "SELECT id, nome, valor, checkins_semanais, duracao_dias, ativo, atual
                          FROM planos 
                          WHERE modalidade_id = :modalidade_id AND ativo = 1
                          ORDER BY checkins_semanais ASC";
            $stmtPlanos = $this->pdo->prepare($sqlPlanos);
            $stmtPlanos->execute(['modalidade_id' => $modalidade['id']]);
            $modalidade['planos'] = $stmtPlanos->fetchAll(PDO::FETCH_ASSOC);
        }
        
        return $modalidades;
    }

    /**
     * Buscar modalidade por ID
     */
    public function buscarPorId(int $id, ?int $tenantId = null): ?array
    {
        $sql = "SELECT * FROM modalidades WHERE id = :id";
        $params = ['id' => $id];
        
        if ($tenantId !== null) {
            $sql .= " AND tenant_id = :tenant_id";
            $params['tenant_id'] = $tenantId;
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result) {
            return null;
        }
        
        // Buscar planos da modalidade
        $sqlPlanos = "SELECT id, nome, valor, checkins_semanais, duracao_dias, ativo, atual 
                      FROM planos 
                      WHERE modalidade_id = :modalidade_id 
                      ORDER BY checkins_semanais ASC";
        $stmtPlanos = $this->pdo->prepare($sqlPlanos);
        $stmtPlanos->execute(['modalidade_id' => $id]);
        $result['planos'] = $stmtPlanos->fetchAll(PDO::FETCH_ASSOC);
        
        return $result;
    }

    /**
     * Criar nova modalidade
     */
    public function criar(array $dados): int
    {
        $sql = "INSERT INTO modalidades 
                (tenant_id, nome, descricao, cor, icone, ativo) 
                VALUES 
                (:tenant_id, :nome, :descricao, :cor, :icone, :ativo)";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'tenant_id' => $dados['tenant_id'],
            'nome' => $dados['nome'],
            'descricao' => $dados['descricao'] ?? null,
            'cor' => $dados['cor'] ?? '#f97316',
            'icone' => $dados['icone'] ?? 'activity',
            'ativo' => $dados['ativo'] ?? 1
        ]);
        
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Atualizar modalidade
     */
    public function atualizar(int $id, array $dados): bool
    {
        $sql = "UPDATE modalidades SET 
                nome = :nome,
                descricao = :descricao,
                cor = :cor,
                icone = :icone,
                ativo = :ativo
                WHERE id = :id";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            'id' => $id,
            'nome' => $dados['nome'],
            'descricao' => $dados['descricao'] ?? null,
            'cor' => $dados['cor'] ?? '#f97316',
            'icone' => $dados['icone'] ?? 'activity',
            'ativo' => $dados['ativo'] ?? 1
        ]);
    }

    /**
     * Alternar status da modalidade (ativar/desativar)
     */
    public function excluir(int $id): bool
    {
        $sql = "UPDATE modalidades SET ativo = NOT ativo WHERE id = :id";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Excluir modalidade permanentemente
     */
    public function excluirPermanente(int $id): bool
    {
        $sql = "DELETE FROM modalidades WHERE id = :id";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Verificar se nome já existe para o tenant
     */
    public function nomeExiste(int $tenantId, string $nome, ?int $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*) as total FROM modalidades 
                WHERE tenant_id = :tenant_id AND nome = :nome";
        
        if ($excludeId) {
            $sql .= " AND id != :exclude_id";
        }
        
        $stmt = $this->pdo->prepare($sql);
        $params = [
            'tenant_id' => $tenantId,
            'nome' => $nome
        ];
        
        if ($excludeId) {
            $params['exclude_id'] = $excludeId;
        }
        
        $stmt->execute($params);
        
        return (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'] > 0;
    }
}
