<?php

namespace App\Models;

use PDO;

class Usuario
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function create(array $data, int $tenantId = 1): ?int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO usuarios (tenant_id, nome, email, senha_hash) VALUES (:tenant_id, :nome, :email, :senha_hash)"
        );
        
        $stmt->execute([
            'tenant_id' => $tenantId,
            'nome' => $data['nome'],
            'email' => $data['email'],
            'senha_hash' => password_hash($data['senha'], PASSWORD_BCRYPT)
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function findByEmail(string $email, ?int $tenantId = null): ?array
    {
        $sql = "SELECT * FROM usuarios WHERE email = :email";
        $params = ['email' => $email];
        
        if ($tenantId) {
            $sql .= " AND tenant_id = :tenant_id";
            $params['tenant_id'] = $tenantId;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $user = $stmt->fetch();
        
        return $user ?: null;
    }

    public function findById(int $id, ?int $tenantId = null): ?array
    {
        $sql = "SELECT id, tenant_id, nome, email, foto_base64, created_at, updated_at FROM usuarios WHERE id = :id";
        $params = ['id' => $id];
        
        if ($tenantId) {
            $sql .= " AND tenant_id = :tenant_id";
            $params['tenant_id'] = $tenantId;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $user = $stmt->fetch();
        
        return $user ?: null;
    }

    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = ['id' => $id];

        if (isset($data['nome'])) {
            $fields[] = 'nome = :nome';
            $params['nome'] = $data['nome'];
        }

        if (isset($data['email'])) {
            $fields[] = 'email = :email';
            $params['email'] = $data['email'];
        }

        if (isset($data['senha'])) {
            $fields[] = 'senha_hash = :senha_hash';
            $params['senha_hash'] = password_hash($data['senha'], PASSWORD_BCRYPT);
        }

        if (isset($data['foto_base64'])) {
            $fields[] = 'foto_base64 = :foto_base64';
            $params['foto_base64'] = $data['foto_base64'];
        }

        if (empty($fields)) {
            return false;
        }

        $sql = "UPDATE usuarios SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute($params);
    }

    public function emailExists(string $email, ?int $excludeId = null, ?int $tenantId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM usuarios WHERE email = :email";
        $params = ['email' => $email];

        if ($tenantId) {
            $sql .= " AND tenant_id = :tenant_id";
            $params['tenant_id'] = $tenantId;
        }

        if ($excludeId) {
            $sql .= " AND id != :id";
            $params['id'] = $excludeId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Busca estatísticas de um usuário (checkins, foto, etc)
     */
    public function getEstatisticas(int $userId, ?int $tenantId = null): ?array
    {
        // Buscar dados do usuário
        $usuario = $this->findById($userId, $tenantId);
        
        if (!$usuario) {
            return null;
        }

        // Contar total de checkins
        $sqlCheckins = "SELECT COUNT(*) as total_checkins FROM checkins WHERE usuario_id = ?";
        $stmtCheckins = $this->db->prepare($sqlCheckins);
        $stmtCheckins->execute([$userId]);
        $totalCheckins = $stmtCheckins->fetch()['total_checkins'] ?? 0;

        // Aqui você pode adicionar mais estatísticas como PRs, etc.
        // Por enquanto, vou deixar PRs como 0 (pode ser implementado depois)
        
        return [
            'id' => $usuario['id'],
            'nome' => $usuario['nome'],
            'email' => $usuario['email'],
            'foto_url' => $usuario['foto_base64'] ?? null,
            'total_checkins' => (int) $totalCheckins,
            'total_prs' => 0, // Implementar se houver sistema de PRs
            'created_at' => $usuario['created_at'],
            'updated_at' => $usuario['updated_at']
        ];
    }
}
