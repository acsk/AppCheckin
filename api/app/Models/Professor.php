<?php

namespace App\Models;

use PDO;

class Professor
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Listar todos os professores de um tenant
     */
    public function listarPorTenant(int $tenantId, bool $apenasAtivos = false): array
    {
        $sql = "SELECT p.*, 
                (SELECT COUNT(*) FROM turmas t WHERE t.professor_id = p.id AND t.ativo = 1) as turmas_count
                FROM professores p 
                WHERE p.tenant_id = :tenant_id";
        
        if ($apenasAtivos) {
            $sql .= " AND p.ativo = 1";
        }
        
        $sql .= " ORDER BY p.nome ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['tenant_id' => $tenantId]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Buscar professor por ID
     */
    public function findById(int $id, ?int $tenantId = null): ?array
    {
        $sql = "SELECT * FROM professores WHERE id = :id";
        $params = ['id' => $id];
        
        if ($tenantId) {
            $sql .= " AND tenant_id = :tenant_id";
            $params['tenant_id'] = $tenantId;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        $professor = $stmt->fetch(PDO::FETCH_ASSOC);
        return $professor ?: null;
    }

    /**
     * Buscar professor por email dentro de um tenant
     */
    public function findByEmail(string $email, int $tenantId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM professores WHERE email = :email AND tenant_id = :tenant_id"
        );
        $stmt->execute(['email' => $email, 'tenant_id' => $tenantId]);
        
        $professor = $stmt->fetch(PDO::FETCH_ASSOC);
        return $professor ?: null;
    }

    /**
     * Criar novo professor
     */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO professores (tenant_id, nome, email, telefone, cpf, foto_url, ativo) 
             VALUES (:tenant_id, :nome, :email, :telefone, :cpf, :foto_url, :ativo)"
        );
        
        $stmt->execute([
            'tenant_id' => $data['tenant_id'],
            'nome' => $data['nome'],
            'email' => $data['email'] ?? null,
            'telefone' => $data['telefone'] ?? null,
            'cpf' => $data['cpf'] ?? null,
            'foto_url' => $data['foto_url'] ?? null,
            'ativo' => $data['ativo'] ?? 1
        ]);
        
        return (int) $this->db->lastInsertId();
    }

    /**
     * Atualizar professor
     */
    public function update(int $id, array $data): bool
    {
        $updates = [];
        $params = ['id' => $id];
        
        $allowed = ['nome', 'email', 'telefone', 'cpf', 'foto_url', 'ativo'];
        
        foreach ($allowed as $field) {
            if (isset($data[$field])) {
                $updates[] = "$field = :$field";
                $params[$field] = $data[$field];
            }
        }
        
        if (empty($updates)) {
            return true;
        }
        
        $updates[] = "updated_at = CURRENT_TIMESTAMP";
        $sql = "UPDATE professores SET " . implode(', ', $updates) . " WHERE id = :id";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Deletar professor (soft delete)
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE professores SET ativo = 0, updated_at = CURRENT_TIMESTAMP WHERE id = :id"
        );
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Verificar se professor pertence ao tenant
     */
    public function pertenceAoTenant(int $professorId, int $tenantId): bool
    {
        $stmt = $this->db->prepare(
            "SELECT id FROM professores WHERE id = :id AND tenant_id = :tenant_id"
        );
        $stmt->execute(['id' => $professorId, 'tenant_id' => $tenantId]);
        
        return (bool) $stmt->fetch();
    }
}
