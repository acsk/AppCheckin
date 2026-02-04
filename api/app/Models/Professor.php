<?php

namespace App\Models;

use PDO;

/**
 * Model Professor
 * 
 * ARQUITETURA SIMPLIFICADA: Usa apenas tenant_usuario_papel
 * 
 * O vínculo professor↔tenant é gerenciado exclusivamente por:
 * 
 * 1. TABELA: professores (Cadastro Global)
 *    - Responsabilidade: Dados básicos do professor (nome, cpf, email, foto, usuario_id)
 *    - Cardinalidade: 1 professor global
 * 
 * 2. TABELA: tenant_usuario_papel (Vínculo + Permissões)
 *    - Responsabilidade: Associação tenant + papel (papel_id=2 para professor)
 *    - Cardinalidade: N vínculos (usuário pode ter múltiplos papéis em múltiplos tenants)
 * 
 * FLUXO DE CADASTRO:
 * 1. Buscar professor existente por CPF (cadastro global)
 * 2. Se não existir, criar em professores
 * 3. Criar vínculo em tenant_usuario_papel (papel_id=2)
 * 
 * VANTAGENS:
 * - Arquitetura mais simples e consistente
 * - Um usuário pode ser professor em um tenant e aluno em outro
 * - Elimina redundância entre tenant_professor e tenant_usuario_papel
 * 
 * DECISÃO ARQUITETURAL (2026-02-03):
 * Usar APENAS tenant_usuario_papel para todos os vínculos usuário↔tenant.
 */
class Professor
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Listar todos os professores de um tenant
     * Usa tenant_usuario_papel com papel_id=2 (professor)
     */
    public function listarPorTenant(int $tenantId, bool $apenasAtivos = false): array
    {
        $sql = "SELECT p.id, p.nome, p.cpf, p.email, p.foto_url, p.ativo, p.usuario_id,
                       u.telefone,
                       tup.ativo as vinculo_ativo,
                       (SELECT COUNT(*) FROM turmas t WHERE t.professor_id = p.id AND t.ativo = 1) as turmas_count
                FROM professores p 
                INNER JOIN tenant_usuario_papel tup ON tup.usuario_id = p.usuario_id
                    AND tup.tenant_id = :tenant_id
                    AND tup.papel_id = 2
                LEFT JOIN usuarios u ON u.id = p.usuario_id";
        
        if ($apenasAtivos) {
            $sql .= " WHERE p.ativo = 1 AND tup.ativo = 1";
        }
        
        $sql .= " ORDER BY p.nome ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['tenant_id' => $tenantId]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Buscar professor por ID
     * Se tenantId for informado, verifica vínculo via tenant_usuario_papel
     */
    public function findById(int $id, ?int $tenantId = null): ?array
    {
        if ($tenantId) {
            $sql = "SELECT p.id, p.nome, p.cpf, p.email, p.foto_url, p.ativo, p.usuario_id, p.created_at, p.updated_at,
                           u.telefone,
                           tup.ativo as vinculo_ativo
                    FROM professores p
                    INNER JOIN tenant_usuario_papel tup ON tup.usuario_id = p.usuario_id
                        AND tup.tenant_id = :tenant_id
                        AND tup.papel_id = 2
                    LEFT JOIN usuarios u ON u.id = p.usuario_id
                    WHERE p.id = :id";
            $params = ['id' => $id, 'tenant_id' => $tenantId];
        } else {
            $sql = "SELECT p.id, p.nome, p.cpf, p.email, p.foto_url, p.ativo, p.usuario_id, p.created_at, p.updated_at,
                           u.telefone
                    FROM professores p
                    LEFT JOIN usuarios u ON u.id = p.usuario_id
                    WHERE p.id = :id";
            $params = ['id' => $id];
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        $professor = $stmt->fetch(PDO::FETCH_ASSOC);
        return $professor ?: null;
    }

    /**
     * Buscar professor por email dentro de um tenant
     * Usa tenant_usuario_papel com papel_id=2
     */
    public function findByEmail(string $email, int $tenantId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT p.id, p.nome, p.cpf, p.email, p.foto_url, p.ativo, p.usuario_id, p.created_at, p.updated_at,
                    u.telefone,
                    tup.ativo as vinculo_ativo
             FROM professores p
             INNER JOIN tenant_usuario_papel tup ON tup.usuario_id = p.usuario_id
                AND tup.tenant_id = :tenant_id
                AND tup.papel_id = 2
             LEFT JOIN usuarios u ON u.id = p.usuario_id
             WHERE p.email = :email"
        );
        $stmt->execute(['email' => $email, 'tenant_id' => $tenantId]);
        
        $professor = $stmt->fetch(PDO::FETCH_ASSOC);
        return $professor ?: null;
    }

    /**
     * Buscar professor por CPF dentro de um tenant
     * Usa tenant_usuario_papel com papel_id=2
     */
    public function findByCpf(string $cpf, int $tenantId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT p.id, p.nome, p.cpf, p.email, p.foto_url, p.ativo, p.usuario_id, p.created_at, p.updated_at,
                    u.telefone,
                    tup.ativo as vinculo_ativo,
                    (SELECT COUNT(*) FROM turmas t WHERE t.professor_id = p.id AND t.ativo = 1) as turmas_count
             FROM professores p
             INNER JOIN tenant_usuario_papel tup ON tup.usuario_id = p.usuario_id
                AND tup.tenant_id = :tenant_id
                AND tup.papel_id = 2
             LEFT JOIN usuarios u ON u.id = p.usuario_id
             WHERE p.cpf = :cpf"
        );
        $stmt->execute(['cpf' => $cpf, 'tenant_id' => $tenantId]);
        
        $professor = $stmt->fetch(PDO::FETCH_ASSOC);
        return $professor ?: null;
    }

    /**
     * Buscar professor por CPF globalmente (sem filtro de tenant)
     * Usado para localizar professor existente antes de associar a tenant
     * ATUALIZADO: Usa campo CPF direto da tabela professores
     */
    public function findByCpfGlobal(string $cpf): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT p.id, p.nome, p.cpf, p.email, p.foto_url, p.ativo, p.usuario_id, p.created_at, p.updated_at,
                    u.telefone
             FROM professores p
             LEFT JOIN usuarios u ON u.id = p.usuario_id
             WHERE p.cpf = :cpf"
        );
        $stmt->execute(['cpf' => $cpf]);
        
        $professor = $stmt->fetch(PDO::FETCH_ASSOC);
        return $professor ?: null;
    }

    /**
     * Buscar professor por EMAIL globalmente (sem filtro de tenant)
     * Usado para localizar professor existente antes de associar a tenant
     * ATUALIZADO: Usa campo EMAIL direto da tabela professores
     */
    public function findByEmailGlobalDirect(string $email): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT p.id, p.nome, p.cpf, p.email, p.foto_url, p.ativo, p.usuario_id, p.created_at, p.updated_at,
                    u.telefone
             FROM professores p
             LEFT JOIN usuarios u ON u.id = p.usuario_id
             WHERE p.email = :email"
        );
        $stmt->execute(['email' => $email]);
        
        $professor = $stmt->fetch(PDO::FETCH_ASSOC);
        return $professor ?: null;
    }

    /**
     * Criar novo professor
     * Campos: usuario_id, nome, cpf, email, foto_url (opcional)
     * CPF e EMAIL são armazenados tanto em professores quanto em usuarios
     */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO professores (usuario_id, nome, cpf, email, foto_url, ativo) 
             VALUES (:usuario_id, :nome, :cpf, :email, :foto_url, :ativo)"
        );
        
        $stmt->execute([
            'usuario_id' => $data['usuario_id'],
            'nome' => $data['nome'],
            'cpf' => $data['cpf'] ?? null,
            'email' => $data['email'] ?? null,
            'foto_url' => $data['foto_url'] ?? null,
            'ativo' => $data['ativo'] ?? 1
        ]);
        
        return (int) $this->db->lastInsertId();
    }
    
    /**
     * Buscar professor pelo usuario_id
     */
    public function findByUsuarioId(int $usuarioId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM professores WHERE usuario_id = :usuario_id"
        );
        $stmt->execute(['usuario_id' => $usuarioId]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    
    /**
     * Buscar professor pelo usuario_id em um tenant específico
     * Usa tenant_usuario_papel com papel_id=2
     */
    public function findByUsuarioIdAndTenant(int $usuarioId, int $tenantId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT p.*, tup.ativo as vinculo_ativo
             FROM professores p
             INNER JOIN tenant_usuario_papel tup ON tup.usuario_id = p.usuario_id
                AND tup.tenant_id = :tenant_id
                AND tup.papel_id = 2
                AND tup.ativo = 1
             WHERE p.usuario_id = :usuario_id"
        );
        $stmt->execute([
            'usuario_id' => $usuarioId,
            'tenant_id' => $tenantId
        ]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Atualizar professor
     * Atualiza dados em professores e usuarios
     */
    public function update(int $id, array $data): bool
    {
        // Buscar professor para obter usuario_id
        $professor = $this->findById($id);
        if (!$professor) {
            return false;
        }
        
        // Campos da tabela professores
        $professorUpdates = [];
        $professorParams = ['id' => $id];
        $professorFields = ['nome', 'cpf', 'email', 'foto_url', 'ativo'];
        
        foreach ($professorFields as $field) {
            if (isset($data[$field])) {
                $professorUpdates[] = "$field = :$field";
                $professorParams[$field] = $data[$field];
            }
        }
        
        // Atualizar tabela professores
        if (!empty($professorUpdates)) {
            $professorUpdates[] = "updated_at = CURRENT_TIMESTAMP";
            $sql = "UPDATE professores SET " . implode(', ', $professorUpdates) . " WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($professorParams);
        }
        
        // Campos da tabela usuarios (email, telefone, cpf, nome)
        $usuarioUpdates = [];
        $usuarioParams = ['usuario_id' => $professor['usuario_id']];
        $usuarioFields = ['email', 'telefone', 'cpf', 'nome'];
        
        foreach ($usuarioFields as $field) {
            if (isset($data[$field])) {
                $usuarioUpdates[] = "$field = :$field";
                $usuarioParams[$field] = $data[$field];
            }
        }
        
        // Atualizar tabela usuarios
        if (!empty($usuarioUpdates)) {
            $usuarioUpdates[] = "updated_at = CURRENT_TIMESTAMP";
            $sql = "UPDATE usuarios SET " . implode(', ', $usuarioUpdates) . " WHERE id = :usuario_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($usuarioParams);
        }
        
        return true;
    }

    /**
     * Deletar professor (soft delete)
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE professores SET ativo = 0, updated_at = CURRENT_TIMESTAMP WHERE id = :id"
        );
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Verificar se professor pertence ao tenant
     * Usa tenant_usuario_papel com papel_id=2
     */
    public function pertenceAoTenant(int $professorId, int $tenantId): bool
    {
        $stmt = $this->db->prepare(
            "SELECT tup.id 
             FROM professores p
             INNER JOIN tenant_usuario_papel tup ON tup.usuario_id = p.usuario_id
                AND tup.tenant_id = :tenant_id
                AND tup.papel_id = 2
                AND tup.ativo = 1
             WHERE p.id = :professor_id"
        );
        $stmt->execute(['professor_id' => $professorId, 'tenant_id' => $tenantId]);
        
        return (bool) $stmt->fetch();
    }

    /**
     * Verificar se um usuário é professor no tenant
     * Usa tenant_usuario_papel com papel_id=2
     * @param int $userId ID do usuário (usuarios.id)
     * @param int $tenantId ID do tenant
     * @return bool
     */
    public function isUsuarioProfessor(int $userId, int $tenantId): bool
    {
        $stmt = $this->db->prepare(
            "SELECT 1 
             FROM tenant_usuario_papel tup
             WHERE tup.usuario_id = :user_id
             AND tup.tenant_id = :tenant_id
             AND tup.papel_id = 2
             AND tup.ativo = 1"
        );
        $stmt->execute(['user_id' => $userId, 'tenant_id' => $tenantId]);
        
        return $stmt->fetch() !== false;
    }

    /**
     * Listar turmas de um professor
     * @param int $professorId ID do professor
     * @param int $tenantId ID do tenant
     * @param bool $apenasAtivas Se true, retorna apenas turmas ativas
     * @return array Lista de turmas
     */
    public function listarTurmas(int $professorId, int $tenantId, bool $apenasAtivas = true): array
    {
        $sql = "SELECT t.*, 
                m.nome as modalidade_nome,
                m.icone as modalidade_icone,
                m.cor as modalidade_cor,
                d.data as dia_data,
                (SELECT COUNT(*) FROM checkins c WHERE c.turma_id = t.id) as total_checkins,
                (SELECT COUNT(*) FROM checkins c WHERE c.turma_id = t.id AND c.presente IS NULL) as checkins_pendentes
                FROM turmas t
                LEFT JOIN modalidades m ON t.modalidade_id = m.id
                LEFT JOIN dias d ON t.dia_id = d.id
                WHERE t.professor_id = :professor_id 
                AND t.tenant_id = :tenant_id";
        
        if ($apenasAtivas) {
            $sql .= " AND t.ativo = 1";
        }
        
        $sql .= " ORDER BY d.data DESC, t.horario_inicio ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['professor_id' => $professorId, 'tenant_id' => $tenantId]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Buscar professor global pelo email (sem verificar tenant)
     */
    public function findByEmailGlobal(string $email): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT p.id, p.nome, p.foto_url, p.ativo, p.usuario_id,
                    u.email, u.telefone, u.cpf
             FROM professores p
             INNER JOIN usuarios u ON u.id = p.usuario_id
             WHERE u.email = :email"
        );
        $stmt->execute(['email' => $email]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Associar professor existente a um tenant
     * Usa APENAS tenant_usuario_papel (papel_id=2)
     * 
     * @param int $professorId ID do professor (tabela professores)
     * @param int $tenantId ID do tenant
     * @param string $status Status do vínculo (default: 'ativo') - compatibilidade (1=ativo, 0=inativo)
     * @param int|null $planoId DEPRECATED - mantido para compatibilidade, não usado
     * @return bool True se associou com sucesso
     */
    public function associarAoTenant(int $professorId, int $tenantId, string $status = 'ativo', ?int $planoId = null): bool
    {
        try {
            // Buscar usuario_id do professor
            $professor = $this->findById($professorId);
            if (!$professor || !$professor['usuario_id']) {
                return false;
            }
            
            $ativo = ($status === 'ativo') ? 1 : 0;
            
            // Verificar se já existe vínculo em tenant_usuario_papel
            $stmt = $this->db->prepare(
                "SELECT id FROM tenant_usuario_papel 
                 WHERE tenant_id = :tenant_id 
                 AND usuario_id = :usuario_id 
                 AND papel_id = 2"
            );
            $stmt->execute([
                'tenant_id' => $tenantId,
                'usuario_id' => $professor['usuario_id']
            ]);
            
            if ($stmt->fetch()) {
                // Já existe vínculo, atualizar status
                $stmt = $this->db->prepare(
                    "UPDATE tenant_usuario_papel 
                     SET ativo = :ativo, updated_at = CURRENT_TIMESTAMP
                     WHERE tenant_id = :tenant_id 
                     AND usuario_id = :usuario_id 
                     AND papel_id = 2"
                );
                return $stmt->execute([
                    'tenant_id' => $tenantId,
                    'usuario_id' => $professor['usuario_id'],
                    'ativo' => $ativo
                ]);
            }
            
            // Criar novo vínculo em tenant_usuario_papel (papel_id=2 para professor)
            $stmt = $this->db->prepare(
                "INSERT INTO tenant_usuario_papel (tenant_id, usuario_id, papel_id, ativo)
                 VALUES (:tenant_id, :usuario_id, 2, :ativo)"
            );
            
            return $stmt->execute([
                'tenant_id' => $tenantId,
                'usuario_id' => $professor['usuario_id'],
                'ativo' => $ativo
            ]);
            
        } catch (\PDOException $e) {
            error_log("Erro ao associar professor ao tenant: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Desassociar professor do tenant (soft delete)
     * Desativa vínculo em tenant_usuario_papel (papel_id=2)
     */
    public function desassociarDoTenant(int $professorId, int $tenantId): bool
    {
        try {
            // Buscar usuario_id do professor
            $professor = $this->findById($professorId);
            if (!$professor || !$professor['usuario_id']) {
                return false;
            }
            
            // Desativar papel em tenant_usuario_papel
            $stmt = $this->db->prepare(
                "UPDATE tenant_usuario_papel 
                 SET ativo = 0, updated_at = CURRENT_TIMESTAMP
                 WHERE tenant_id = :tenant_id 
                 AND usuario_id = :usuario_id 
                 AND papel_id = 2"
            );
            
            return $stmt->execute([
                'tenant_id' => $tenantId,
                'usuario_id' => $professor['usuario_id']
            ]);
            
        } catch (\PDOException $e) {
            error_log("Erro ao desassociar professor do tenant: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Listar todos os tenants onde o professor está vinculado
     * Usa tenant_usuario_papel com papel_id=2
     */
    public function listarTenants(int $professorId, bool $apenasAtivos = true): array
    {
        // Buscar usuario_id do professor
        $professor = $this->findById($professorId);
        if (!$professor || !$professor['usuario_id']) {
            return [];
        }
        
        $sql = "SELECT tup.tenant_id, tup.ativo, t.nome as tenant_nome, t.slug as tenant_slug
                FROM tenant_usuario_papel tup
                INNER JOIN tenants t ON t.id = tup.tenant_id
                WHERE tup.usuario_id = :usuario_id
                AND tup.papel_id = 2";
        
        if ($apenasAtivos) {
            $sql .= " AND tup.ativo = 1";
        }
        
        $sql .= " ORDER BY t.nome ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['usuario_id' => $professor['usuario_id']]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
