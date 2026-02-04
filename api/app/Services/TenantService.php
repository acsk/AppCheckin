<?php

namespace App\Services;

class TenantService
{
    private $db;

    public function __construct($database)
    {
        $this->db = $database;
    }

    /**
     * Obtém o tenant_id do usuário
     * Substitui a função MySQL: get_tenant_id_from_usuario()
     */
    public function getTenantIdFromUsuario(int $usuarioId): int
    {
        $query = "SELECT tup.tenant_id 
                  FROM tenant_usuario_papel tup 
                  WHERE tup.usuario_id = :usuario_id 
                  AND tup.ativo = 1 
                  LIMIT 1";

        $stmt = $this->db->prepare($query);
        $stmt->execute([':usuario_id' => $usuarioId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        // Se não encontrar, retornar tenant padrão
        return $result ? (int)$result['tenant_id'] : 1;
    }
}
