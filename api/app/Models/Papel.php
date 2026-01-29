<?php

namespace App\Models;

use PDO;

/**
 * Model para gerenciar papéis de usuários
 * Tabela: papeis
 * 
 * Papéis disponíveis:
 * - id=1: aluno (nivel 10)
 * - id=2: professor (nivel 50)
 * - id=3: admin (nivel 100)
 * - id=4: super_admin (nivel 200)
 */
class Papel
{
    private PDO $db;

    // Constantes para os IDs dos papéis
    public const ALUNO = 1;
    public const PROFESSOR = 2;
    public const ADMIN = 3;
    public const SUPER_ADMIN = 4;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Busca todos os papéis
     */
    public function getAll(): array
    {
        $stmt = $this->db->query("SELECT * FROM papeis WHERE ativo = 1 ORDER BY nivel ASC");
        return $stmt->fetchAll();
    }

    /**
     * Busca papel por ID
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM papeis WHERE id = ?");
        $stmt->execute([$id]);
        $papel = $stmt->fetch();
        
        return $papel ?: null;
    }

    /**
     * Busca papel por nome
     */
    public function findByNome(string $nome): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM papeis WHERE nome = ?");
        $stmt->execute([$nome]);
        $papel = $stmt->fetch();
        
        return $papel ?: null;
    }

    /**
     * Verifica se um papel tem nível suficiente
     */
    public function temNivelMinimo(int $papelId, int $nivelMinimo): bool
    {
        $papel = $this->findById($papelId);
        return $papel && $papel['nivel'] >= $nivelMinimo;
    }

    /**
     * Retorna papéis disponíveis para atribuição em tenant
     * (exclui super_admin que é global)
     */
    public function getPapeisParaTenant(): array
    {
        $stmt = $this->db->query("
            SELECT * FROM papeis 
            WHERE ativo = 1 AND id != " . self::SUPER_ADMIN . "
            ORDER BY nivel ASC
        ");
        return $stmt->fetchAll();
    }
}
