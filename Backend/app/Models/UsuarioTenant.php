<?php

namespace App\Models;

use PDO;

class UsuarioTenant
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Validar se usuário está vinculado ao tenant e está ativo
     * 
     * CRÍTICO: Evita "dados cruzados" (cross-tenant pollution)
     * 
     * @param int $usuarioId ID do usuário
     * @param int $tenantId ID do tenant
     * @return array|null Retorna registro de usuario_tenant se válido, null caso contrário
     */
    public function validarAcesso(int $usuarioId, int $tenantId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT ut.* 
             FROM usuario_tenant ut
             WHERE ut.usuario_id = :usuario_id 
             AND ut.tenant_id = :tenant_id 
             AND ut.status = 'ativo'"
        );
        
        $stmt->execute([
            'usuario_id' => $usuarioId,
            'tenant_id' => $tenantId
        ]);
        
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Validar múltiplos usuários de uma vez (batch check)
     * 
     * @param array $usuarioIds IDs dos usuários
     * @param int $tenantId ID do tenant
     * @return array Array com usuario_id => válido (bool)
     */
    public function validarAcessoBatch(array $usuarioIds, int $tenantId): array
    {
        if (empty($usuarioIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($usuarioIds), '?'));
        
        $stmt = $this->db->prepare(
            "SELECT usuario_id FROM usuario_tenant 
             WHERE usuario_id IN ($placeholders) 
             AND tenant_id = ? 
             AND status = 'ativo'"
        );
        
        $params = array_merge($usuarioIds, [$tenantId]);
        $stmt->execute($params);
        
        $validos = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
        
        $result = [];
        foreach ($usuarioIds as $id) {
            $result[$id] = in_array($id, $validos);
        }
        
        return $result;
    }

    /**
     * Contar quantos tenants um usuário tem acesso
     * 
     * @param int $usuarioId
     * @return int Contagem de tenants ativos
     */
    public function contarTenantsPorUsuario(int $usuarioId): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM usuario_tenant 
             WHERE usuario_id = :usuario_id AND status = 'ativo'"
        );
        
        $stmt->execute(['usuario_id' => $usuarioId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Listar todos os tenants que um usuário tem acesso
     * 
     * @param int $usuarioId
     * @return array
     */
    public function listarTenants(int $usuarioId): array
    {
        $stmt = $this->db->prepare(
            "SELECT ut.*, t.nome as tenant_nome 
             FROM usuario_tenant ut
             INNER JOIN tenants t ON ut.tenant_id = t.id
             WHERE ut.usuario_id = :usuario_id AND ut.status = 'ativo'"
        );
        
        $stmt->execute(['usuario_id' => $usuarioId]);
        return $stmt->fetchAll();
    }
}
