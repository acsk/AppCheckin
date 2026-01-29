<?php

namespace App\Models;

use PDO;

class Professor
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Listar todos os professores de um tenant
     * Busca professores que têm papel_id=2 no tenant via tenant_usuario_papel
     */
    public function listarPorTenant(int $tenantId, bool $apenasAtivos = false): array
    {
        $sql = "SELECT p.id, p.nome, p.foto_url, p.ativo, p.usuario_id,
                       u.email, u.telefone, u.cpf,
                       (SELECT COUNT(*) FROM turmas t WHERE t.professor_id = p.id AND t.ativo = 1) as turmas_count
                FROM professores p 
                INNER JOIN tenant_usuario_papel tup ON tup.usuario_id = p.usuario_id 
                    AND tup.tenant_id = :tenant_id 
                    AND tup.papel_id = 2
                LEFT JOIN usuarios u ON u.id = p.usuario_id";
        
        if ($apenasAtivos) {
            $sql .= " AND p.ativo = 1 AND tup.ativo = 1";
        }
        
        $sql .= " ORDER BY p.nome ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['tenant_id' => $tenantId]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Buscar professor por ID
     * Se tenantId for informado, verifica se tem papel_id=2 nesse tenant
     */
    public function findById(int $id, ?int $tenantId = null): ?array
    {
        if ($tenantId) {
            $sql = "SELECT p.id, p.nome, p.foto_url, p.ativo, p.usuario_id, p.created_at, p.updated_at,
                           u.email, u.telefone, u.cpf
                    FROM professores p
                    INNER JOIN tenant_usuario_papel tup ON tup.usuario_id = p.usuario_id 
                        AND tup.tenant_id = :tenant_id 
                        AND tup.papel_id = 2
                    LEFT JOIN usuarios u ON u.id = p.usuario_id
                    WHERE p.id = :id";
            $params = ['id' => $id, 'tenant_id' => $tenantId];
        } else {
            $sql = "SELECT p.id, p.nome, p.foto_url, p.ativo, p.usuario_id, p.created_at, p.updated_at,
                           u.email, u.telefone, u.cpf
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
     * O email agora está em usuarios, não em professores
     */
    public function findByEmail(string $email, int $tenantId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT p.id, p.nome, p.foto_url, p.ativo, p.usuario_id, p.created_at, p.updated_at,
                    u.email, u.telefone, u.cpf
             FROM professores p
             INNER JOIN usuarios u ON u.id = p.usuario_id
             INNER JOIN tenant_usuario_papel tup ON tup.usuario_id = p.usuario_id 
                AND tup.tenant_id = :tenant_id 
                AND tup.papel_id = 2
             WHERE u.email = :email"
        );
        $stmt->execute(['email' => $email, 'tenant_id' => $tenantId]);
        
        $professor = $stmt->fetch(PDO::FETCH_ASSOC);
        return $professor ?: null;
    }

    /**
     * Criar novo professor
     * Agora só precisa: usuario_id, nome, foto_url (opcional)
     * email, telefone, cpf ficam em usuarios
     */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO professores (usuario_id, nome, foto_url, ativo) 
             VALUES (:usuario_id, :nome, :foto_url, :ativo)"
        );
        
        $stmt->execute([
            'usuario_id' => $data['usuario_id'],
            'nome' => $data['nome'],
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
     * (via tenant_usuario_papel)
     */
    public function findByUsuarioIdAndTenant(int $usuarioId, int $tenantId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT p.* 
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
        $professorFields = ['nome', 'foto_url', 'ativo'];
        
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
     * Usa tenant_usuario_papel para verificar papel_id=2 (professor)
     */
    public function pertenceAoTenant(int $professorId, int $tenantId): bool
    {
        $stmt = $this->db->prepare(
            "SELECT p.id FROM professores p
             INNER JOIN tenant_usuario_papel tup ON tup.usuario_id = p.usuario_id 
                AND tup.tenant_id = :tenant_id 
                AND tup.papel_id = 2 
                AND tup.ativo = 1
             WHERE p.id = :id"
        );
        $stmt->execute(['id' => $professorId, 'tenant_id' => $tenantId]);
        
        return (bool) $stmt->fetch();
    }

    /**
     * Verificar se um usuário é professor no tenant
     * Usa tenant_usuario_papel para verificar papel_id=2 (professor)
     * @param int $userId ID do usuário
     * @param int $tenantId ID do tenant
     * @return bool
     */
    public function isUsuarioProfessor(int $userId, int $tenantId): bool
    {
        $stmt = $this->db->prepare(
            "SELECT 1 
             FROM tenant_usuario_papel tup
             INNER JOIN professores p ON p.usuario_id = tup.usuario_id AND p.ativo = 1
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
}
