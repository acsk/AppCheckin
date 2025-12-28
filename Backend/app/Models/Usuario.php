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
        $sql = "
            SELECT u.id, u.tenant_id, u.nome, u.email, u.role_id, u.plano_id, 
                   u.data_vencimento_plano, u.foto_base64, u.created_at, u.updated_at,
                   r.nome as role_nome, r.descricao as role_descricao
            FROM usuarios u
            LEFT JOIN roles r ON u.role_id = r.id
            WHERE u.id = :id
        ";
        $params = ['id' => $id];
        
        if ($tenantId) {
            $sql .= " AND u.tenant_id = :tenant_id";
            $params['tenant_id'] = $tenantId;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $user = $stmt->fetch();
        
        if (!$user) {
            return null;
        }
        
        // Estruturar com objeto role se existir
        if ($user['role_id']) {
            $user['role'] = [
                'id' => $user['role_id'],
                'nome' => $user['role_nome'],
                'descricao' => $user['role_descricao']
            ];
            unset($user['role_nome'], $user['role_descricao']);
        }
        
        return $user;
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

        if (isset($data['role_id'])) {
            $fields[] = 'role_id = :role_id';
            $params['role_id'] = $data['role_id'];
        }

        if (isset($data['plano_id'])) {
            $fields[] = 'plano_id = :plano_id';
            $params['plano_id'] = $data['plano_id'];
        }

        if (isset($data['data_vencimento_plano'])) {
            $fields[] = 'data_vencimento_plano = :data_vencimento_plano';
            $params['data_vencimento_plano'] = $data['data_vencimento_plano'];
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

    /**
     * Buscar todos os tenants/academias de um usuário
     */
    public function getTenantsByUsuario(int $usuarioId): array
    {
        $sql = "
            SELECT 
                ut.id as vinculo_id,
                ut.status,
                ut.data_inicio,
                ut.data_fim,
                t.id as tenant_id,
                t.nome as tenant_nome,
                t.slug as tenant_slug,
                t.email as tenant_email,
                t.telefone as tenant_telefone,
                p.id as plano_id,
                p.nome as plano_nome,
                p.valor as plano_valor,
                p.duracao_dias as plano_duracao_dias
            FROM usuario_tenant ut
            INNER JOIN tenants t ON ut.tenant_id = t.id
            LEFT JOIN planos p ON ut.plano_id = p.id
            WHERE ut.usuario_id = :usuario_id
            AND t.ativo = 1
            ORDER BY ut.status = 'ativo' DESC, t.nome ASC
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['usuario_id' => $usuarioId]);
        $tenants = $stmt->fetchAll();
        
        // Estruturar os dados
        return array_map(function($row) {
            return [
                'vinculo_id' => $row['vinculo_id'],
                'status' => $row['status'],
                'data_inicio' => $row['data_inicio'],
                'data_fim' => $row['data_fim'],
                'tenant' => [
                    'id' => $row['tenant_id'],
                    'nome' => $row['tenant_nome'],
                    'slug' => $row['tenant_slug'],
                    'email' => $row['tenant_email'],
                    'telefone' => $row['tenant_telefone']
                ],
                'plano' => $row['plano_id'] ? [
                    'id' => $row['plano_id'],
                    'nome' => $row['plano_nome'],
                    'valor' => $row['plano_valor'],
                    'duracao_dias' => $row['plano_duracao_dias']
                ] : null
            ];
        }, $tenants);
    }

    /**
     * Criar vínculo entre usuário e tenant
     */
    public function vincularTenant(int $usuarioId, int $tenantId, ?int $planoId = null): bool
    {
        $sql = "
            INSERT INTO usuario_tenant (usuario_id, tenant_id, plano_id, status, data_inicio)
            VALUES (:usuario_id, :tenant_id, :plano_id, 'ativo', CURRENT_DATE)
            ON DUPLICATE KEY UPDATE status = 'ativo', updated_at = CURRENT_TIMESTAMP
        ";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'usuario_id' => $usuarioId,
            'tenant_id' => $tenantId,
            'plano_id' => $planoId
        ]);
    }

    /**
     * Verificar se usuário tem acesso a um tenant específico
     */
    public function temAcessoTenant(int $usuarioId, int $tenantId): bool
    {
        $sql = "
            SELECT COUNT(*) 
            FROM usuario_tenant 
            WHERE usuario_id = :usuario_id 
            AND tenant_id = :tenant_id 
            AND status = 'ativo'
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'usuario_id' => $usuarioId,
            'tenant_id' => $tenantId
        ]);
        
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Buscar usuário por email global (independente de tenant)
     */
    public function findByEmailGlobal(string $email): ?array
    {
        // Tentar primeiro com email_global, se não existir usar email
        $sql = "SELECT * FROM usuarios WHERE ";
        
        // Verificar se coluna email_global existe
        try {
            $checkColumn = $this->db->query("SHOW COLUMNS FROM usuarios LIKE 'email_global'");
            $hasEmailGlobal = $checkColumn->fetch() !== false;
            
            if ($hasEmailGlobal) {
                $sql .= "(email_global = :email OR email = :email2)";
                $params = ['email' => $email, 'email2' => $email];
            } else {
                $sql .= "email = :email";
                $params = ['email' => $email];
            }
        } catch (\PDOException $e) {
            // Se falhar, usar apenas email
            $sql .= "email = :email";
            $params = ['email' => $email];
        }
        
        $sql .= " LIMIT 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $user = $stmt->fetch();
        
        return $user ?: null;
    }

    /**
     * Listar todos os usuários (com ou sem filtro de tenant)
     * 
     * @param bool $isSuperAdmin Se true, lista TODOS os usuários sem filtro
     * @param int|null $tenantId ID do tenant para filtrar (usado quando não é SuperAdmin)
     * @param bool $apenasAtivos Filtrar apenas usuários ativos
     * @return array Lista de usuários com informações de tenant, role e plano
     */
    public function listarTodos(bool $isSuperAdmin = false, ?int $tenantId = null, bool $apenasAtivos = false): array
    {
        $sql = "
            SELECT 
                u.id, 
                u.tenant_id,
                u.nome, 
                u.email, 
                u.role_id,
                u.plano_id,
                u.data_vencimento_plano,
                u.foto_base64,
                u.created_at,
                u.updated_at,
                COALESCE(u.ativo, TRUE) as ativo,
                r.nome as role_nome,
                p.nome as plano_nome,
                t.nome as tenant_nome,
                t.slug as tenant_slug
            FROM usuarios u
            LEFT JOIN roles r ON u.role_id = r.id
            LEFT JOIN planos p ON u.plano_id = p.id
            LEFT JOIN tenants t ON u.tenant_id = t.id
        ";
        
        $conditions = [];
        $params = [];
        
        // Se NÃO for SuperAdmin, filtrar por tenant
        if (!$isSuperAdmin && $tenantId !== null) {
            $conditions[] = "u.tenant_id = :tenant_id";
            $params['tenant_id'] = $tenantId;
        }
        
        // Filtrar apenas ativos se solicitado
        if ($apenasAtivos) {
            $conditions[] = "COALESCE(u.ativo, TRUE) = TRUE";
        }
        
        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }
        
        $sql .= " ORDER BY t.nome ASC, u.nome ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        $result = $stmt->fetchAll();
        
        error_log("DEBUG listarTodos() - isSuperAdmin: " . ($isSuperAdmin ? 'true' : 'false'));
        error_log("DEBUG listarTodos() - tenantId: " . ($tenantId ?? 'null'));
        error_log("DEBUG listarTodos() - apenasAtivos: " . ($apenasAtivos ? 'true' : 'false'));
        error_log("DEBUG listarTodos() - Total rows: " . count($result));
        
        // Estruturar dados incluindo tenant e status ativo
        return array_map(function($row) {
            return [
                'id' => $row['id'],
                'nome' => $row['nome'],
                'email' => $row['email'],
                'role_id' => $row['role_id'],
                'role_nome' => $row['role_nome'],
                'plano_id' => $row['plano_id'],
                'plano_nome' => $row['plano_nome'],
                'data_vencimento_plano' => $row['data_vencimento_plano'],
                'ativo' => (bool) $row['ativo'],
                'status' => $row['ativo'] ? 'ativo' : 'inativo',
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'],
                'tenant' => [
                    'id' => $row['tenant_id'],
                    'nome' => $row['tenant_nome'],
                    'slug' => $row['tenant_slug']
                ]
            ];
        }, $result);
    }

    /**
     * Listar usuários do tenant
     * 
     * @param int $tenantId ID do tenant
     * @param bool $apenasAtivos Filtrar apenas usuários ativos
     * @return array Lista de usuários do tenant
     */
    public function listarPorTenant(int $tenantId, bool $apenasAtivos = false): array
    {
        $sql = "
            SELECT 
                u.id, 
                u.nome, 
                u.email, 
                u.role_id,
                u.plano_id,
                u.data_vencimento_plano,
                u.foto_base64,
                u.created_at,
                u.updated_at,
                r.nome as role_nome,
                p.nome as plano_nome,
                ut.status,
                COALESCE(u.ativo, TRUE) as ativo
            FROM usuarios u
            LEFT JOIN roles r ON u.role_id = r.id
            LEFT JOIN planos p ON u.plano_id = p.id
            LEFT JOIN usuario_tenant ut ON u.id = ut.usuario_id AND ut.tenant_id = ?
            WHERE u.tenant_id = ?
        ";

        if ($apenasAtivos) {
            $sql .= " AND (ut.status = 'ativo' OR ut.status IS NULL) AND COALESCE(u.ativo, TRUE) = TRUE";
        }

        $sql .= " ORDER BY u.nome ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$tenantId, $tenantId]);
        
        return $stmt->fetchAll();
    }

    /**
     * Criar usuário completo com todos os campos
     */
    public function criarUsuarioCompleto(array $data, int $tenantId): ?int
    {
        $sql = "
            INSERT INTO usuarios 
            (tenant_id, nome, email, senha_hash, role_id, plano_id, data_vencimento_plano) 
            VALUES 
            (:tenant_id, :nome, :email, :senha_hash, :role_id, :plano_id, :data_vencimento_plano)
        ";
        
        $stmt = $this->db->prepare($sql);
        
        $stmt->execute([
            'tenant_id' => $tenantId,
            'nome' => $data['nome'],
            'email' => $data['email'],
            'senha_hash' => password_hash($data['senha'], PASSWORD_BCRYPT),
            'role_id' => $data['role_id'] ?? 1, // Default: Aluno
            'plano_id' => $data['plano_id'] ?? null,
            'data_vencimento_plano' => $data['data_vencimento_plano'] ?? null
        ]);

        $usuarioId = (int) $this->db->lastInsertId();

        // Criar vínculo na tabela usuario_tenant se existir
        if ($usuarioId) {
            try {
                $this->vincularTenant($usuarioId, $tenantId, $data['plano_id'] ?? null);
            } catch (\PDOException $e) {
                // Se falhar, não é crítico (pode ser que a tabela não exista ainda)
            }
        }

        return $usuarioId;
    }

    /**
     * Desativar usuário no tenant (soft delete)
     */
    public function desativarUsuarioTenant(int $usuarioId, int $tenantId): bool
    {
        // Atualizar status na tabela usuario_tenant
        $sqlVinculo = "
            UPDATE usuario_tenant 
            SET status = 'inativo', data_fim = CURRENT_DATE, updated_at = CURRENT_TIMESTAMP
            WHERE usuario_id = :usuario_id AND tenant_id = :tenant_id
        ";
        
        try {
            $stmt = $this->db->prepare($sqlVinculo);
            $stmt->execute([
                'usuario_id' => $usuarioId,
                'tenant_id' => $tenantId
            ]);
        } catch (\PDOException $e) {
            // Se tabela não existir, apenas continuar
        }

        // Marcar o usuário como inativo na tabela principal (soft delete global)
        $sqlUsuario = "
            UPDATE usuarios 
            SET ativo = FALSE, updated_at = CURRENT_TIMESTAMP
            WHERE id = :usuario_id
        ";
        
        try {
            $stmt = $this->db->prepare($sqlUsuario);
            $stmt->execute(['usuario_id' => $usuarioId]);
        } catch (\PDOException $e) {
            // Se coluna não existir, apenas continuar (para backward compatibility)
            error_log("Aviso: Campo 'ativo' não existe na tabela usuarios. Execute a migration 017.");
        }

        return true;
    }
};