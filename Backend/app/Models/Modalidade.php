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
        $sql = "SELECT * FROM modalidades WHERE tenant_id = :tenant_id";
        
        if ($apenasAtivas) {
            $sql .= " AND ativo = 1";
        }
        
        $sql .= " ORDER BY nome ASC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['tenant_id' => $tenantId]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Buscar modalidade por ID
     */
    public function buscarPorId(int $id): ?array
    {
        $sql = "SELECT * FROM modalidades WHERE id = :id";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Criar nova modalidade
     */
    public function criar(array $dados): int
    {
        $sql = "INSERT INTO modalidades 
                (tenant_id, nome, descricao, valor_mensalidade, cor, icone, ativo) 
                VALUES 
                (:tenant_id, :nome, :descricao, :valor_mensalidade, :cor, :icone, :ativo)";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'tenant_id' => $dados['tenant_id'],
            'nome' => $dados['nome'],
            'descricao' => $dados['descricao'] ?? null,
            'valor_mensalidade' => $dados['valor_mensalidade'] ?? 0.00,
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
                valor_mensalidade = :valor_mensalidade,
                cor = :cor,
                icone = :icone,
                ativo = :ativo
                WHERE id = :id";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            'id' => $id,
            'nome' => $dados['nome'],
            'descricao' => $dados['descricao'] ?? null,
            'valor_mensalidade' => $dados['valor_mensalidade'] ?? 0.00,
            'cor' => $dados['cor'] ?? '#f97316',
            'icone' => $dados['icone'] ?? 'activity',
            'ativo' => $dados['ativo'] ?? 1
        ]);
    }

    /**
     * Excluir modalidade (soft delete)
     */
    public function excluir(int $id): bool
    {
        $sql = "UPDATE modalidades SET ativo = 0 WHERE id = :id";
        
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
