<?php

namespace App\Models;

use PDO;

/**
 * Model Usuario
 * 
 * ARQUITETURA: Sistema Multi-Tenant com Gestão de Permissões
 * 
 * Este model gerencia usuários e seus relacionamentos com tenants através de:
 * 
 * TABELA: tenant_usuario_papel (Vínculo + Permissões/Roles)
 *    - Responsabilidade: Gerenciar o vínculo user↔tenant e papéis
 *    - Campos: papel_id (1=aluno, 2=professor, 3=admin, 4=superadmin), ativo
 *    - Cardinalidade: N registros por user/tenant (múltiplos papéis)
 * 
 * DECISÃO ARQUITETURAL (2026-02-04):
 * Consolidar em tenant_usuario_papel para evitar redundância e simplificar a estrutura.
 * A tabela usuario_tenant foi renomeada para usuario_tenant_backup e não é mais utilizada.
 */
class Usuario
{
    private PDO $db;
    private ?string $lastError = null;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function create(array $data, ?int $tenantId = null): ?int
    {
        try {
            $this->lastError = null;
            // Limpar CPF e CEP (remover formatação)
            $cpfLimpo = isset($data['cpf']) ? preg_replace('/[^0-9]/', '', $data['cpf']) : null;
            $cepLimpo = isset($data['cep']) ? preg_replace('/[^0-9]/', '', $data['cep']) : null;
            
            // Normalizar email para minúsculo e sem espaços
            $emailNormalizado = isset($data['email']) ? mb_strtolower(trim($data['email']), 'UTF-8') : null;
            $emailGlobalNormalizado = isset($data['email_global']) ? mb_strtolower(trim($data['email_global']), 'UTF-8') : $emailNormalizado;

            // Normalizar telefone/whatsapp (somente números)
            $telefoneLimpo = isset($data['telefone']) ? preg_replace('/[^0-9]/', '', $data['telefone']) : null;
            $whatsappLimpo = isset($data['whatsapp']) ? preg_replace('/[^0-9]/', '', $data['whatsapp']) : null;
            
            // Converter campos de texto para maiúsculas
            $nome = isset($data['nome']) ? mb_strtoupper(trim($data['nome']), 'UTF-8') : null;
            $logradouro = isset($data['logradouro']) ? mb_strtoupper(trim($data['logradouro']), 'UTF-8') : null;
            $complemento = isset($data['complemento']) ? mb_strtoupper(trim($data['complemento']), 'UTF-8') : null;
            $bairro = isset($data['bairro']) ? mb_strtoupper(trim($data['bairro']), 'UTF-8') : null;
            $cidade = isset($data['cidade']) ? mb_strtoupper(trim($data['cidade']), 'UTF-8') : null;
            $estado = isset($data['estado']) ? mb_strtoupper(trim($data['estado']), 'UTF-8') : null;
            if ($estado !== null && $estado !== '') {
                $estado = mb_substr($estado, 0, 2, 'UTF-8');
            }
            
            // 1. Inserir usuário (dados de autenticação)
            // Nota: mantendo campos de perfil em usuarios por compatibilidade, mas também salvando em alunos
            $stmt = $this->db->prepare(
                "INSERT INTO usuarios (nome, email, email_global, senha_hash, cpf, cep, logradouro, numero, complemento, bairro, cidade, estado, telefone, ativo) 
                 VALUES (:nome, :email, :email_global, :senha_hash, :cpf, :cep, :logradouro, :numero, :complemento, :bairro, :cidade, :estado, :telefone, :ativo)"
            );
            
            $stmt->execute([
                'nome' => $nome,
                'email' => $emailNormalizado,
                'email_global' => $emailGlobalNormalizado,
                'senha_hash' => password_hash($data['senha'], PASSWORD_BCRYPT),
                'cpf' => $cpfLimpo ?: null,
                'cep' => $cepLimpo ?: null,
                'logradouro' => $logradouro,
                'numero' => $data['numero'] ?? null,
                'complemento' => $complemento,
                'bairro' => $bairro,
                'cidade' => $cidade,
                'estado' => $estado,
                'telefone' => $telefoneLimpo,
                'ativo' => $data['ativo'] ?? 1
            ]);
            
            $usuarioId = (int) $this->db->lastInsertId();
            
            // 2. Criar registro em alunos APENAS se não for admin ou superadmin
            $papelId = $data['papel_id'] ?? 1;
            $isAdmin = in_array($papelId, [3, 4]); // 3 = admin, 4 = superadmin
            
            if (!$isAdmin) {
                // Criar registro em alunos (perfil separado)
                $stmtAluno = $this->db->prepare(
                    "INSERT INTO alunos (usuario_id, nome, telefone, whatsapp, cpf, data_nascimento, cep, logradouro, numero, complemento, bairro, cidade, estado, ativo)
                     VALUES (:usuario_id, :nome, :telefone, :whatsapp, :cpf, :data_nascimento, :cep, :logradouro, :numero, :complemento, :bairro, :cidade, :estado, :ativo)"
                );
                
                $stmtAluno->execute([
                    'usuario_id' => $usuarioId,
                    'nome' => $nome,
                    'telefone' => $telefoneLimpo,
                    'whatsapp' => $whatsappLimpo,
                    'cpf' => $cpfLimpo ?: null,
                    'data_nascimento' => $data['data_nascimento'] ?? null,
                    'cep' => $cepLimpo ?: null,
                    'logradouro' => $logradouro,
                    'numero' => $data['numero'] ?? null,
                    'complemento' => $complemento,
                    'bairro' => $bairro,
                    'cidade' => $cidade,
                    'estado' => $estado,
                    'ativo' => $data['ativo'] ?? 1
                ]);
            }
            
            // 3. Adicionar papel correto no tenant (se fornecido)
            if ($tenantId !== null) {
                $stmtPapel = $this->db->prepare(
                    "INSERT IGNORE INTO tenant_usuario_papel (tenant_id, usuario_id, papel_id, ativo, created_at, updated_at)
                     VALUES (:tenant_id, :usuario_id, :papel_id, 1, NOW(), NOW())"
                );
                $stmtPapel->execute([
                    'tenant_id' => $tenantId,
                    'usuario_id' => $usuarioId,
                    'papel_id' => $papelId
                ]);
            }
            
            return $usuarioId;
        } catch (\PDOException $e) {
            $this->lastError = $e->getMessage();
            error_log("Erro ao criar usuário: " . $this->lastError);
            return null;
        }
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    public function findByEmail(string $email, ?int $tenantId = null): ?array
    {
        if ($tenantId) {
            // Se tenantId fornecido, verificar se usuário pertence ao tenant
            $sql = "
                SELECT u.* 
                FROM usuarios u
                INNER JOIN tenant_usuario_papel tup ON tup.usuario_id = u.id
                WHERE u.email = :email 
                AND tup.tenant_id = :tenant_id
                AND tup.ativo = 1
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
            SELECT u.id, COALESCE(tup.tenant_id, :fallback_tenant) as tenant_id, tup.ativo, 
                   u.nome, u.email, tup.papel_id, 
                   u.foto_base64, u.foto_caminho, u.telefone,
                   u.cpf, u.cep, u.logradouro, u.numero, u.complemento, u.bairro, u.cidade, u.estado,
                   u.created_at, u.updated_at,
                   p.nome as role_nome, p.descricao as role_descricao
            FROM usuarios u
            {$joinType} tenant_usuario_papel tup ON tup.usuario_id = u.id AND tup.ativo = 1
            LEFT JOIN papeis p ON tup.papel_id = p.id
        ";
        
        $conditions = ["u.id = :id"];
        $params = ['id' => $id, 'fallback_tenant' => $tenantId ?? 0];
        
        if ($tenantId) {
            $conditions[] = "tup.tenant_id = :tenant_id";
            $params['tenant_id'] = $tenantId;
        }
        
        $sql .= " WHERE " . implode(' AND ', $conditions);
        $sql .= " ORDER BY tup.papel_id DESC LIMIT 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$user) {
            return null;
        }
        
        // Estruturar com objeto role se existir
        if ($user['papel_id']) {
            $user['role'] = [
                'id' => $user['papel_id'],
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

        // role_id foi removido - agora usa tenant_usuario_papel

        if (isset($data['telefone'])) {
            $fields[] = 'telefone = :telefone';
            $params['telefone'] = preg_replace('/[^0-9]/', '', $data['telefone']);
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
        $result = $stmt->execute($params);
        
        // Também atualizar na tabela alunos (manter sincronia)
        if ($result) {
            $this->sincronizarAluno($id, $data);
        }
        
        return $result;
    }
    
    /**
     * Sincroniza dados de perfil com a tabela alunos
     */
    private function sincronizarAluno(int $usuarioId, array $data): void
    {
        // Campos de perfil que devem ser sincronizados com alunos
        $camposPerfil = ['nome', 'telefone', 'whatsapp', 'cpf', 'cep', 'logradouro', 'numero', 
                'complemento', 'bairro', 'cidade', 'estado', 'foto_base64'];
        
        $updates = [];
        $params = ['usuario_id' => $usuarioId];
        
        foreach ($camposPerfil as $campo) {
            if (array_key_exists($campo, $data)) {
                $valor = $data[$campo];
                
                // Aplicar mesmas transformações do update
                if ($campo === 'cpf' || $campo === 'cep') {
                    $valor = $valor ? preg_replace('/[^0-9]/', '', $valor) : null;
                } elseif (in_array($campo, ['nome', 'logradouro', 'complemento', 'bairro', 'cidade', 'estado'])) {
                    $valor = $valor ? mb_strtoupper(trim($valor), 'UTF-8') : null;
                }
                
                $updates[] = "$campo = :$campo";
                $params[$campo] = $valor;
            }
        }
        
        if (empty($updates)) {
            return;
        }
        
        $updates[] = "updated_at = CURRENT_TIMESTAMP";
        $sql = "UPDATE alunos SET " . implode(', ', $updates) . " WHERE usuario_id = :usuario_id";
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
        } catch (\PDOException $e) {
            // Log do erro mas não falha a operação principal
            error_log("Erro ao sincronizar aluno: " . $e->getMessage());
        }
    }

    public function emailExists(string $email, ?int $excludeId = null, ?int $tenantId = null): bool
    {
        // Verificar email_global (único no sistema) ou email em combinação com tenant
        if ($tenantId) {
            // Verificar se email já existe no tenant específico
            $sql = "
                SELECT COUNT(*) 
                FROM usuarios u
                INNER JOIN tenant_usuario_papel tup ON tup.usuario_id = u.id
                WHERE u.email = :email 
                AND tup.tenant_id = :tenant_id
                AND tup.ativo = 1
            ";
            $params = ['email' => $email, 'tenant_id' => $tenantId];
            
            if ($excludeId) {
                $sql .= " AND u.id != :id";
                $params['id'] = $excludeId;
            }
        } else {
            // Verificar globalmente tanto email_global quanto email (compatibilidade com dados antigos)
            $sql = "SELECT COUNT(*) FROM usuarios WHERE (email_global = :email OR email = :email2)";
            $params = ['email' => $email, 'email2' => $email];
            
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

        // Contar total de checkins via aluno_id
        $sqlCheckins = "SELECT COUNT(*) as total_checkins FROM checkins c
                        INNER JOIN alunos a ON a.id = c.aluno_id
                        WHERE a.usuario_id = ?";
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
            SELECT DISTINCT
                tup.id as vinculo_id,
                tup.papel_id,
                tup.ativo,
                tup.created_at as data_inicio,
                t.id as tenant_id,
                t.nome as tenant_nome,
                t.slug as tenant_slug,
                t.email as tenant_email,
                t.telefone as tenant_telefone,
                p.nome as papel_nome
            FROM tenant_usuario_papel tup
            INNER JOIN tenants t ON tup.tenant_id = t.id
            LEFT JOIN papeis p ON p.id = tup.papel_id
            WHERE tup.usuario_id = :usuario_id
            AND tup.papel_id IN (1, 2, 3)
            AND tup.ativo = 1
            AND t.ativo = 1
            ORDER BY tup.papel_id DESC, t.nome ASC
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['usuario_id' => $usuarioId]);
        $tenants = $stmt->fetchAll();
        
        // Agrupar por tenant_id (usuário pode ter múltiplos papéis no mesmo tenant)
        $tenantsGrouped = [];
        foreach ($tenants as $row) {
            $tenantId = $row['tenant_id'];
            if (!isset($tenantsGrouped[$tenantId])) {
                $tenantsGrouped[$tenantId] = [
                    'vinculo_id' => $row['vinculo_id'],
                    'ativo' => $row['ativo'],
                    'data_inicio' => $row['data_inicio'],
                    'tenant' => [
                        'id' => $row['tenant_id'],
                        'nome' => $row['tenant_nome'],
                        'slug' => $row['tenant_slug'],
                        'email' => $row['tenant_email'],
                        'telefone' => $row['tenant_telefone']
                    ],
                    'papeis' => [],
                    'plano' => null // Não retornamos mais plano aqui (só para alunos via endpoint específico)
                ];
            }
            // Adicionar papel ao array de papéis
            $tenantsGrouped[$tenantId]['papeis'][] = [
                'id' => (int) $row['papel_id'],
                'nome' => $row['papel_nome']
            ];
        }
        
        return array_values($tenantsGrouped);
    }

    /**
     * Criar vínculo entre usuário e tenant (como aluno)
     */
    public function vincularTenant(int $usuarioId, int $tenantId, ?int $planoId = null): bool
    {
        $sql = "
            INSERT INTO tenant_usuario_papel (usuario_id, tenant_id, papel_id, ativo, created_at, updated_at)
            VALUES (:usuario_id, :tenant_id, 1, 1, NOW(), NOW())
            ON DUPLICATE KEY UPDATE ativo = 1, updated_at = NOW()
        ";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'usuario_id' => $usuarioId,
            'tenant_id' => $tenantId
        ]);
    }

    /**
     * Verificar se usuário tem acesso a um tenant específico
     */
    public function temAcessoTenant(int $usuarioId, int $tenantId): bool
    {
        $sql = "
            SELECT COUNT(*) 
            FROM tenant_usuario_papel 
            WHERE usuario_id = :usuario_id 
            AND tenant_id = :tenant_id 
            AND ativo = 1
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
     * Buscar usuário por CPF global (independente de tenant)
     * Usado para verificar se CPF já está cadastrado no sistema
     */
    public function findByCpfGlobal(string $cpf): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM usuarios WHERE cpf = :cpf LIMIT 1"
        );
        $stmt->execute(['cpf' => $cpf]);
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
                tup.papel_id,
                u.foto_base64,
                u.created_at,
                u.updated_at,
                p_tenant.nome as papel_nome,
                tup.ativo
            FROM usuarios u
            INNER JOIN tenant_usuario_papel tup ON tup.usuario_id = u.id AND tup.ativo = 1
            LEFT JOIN papeis p_tenant ON tup.papel_id = p_tenant.id
        ";
        
        $conditions = [];
        $params = [];
        
        // Se NÃO for SuperAdmin, filtrar por tenant
        if (!$isSuperAdmin && $tenantId !== null) {
            $conditions[] = "tup.tenant_id = :tenant_id";
            $params['tenant_id'] = $tenantId;
        }
        
        // Filtrar apenas ativos se solicitado
        if ($apenasAtivos) {
            $conditions[] = "tup.ativo = 1";
        }
        
        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }
        
        // Ordenar para evitar duplicatas quando usuário está em múltiplos tenants
        $sql .= " ORDER BY u.id ASC, tup.ativo DESC";

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
                    'papel_id' => $row['papel_id'],
                    'papel_nome' => $row['papel_nome'],
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
                tup.papel_id,
                u.foto_base64,
                u.created_at,
                u.updated_at,
                p_tenant.nome as papel_nome,
                tup.ativo
            FROM usuarios u
            INNER JOIN tenant_usuario_papel tup ON tup.usuario_id = u.id AND tup.ativo = 1
            LEFT JOIN papeis p_tenant ON tup.papel_id = p_tenant.id
            WHERE tup.tenant_id = :tenant_id
        ";

        $params = ['tenant_id' => $tenantId];

        if ($apenasAtivos) {
            $sql .= " AND tup.ativo = 1";
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
                'papel_id' => $row['papel_id'],
                'papel_nome' => $row['papel_nome'],
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
            (nome, email, senha_hash, telefone, cpf) 
            VALUES 
            (:nome, :email, :senha_hash, :telefone, :cpf)
        ";
        
        $stmt = $this->db->prepare($sql);
        
        $stmt->execute([
            'nome' => $nome,
            'email' => $data['email'],
            'senha_hash' => password_hash($data['senha'], PASSWORD_BCRYPT),
            'telefone' => $data['telefone'] ?? null,
            'cpf' => $cpfLimpo ?: null,
        ]);

        $usuarioId = (int) $this->db->lastInsertId();

        // Criar papel do usuário em tenant_usuario_papel
        if ($usuarioId) {
            try {
                $papelId = $data['papel_id'] ?? 1; // Default: Aluno
                $sqlPapel = "
                    INSERT INTO tenant_usuario_papel (tenant_id, usuario_id, papel_id, ativo)
                    VALUES (:tenant_id, :usuario_id, :papel_id, 1)
                    ON DUPLICATE KEY UPDATE papel_id = :papel_id2, ativo = 1
                ";
                $stmtPapel = $this->db->prepare($sqlPapel);
                $stmtPapel->execute([
                    'tenant_id' => $tenantId,
                    'usuario_id' => $usuarioId,
                    'papel_id' => $papelId,
                    'papel_id2' => $papelId
                ]);
            } catch (\PDOException $e) {
                // Se falhar, não é crítico
                error_log("Erro ao criar papel do usuário: " . $e->getMessage());
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
            SELECT ativo FROM tenant_usuario_papel 
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
        
        $novoAtivo = $vinculo['ativo'] == 1 ? 0 : 1;
        
        // Atualizar status na tabela tenant_usuario_papel
        $sqlVinculo = "
            UPDATE tenant_usuario_papel 
            SET ativo = :ativo, updated_at = NOW()
            WHERE usuario_id = :usuario_id AND tenant_id = :tenant_id
        ";
        
        $stmt = $this->db->prepare($sqlVinculo);
        $stmt->execute([
            'ativo' => $novoAtivo,
            'usuario_id' => $usuarioId,
            'tenant_id' => $tenantId
        ]);

        // Atualizar também na tabela usuarios
        $sqlUsuario = "
            UPDATE usuarios 
            SET ativo = :ativo, updated_at = NOW()
            WHERE id = :usuario_id
        ";
        
        try {
            $stmt = $this->db->prepare($sqlUsuario);
            $stmt->execute([
                'ativo' => $novoAtivo,
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
        // Atualizar status na tabela tenant_usuario_papel
        $sqlVinculo = "
            UPDATE tenant_usuario_papel 
            SET ativo = 0, updated_at = NOW()
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
            SELECT u.id, u.nome, u.email, u.email_global, u.telefone,
                   u.cpf, u.cep, u.logradouro, u.numero, u.complemento, 
                   u.bairro, u.cidade, u.estado, u.ativo,
                   u.created_at, u.updated_at,
                   tup.papel_id
            FROM usuarios u
            LEFT JOIN tenant_usuario_papel tup ON tup.usuario_id = u.id AND tup.ativo = 1
            WHERE u.cpf = :cpf
            ORDER BY tup.papel_id DESC
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
            FROM tenant_usuario_papel
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
     * Associar usuário existente a um tenant (como aluno)
     */
    public function associateToTenant(int $usuarioId, int $tenantId, string $status = 'ativo'): bool
    {
        try {
            $ativo = $status === 'ativo' ? 1 : 0;
            
            // Verificar se já existe associação
            if ($this->isAssociatedWithTenant($usuarioId, $tenantId)) {
                // Atualizar status se já existir
                $sql = "
                    UPDATE tenant_usuario_papel 
                    SET ativo = :ativo, updated_at = NOW()
                    WHERE usuario_id = :usuario_id 
                    AND tenant_id = :tenant_id
                ";
            } else {
                // Criar nova associação (papel_id = 1 para aluno)
                $sql = "
                    INSERT INTO tenant_usuario_papel (usuario_id, tenant_id, papel_id, ativo, created_at, updated_at)
                    VALUES (:usuario_id, :tenant_id, 1, :ativo, NOW(), NOW())
                ";
            }
            
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                'usuario_id' => $usuarioId,
                'tenant_id' => $tenantId,
                'ativo' => $ativo
            ]);
        } catch (\PDOException $e) {
            error_log("Erro ao associar usuário ao tenant: " . $e->getMessage());
            return false;
        }
    }
};