<?php

namespace App\Models;

use PDO;

/**
 * Model UsuarioTenant
 * 
 * ARQUITETURA: Sistema de Duas Tabelas (Decisão 2026-02-03)
 * 
 * Este model gerencia a tabela `usuario_tenant`, que é responsável por:
 * - Vínculo básico entre usuário e tenant (relacionamento N:M)
 * - Status do vínculo (ativo/inativo)
 * - Plano/assinatura do usuário no tenant (plano_id)
 * - Datas de início e fim do vínculo
 * 
 * IMPORTANTE: Coexiste com a tabela `tenant_usuario_papel`:
 * - usuario_tenant: Vínculo + Status + Plano + Datas (este model)
 * - tenant_usuario_papel: Papéis/Permissões (aluno=1, professor=2, admin=3)
 * 
 * Um usuário pode ter 1 vínculo por tenant (usuario_tenant) mas múltiplos
 * papéis no mesmo tenant (tenant_usuario_papel).
 * 
 * Exemplo:
 * - usuario_tenant: user_id=5, tenant_id=2, status='ativo', plano_id=1
 * - tenant_usuario_papel: user_id=5, tenant_id=2, papel_id=1 (aluno)
 * - tenant_usuario_papel: user_id=5, tenant_id=2, papel_id=2 (professor)
 */
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
     * @return array|null Retorna registro de tenant_usuario_papel se válido, null caso contrário
     */
    public function validarAcesso(int $usuarioId, int $tenantId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT tup.* 
             FROM tenant_usuario_papel tup
             WHERE tup.usuario_id = :usuario_id 
             AND tup.tenant_id = :tenant_id 
             AND tup.ativo = 1"
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
            "SELECT usuario_id FROM tenant_usuario_papel 
             WHERE usuario_id IN ($placeholders) 
             AND tenant_id = ? 
             AND ativo = 1"
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
            "SELECT COUNT(DISTINCT tenant_id) FROM tenant_usuario_papel 
             WHERE usuario_id = :usuario_id AND ativo = 1"
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
            "SELECT tup.*, t.nome as tenant_nome 
             FROM tenant_usuario_papel tup
             INNER JOIN tenants t ON tup.tenant_id = t.id
             WHERE tup.usuario_id = :usuario_id AND tup.ativo = 1
             GROUP BY tup.tenant_id"
        );
        
        $stmt->execute(['usuario_id' => $usuarioId]);
        return $stmt->fetchAll();
    }
}
