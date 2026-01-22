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
        try {
            // Limpar CPF e CEP (remover formatação)
            $cpfLimpo = isset($data['cpf']) ? preg_replace('/[^0-9]/', '', $data['cpf']) : null;
            $cepLimpo = isset($data['cep']) ? preg_replace('/[^0-9]/', '', $data['cep']) : null;
            
            // Converter campos de texto para maiúsculas
            $nome = isset($data['nome']) ? mb_strtoupper(trim($data['nome']), 'UTF-8') : null;
            $logradouro = isset($data['logradouro']) ? mb_strtoupper(trim($data['logradouro']), 'UTF-8') : null;
            $complemento = isset($data['complemento']) ? mb_strtoupper(trim($data['complemento']), 'UTF-8') : null;
            $bairro = isset($data['bairro']) ? mb_strtoupper(trim($data['bairro']), 'UTF-8') : null;
            $cidade = isset($data['cidade']) ? mb_strtoupper(trim($data['cidade']), 'UTF-8') : null;
            $estado = isset($data['estado']) ? mb_strtoupper(trim($data['estado']), 'UTF-8') : null;
            
            // 1. Inserir usuário (sem tenant_id, pois isso vai para usuario_tenant)
            $stmt = $this->db->prepare(
                "INSERT INTO usuarios (nome, email, email_global, senha_hash, role_id, cpf, cep, logradouro, numero, complemento, bairro, cidade, estado, telefone, ativo) 
                 VALUES (:nome, :email, :email_global, :senha_hash, :role_id, :cpf, :cep, :logradouro, :numero, :complemento, :bairro, :cidade, :estado, :telefone, :ativo)"
            );
            
            $stmt->execute([
                'nome' => $nome,
                'email' => $data['email'],
                'email_global' => $data['email_global'] ?? $data['email'],
                'senha_hash' => password_hash($data['senha'], PASSWORD_BCRYPT),
                'role_id' => $data['role_id'] ?? 1,
                'cpf' => $cpfLimpo ?: null,
                'cep' => $cepLimpo ?: null,
                'logradouro' => $logradouro,
                'numero' => $data['numero'] ?? null,
                'complemento' => $complemento,
                'bairro' => $bairro,
                'cidade' => $cidade,
                'estado' => $estado,
                'telefone' => $data['telefone'] ?? null,
                'ativo' => $data['ativo'] ?? 1
            ]);
            
            $usuarioId = (int) $this->db->lastInsertId();
            
            // 2. Criar vínculo com tenant em usuario_tenant
            $stmtTenant = $this->db->prepare(
                "INSERT INTO usuario_tenant (usuario_id, tenant_id, status, data_inicio) 
                 VALUES (:usuario_id, :tenant_id, 'ativo', CURDATE())"
            );
            
            $stmtTenant->execute([
                'usuario_id' => $usuarioId,
                'tenant_id' => $tenantId
            ]);
            
            return $usuarioId;
        } catch (\PDOException $e) {
            error_log("Erro ao criar usuário: " . $e->getMessage());
            return null;
        }
    }

    public function findByEmail(string $email, ?int $tenantId = null): ?array
    {
        if ($tenantId) {
            // Se tenantId fornecido, verificar se usuário pertence ao tenant
            $sql = "
                SELECT u.* 
                FROM usuarios u
                INNER JOIN usuario_tenant ut ON ut.usuario_id = u.id
                WHERE u.email = :email 
                AND ut.tenant_id = :tenant_id
                AND ut.status = 'ativo'
            ";
            $params = ['email' => $email, 'tenant_id' => $tenantId];
        } else {
            // Busca global por email
            $sql = "SELECT * FROM usuarios WHERE email = :email";
            $params = ['email' => $email];
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $user = $stmt->fetch();
        
        return $user ?: null;
    }

    public function findById(int $id, ?int $tenantId = null): ?array
    {
        // Se tenantId for fornecido, usar INNER JOIN para garantir que o usuário pertence ao tenant
        // Se não, usar LEFT JOIN para retornar dados mesmo sem tenant associado
        $joinType = $tenantId ? "INNER JOIN" : "LEFT JOIN";
        
        $sql = "
            SELECT u.id, COALESCE(ut.tenant_id, :fallback_tenant) as tenant_id, ut.status, 
                   u.nome, u.email, u.role_id, 
                   u.foto_base64, u.foto_caminho, u.telefone,
                   u.cpf, u.cep, u.logradouro, u.numero, u.complemento, u.bairro, u.cidade, u.estado,
                   u.created_at, u.updated_at,
                   r.nome as role_nome, r.descricao as role_descricao
            FROM usuarios u
            LEFT JOIN roles r ON u.role_id = r.id
            {$joinType} usuario_tenant ut ON ut.usuario_id = u.id
        ";
        
        $conditions = ["u.id = :id"];
        $params = ['id' => $id, 'fallback_tenant' => $tenantId ?? 0];
        
        if ($tenantId) {
            $conditions[] = "ut.tenant_id = :tenant_id";
            $params['tenant_id'] = $tenantId;
        }
        
        $sql .= " WHERE " . implode(' AND ', $conditions);
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);
        
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
            $params['nome'] = mb_strtoupper(trim($data['nome']), 'UTF-8');
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

        if (isset($data['telefone'])) {
            $fields[] = 'telefone = :telefone';
            $params['telefone'] = $data['telefone'];
        }

        if (isset($data['cpf'])) {
            $fields[] = 'cpf = :cpf';
            $params['cpf'] = preg_replace('/[^0-9]/', '', $data['cpf']) ?: null;
        }

        if (isset($data['cep'])) {
            $fields[] = 'cep = :cep';
            $params['cep'] = preg_replace('/[^0-9]/', '', $data['cep']) ?: null;
        }

        if (isset($data['logradouro'])) {
            $fields[] = 'logradouro = :logradouro';
            $params['logradouro'] = mb_strtoupper(trim($data['logradouro']), 'UTF-8');
        }

        if (isset($data['numero'])) {
            $fields[] = 'numero = :numero';
            $params['numero'] = $data['numero'];
        }

        if (isset($data['complemento'])) {
            $fields[] = 'complemento = :complemento';
            $params['complemento'] = mb_strtoupper(trim($data['complemento']), 'UTF-8');
        }

        if (isset($data['bairro'])) {
            $fields[] = 'bairro = :bairro';
            $params['bairro'] = mb_strtoupper(trim($data['bairro']), 'UTF-8');
        }

        if (isset($data['cidade'])) {
            $fields[] = 'cidade = :cidade';
            $params['cidade'] = mb_strtoupper(trim($data['cidade']), 'UTF-8');
        }

        if (isset($data['estado'])) {
            $fields[] = 'estado = :estado';
            $params['estado'] = mb_strtoupper(trim($data['estado']), 'UTF-8');
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
        // Verificar email_global (único no sistema) ou email em combinação com tenant
        if ($tenantId) {
            // Verificar se email já existe no tenant específico
            $sql = "
                SELECT COUNT(*) 
                FROM usuarios u
                INNER JOIN usuario_tenant ut ON ut.usuario_id = u.id
                WHERE u.email = :email 
                AND ut.tenant_id = :tenant_id
                AND ut.status = 'ativo'
            ";
            $params = ['email' => $email, 'tenant_id' => $tenantId];
            
            if ($excludeId) {
                $sql .= " AND u.id != :id";
                $params['id'] = $excludeId;
            }
        } else {
            // Verificar email_global (único no sistema inteiro)
            $sql = "SELECT COUNT(*) FROM usuarios WHERE email_global = :email";
            $params = ['email' => $email];
            
            if ($excludeId) {
                $sql .= " AND id != :id";
                $params['id'] = $excludeId;
            }
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
                u.nome, 
                u.email,
                u.telefone,
                u.cpf,
                u.role_id,
                u.foto_base64,
                u.created_at,
                u.updated_at,
                r.nome as role_nome,
                ut.status,
                CASE 
                    WHEN ut.status = 'ativo' THEN 1
                    ELSE 0
                END as ativo
            FROM usuarios u
            LEFT JOIN roles r ON u.role_id = r.id
            INNER JOIN usuario_tenant ut ON u.id = ut.usuario_id
        ";
        
        $conditions = [];
        $params = [];
        
        // Se NÃO for SuperAdmin, filtrar por tenant
        if (!$isSuperAdmin && $tenantId !== null) {
            $conditions[] = "ut.tenant_id = :tenant_id";
            $params['tenant_id'] = $tenantId;
        }
        
        // Filtrar apenas ativos se solicitado
        if ($apenasAtivos) {
            $conditions[] = "ut.status = 'ativo'";
        }
        
        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }
        
        // Ordenar para evitar duplicatas quando usuário está em múltiplos tenants
        $sql .= " ORDER BY u.id ASC, ut.status DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        $result = $stmt->fetchAll();
        
        // Remover duplicatas: manter apenas o primeiro registro de cada usuário
        $usuariosProcessados = [];
        $usuariosMap = [];
        
        foreach ($result as $row) {
            $usuarioId = $row['id'];
            
            // Se ainda não processamos este usuário, adicionar à lista
            if (!isset($usuariosMap[$usuarioId])) {
                $usuariosMap[$usuarioId] = true;
                $usuariosProcessados[] = [
                    'id' => $row['id'],
                    'nome' => $row['nome'],
                    'email' => $row['email'],
                    'telefone' => $row['telefone'] ?? null,
                    'cpf' => $row['cpf'] ?? null,
                    'role_id' => $row['role_id'],
                    'role_nome' => $row['role_nome'],
                    'ativo' => (bool) $row['ativo'],
                    'status' => $row['status'],
                    'created_at' => $row['created_at'],
                    'updated_at' => $row['updated_at']
                ];
            }
        }
        
        return $usuariosProcessados;
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
                u.telefone,
                u.cpf,
                u.role_id,
                u.foto_base64,
                u.created_at,
                u.updated_at,
                r.nome as role_nome,
                ut.status,
                CASE 
                    WHEN ut.status = 'ativo' THEN 1
                    ELSE 0
                END as ativo
            FROM usuarios u
            LEFT JOIN roles r ON u.role_id = r.id
            INNER JOIN usuario_tenant ut ON u.id = ut.usuario_id
            WHERE ut.tenant_id = :tenant_id
        ";

        $params = ['tenant_id' => $tenantId];

        if ($apenasAtivos) {
            $sql .= " AND ut.status = 'ativo'";
        }

        $sql .= " ORDER BY u.nome ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        $result = $stmt->fetchAll();
        
        // Adicionar informações do tenant para consistência
        return array_map(function($row) use ($tenantId) {
            return [
                'id' => $row['id'],
                'nome' => $row['nome'],
                'email' => $row['email'],
                'telefone' => $row['telefone'] ?? null,
                'cpf' => $row['cpf'] ?? null,
                'role_id' => $row['role_id'],
                'role_nome' => $row['role_nome'],
                'ativo' => (bool) $row['ativo'],
                'status' => $row['status'],
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at']
            ];
        }, $result);
    }

    /**
     * Criar usuário completo com todos os campos
     */
    public function criarUsuarioCompleto(array $data, int $tenantId): ?int
    {
        // Limpar CPF (remover formatação)
        $cpfLimpo = isset($data['cpf']) ? preg_replace('/[^0-9]/', '', $data['cpf']) : null;
        
        // Converter nome para maiúsculas
        $nome = isset($data['nome']) ? mb_strtoupper(trim($data['nome']), 'UTF-8') : null;
        
        $sql = "
            INSERT INTO usuarios 
            (nome, email, senha_hash, telefone, cpf, role_id) 
            VALUES 
            (:nome, :email, :senha_hash, :telefone, :cpf, :role_id)
        ";
        
        $stmt = $this->db->prepare($sql);
        
        $stmt->execute([
            'nome' => $nome,
            'email' => $data['email'],
            'senha_hash' => password_hash($data['senha'], PASSWORD_BCRYPT),
            'telefone' => $data['telefone'] ?? null,
            'cpf' => $cpfLimpo ?: null,
            'role_id' => $data['role_id'] ?? 1, // Default: Aluno
        ]);

        $usuarioId = (int) $this->db->lastInsertId();

        // Criar vínculo na tabela tenant_usuarios
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
     * Alternar status do usuário no tenant (ativar/desativar)
     */
    public function toggleStatusUsuarioTenant(int $usuarioId, int $tenantId): bool
    {
        // Primeiro verificar o status atual
        $sqlCheck = "
            SELECT status FROM usuario_tenant 
            WHERE usuario_id = :usuario_id AND tenant_id = :tenant_id
        ";
        
        $stmt = $this->db->prepare($sqlCheck);
        $stmt->execute([
            'usuario_id' => $usuarioId,
            'tenant_id' => $tenantId
        ]);
        $vinculo = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$vinculo) {
            return false;
        }
        
        $novoStatus = $vinculo['status'] === 'ativo' ? 'inativo' : 'ativo';
        $dataFim = $novoStatus === 'inativo' ? 'CURRENT_DATE' : 'NULL';
        
        // Atualizar status na tabela usuario_tenant
        $sqlVinculo = "
            UPDATE usuario_tenant 
            SET status = :status, data_fim = $dataFim, updated_at = CURRENT_TIMESTAMP
            WHERE usuario_id = :usuario_id AND tenant_id = :tenant_id
        ";
        
        $stmt = $this->db->prepare($sqlVinculo);
        $stmt->execute([
            'status' => $novoStatus,
            'usuario_id' => $usuarioId,
            'tenant_id' => $tenantId
        ]);

        // Atualizar também na tabela usuarios
        $ativo = $novoStatus === 'ativo' ? 1 : 0;
        $sqlUsuario = "
            UPDATE usuarios 
            SET ativo = :ativo, updated_at = CURRENT_TIMESTAMP
            WHERE id = :usuario_id
        ";
        
        try {
            $stmt = $this->db->prepare($sqlUsuario);
            $stmt->execute([
                'ativo' => $ativo,
                'usuario_id' => $usuarioId
            ]);
        } catch (\PDOException $e) {
            // Se coluna não existir, apenas continuar
            error_log("Aviso: Campo 'ativo' não existe na tabela usuarios.");
        }

        return true;
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

    /**
     * Buscar usuário por CPF (busca global, sem filtro de tenant)
     */
    public function findByCpf(string $cpf): ?array
    {
        $cpfLimpo = preg_replace('/[^0-9]/', '', $cpf);
        
        $sql = "
            SELECT u.id, u.nome, u.email, u.email_global, u.role_id, u.telefone,
                   u.cpf, u.cep, u.logradouro, u.numero, u.complemento, 
                   u.bairro, u.cidade, u.estado, u.ativo,
                   u.created_at, u.updated_at
            FROM usuarios u
            WHERE u.cpf = :cpf
            LIMIT 1
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['cpf' => $cpfLimpo]);
        $user = $stmt->fetch();
        
        return $user ?: null;
    }

    /**
     * Verificar se usuário já está associado a um tenant
     */
    public function isAssociatedWithTenant(int $usuarioId, int $tenantId): bool
    {
        $sql = "
            SELECT COUNT(*) as count
            FROM usuario_tenant
            WHERE usuario_id = :usuario_id
            AND tenant_id = :tenant_id
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'usuario_id' => $usuarioId,
            'tenant_id' => $tenantId
        ]);
        
        $result = $stmt->fetch();
        return $result['count'] > 0;
    }

    /**
     * Associar usuário existente a um tenant
     */
    public function associateToTenant(int $usuarioId, int $tenantId, string $status = 'ativo'): bool
    {
        try {
            // Verificar se já existe associação
            if ($this->isAssociatedWithTenant($usuarioId, $tenantId)) {
                // Atualizar status se já existir
                $sql = "
                    UPDATE usuario_tenant 
                    SET status = :status, data_inicio = CURDATE()
                    WHERE usuario_id = :usuario_id 
                    AND tenant_id = :tenant_id
                ";
            } else {
                // Criar nova associação
                $sql = "
                    INSERT INTO usuario_tenant (usuario_id, tenant_id, status, data_inicio)
                    VALUES (:usuario_id, :tenant_id, :status, CURDATE())
                ";
            }
            
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                'usuario_id' => $usuarioId,
                'tenant_id' => $tenantId,
                'status' => $status
            ]);
        } catch (\PDOException $e) {
            error_log("Erro ao associar usuário ao tenant: " . $e->getMessage());
            return false;
        }
    }
};