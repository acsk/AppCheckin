<?php

namespace App\Models;

use PDO;

class Dia
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function getAtivos(): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM dias WHERE ativo = 1 AND data >= CURDATE() ORDER BY data ASC"
        );
        $stmt->execute();
        
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM dias WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $dia = $stmt->fetch();
        
        return $dia ?: null;
    }
}
