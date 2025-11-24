<?php

namespace App\Models;

use PDO;

class Role
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Busca todos os roles
     */
    public function getAll(): array
    {
        $stmt = $this->db->query("SELECT * FROM roles ORDER BY id ASC");
        return $stmt->fetchAll();
    }

    /**
     * Busca role por ID
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM roles WHERE id = ?");
        $stmt->execute([$id]);
        $role = $stmt->fetch();
        
        return $role ?: null;
    }

    /**
     * Busca role por nome
     */
    public function findByNome(string $nome): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM roles WHERE nome = ?");
        $stmt->execute([$nome]);
        $role = $stmt->fetch();
        
        return $role ?: null;
    }
}
