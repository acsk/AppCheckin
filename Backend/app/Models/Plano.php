<?php

namespace App\Models;

use PDO;

class Plano
{
    private PDO $db;
    private int $tenantId;

    public function __construct(PDO $db, int $tenantId = 1)
    {
        $this->db = $db;
        $this->tenantId = $tenantId;
    }

    /**
     * Busca todos os planos ativos
     */
    public function getAll(bool $apenasAtivos = false): array
    {
        $sql = "SELECT * FROM planos WHERE tenant_id = ?";
        
        if ($apenasAtivos) {
            $sql .= " AND ativo = 1";
        }
        
        $sql .= " ORDER BY valor ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$this->tenantId]);
        
        return $stmt->fetchAll();
    }

    /**
     * Busca plano por ID
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM planos 
            WHERE id = ? AND tenant_id = ?
        ");
        $stmt->execute([$id, $this->tenantId]);
        $plano = $stmt->fetch();
        
        return $plano ?: null;
    }

    /**
     * Cria novo plano
     */
    public function create(array $data): ?int
    {
        $stmt = $this->db->prepare("
            INSERT INTO planos 
            (tenant_id, nome, descricao, valor, duracao_dias, checkins_mensais, ativo) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $this->tenantId,
            $data['nome'],
            $data['descricao'] ?? null,
            $data['valor'],
            $data['duracao_dias'],
            $data['checkins_mensais'] ?? null,
            $data['ativo'] ?? true
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Atualiza plano
     */
    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = [];

        if (isset($data['nome'])) {
            $fields[] = 'nome = ?';
            $params[] = $data['nome'];
        }

        if (isset($data['descricao'])) {
            $fields[] = 'descricao = ?';
            $params[] = $data['descricao'];
        }

        if (isset($data['valor'])) {
            $fields[] = 'valor = ?';
            $params[] = $data['valor'];
        }

        if (isset($data['duracao_dias'])) {
            $fields[] = 'duracao_dias = ?';
            $params[] = $data['duracao_dias'];
        }

        if (isset($data['checkins_mensais'])) {
            $fields[] = 'checkins_mensais = ?';
            $params[] = $data['checkins_mensais'];
        }

        if (isset($data['ativo'])) {
            $fields[] = 'ativo = ?';
            $params[] = $data['ativo'];
        }

        if (empty($fields)) {
            return false;
        }

        $params[] = $id;
        $params[] = $this->tenantId;

        $sql = "UPDATE planos SET " . implode(', ', $fields) . " WHERE id = ? AND tenant_id = ?";
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute($params);
    }

    /**
     * Exclui plano (soft delete - desativa)
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("
            UPDATE planos SET ativo = 0 
            WHERE id = ? AND tenant_id = ?
        ");
        
        return $stmt->execute([$id, $this->tenantId]);
    }

    /**
     * Conta quantos usuários estão usando um plano
     */
    public function countUsuarios(int $planoId): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as total
            FROM usuarios 
            WHERE plano_id = ? AND tenant_id = ?
        ");
        $stmt->execute([$planoId, $this->tenantId]);
        
        return (int) $stmt->fetch()['total'];
    }
}
