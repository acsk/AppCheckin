<?php

namespace App\Models;

use PDO;

/**
 * Model para dados de perfil do aluno
 * Separado de Usuario (autenticação) para melhor organização
 */
class Aluno
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Listar alunos de um tenant
     * Busca alunos que têm papel_id=1 no tenant via tenant_usuario_papel
     */
    public function listarPorTenant(int $tenantId, bool $apenasAtivos = false): array
    {
        $sql = "SELECT a.id, a.nome, a.telefone, a.cpf, a.foto_caminho, a.ativo, a.usuario_id,
                       u.email,
                       a.cep, a.logradouro, a.numero, a.complemento, a.bairro, a.cidade, a.estado
                FROM alunos a
                INNER JOIN tenant_usuario_papel tup ON tup.usuario_id = a.usuario_id 
                    AND tup.tenant_id = :tenant_id 
                    AND tup.papel_id = 1
                LEFT JOIN usuarios u ON u.id = a.usuario_id";
        
        if ($apenasAtivos) {
            $sql .= " AND a.ativo = 1 AND tup.ativo = 1";
        }
        
        $sql .= " ORDER BY a.nome ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['tenant_id' => $tenantId]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Buscar aluno por ID
     */
    public function findById(int $id, ?int $tenantId = null): ?array
    {
        if ($tenantId) {
            $sql = "SELECT a.*, u.email
                    FROM alunos a
                    INNER JOIN tenant_usuario_papel tup ON tup.usuario_id = a.usuario_id 
                        AND tup.tenant_id = :tenant_id 
                        AND tup.papel_id = 1
                    LEFT JOIN usuarios u ON u.id = a.usuario_id
                    WHERE a.id = :id";
            $params = ['id' => $id, 'tenant_id' => $tenantId];
        } else {
            $sql = "SELECT a.*, u.email
                    FROM alunos a
                    LEFT JOIN usuarios u ON u.id = a.usuario_id
                    WHERE a.id = :id";
            $params = ['id' => $id];
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Buscar aluno pelo usuario_id
     */
    public function findByUsuarioId(int $usuarioId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT a.*, u.email
             FROM alunos a
             LEFT JOIN usuarios u ON u.id = a.usuario_id
             WHERE a.usuario_id = :usuario_id"
        );
        $stmt->execute(['usuario_id' => $usuarioId]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Buscar aluno pelo usuario_id em um tenant específico
     */
    public function findByUsuarioIdAndTenant(int $usuarioId, int $tenantId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT a.*, u.email
             FROM alunos a
             INNER JOIN tenant_usuario_papel tup ON tup.usuario_id = a.usuario_id 
                AND tup.tenant_id = :tenant_id 
                AND tup.papel_id = 1 
                AND tup.ativo = 1
             LEFT JOIN usuarios u ON u.id = a.usuario_id
             WHERE a.usuario_id = :usuario_id"
        );
        $stmt->execute([
            'usuario_id' => $usuarioId,
            'tenant_id' => $tenantId
        ]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Buscar aluno por email
     */
    public function findByEmail(string $email, ?int $tenantId = null): ?array
    {
        if ($tenantId) {
            $stmt = $this->db->prepare(
                "SELECT a.*, u.email
                 FROM alunos a
                 INNER JOIN usuarios u ON u.id = a.usuario_id
                 INNER JOIN tenant_usuario_papel tup ON tup.usuario_id = a.usuario_id 
                    AND tup.tenant_id = :tenant_id 
                    AND tup.papel_id = 1
                 WHERE u.email = :email"
            );
            $stmt->execute(['email' => $email, 'tenant_id' => $tenantId]);
        } else {
            $stmt = $this->db->prepare(
                "SELECT a.*, u.email
                 FROM alunos a
                 INNER JOIN usuarios u ON u.id = a.usuario_id
                 WHERE u.email = :email"
            );
            $stmt->execute(['email' => $email]);
        }
        
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Buscar aluno por CPF
     */
    public function findByCpf(string $cpf): ?array
    {
        $cpfLimpo = preg_replace('/[^0-9]/', '', $cpf);
        
        $stmt = $this->db->prepare(
            "SELECT a.*, u.email
             FROM alunos a
             LEFT JOIN usuarios u ON u.id = a.usuario_id
             WHERE a.cpf = :cpf"
        );
        $stmt->execute(['cpf' => $cpfLimpo]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Criar novo aluno
     */
    public function create(array $data): int
    {
        // Limpar CPF e CEP
        $cpfLimpo = isset($data['cpf']) ? preg_replace('/[^0-9]/', '', $data['cpf']) : null;
        $cepLimpo = isset($data['cep']) ? preg_replace('/[^0-9]/', '', $data['cep']) : null;
        
        // Converter para maiúsculas
        $nome = isset($data['nome']) ? mb_strtoupper(trim($data['nome']), 'UTF-8') : null;
        $logradouro = isset($data['logradouro']) ? mb_strtoupper(trim($data['logradouro']), 'UTF-8') : null;
        $complemento = isset($data['complemento']) ? mb_strtoupper(trim($data['complemento']), 'UTF-8') : null;
        $bairro = isset($data['bairro']) ? mb_strtoupper(trim($data['bairro']), 'UTF-8') : null;
        $cidade = isset($data['cidade']) ? mb_strtoupper(trim($data['cidade']), 'UTF-8') : null;
        $estado = isset($data['estado']) ? mb_strtoupper(trim($data['estado']), 'UTF-8') : null;
        
        $telefoneLimpo = isset($data['telefone']) ? preg_replace('/[^0-9]/', '', $data['telefone']) : null;
        $whatsappLimpo = isset($data['whatsapp']) ? preg_replace('/[^0-9]/', '', $data['whatsapp']) : null;

        $stmt = $this->db->prepare(
            "INSERT INTO alunos (usuario_id, nome, telefone, cpf, data_nascimento, cep, logradouro, numero, 
                     complemento, bairro, cidade, estado, foto_url, foto_base64, ativo) 
             VALUES (:usuario_id, :nome, :telefone, :cpf, :data_nascimento, :cep, :logradouro, :numero,
                 :complemento, :bairro, :cidade, :estado, :foto_url, :foto_base64, :ativo)"
        );
        
        $stmt->execute([
            'usuario_id' => $data['usuario_id'],
            'nome' => $nome,
            'telefone' => $telefoneLimpo,
            'cpf' => $cpfLimpo ?: null,
            'data_nascimento' => $data['data_nascimento'] ?? null,
            'cep' => $cepLimpo ?: null,
            'logradouro' => $logradouro,
            'numero' => $data['numero'] ?? null,
            'complemento' => $complemento,
            'bairro' => $bairro,
            'cidade' => $cidade,
            'estado' => $estado,
            'foto_url' => $data['foto_url'] ?? null,
            'foto_base64' => $data['foto_base64'] ?? null,
            'ativo' => $data['ativo'] ?? 1
        ]);
        
        return (int) $this->db->lastInsertId();
    }

    /**
     * Atualizar aluno
     */
    public function update(int $id, array $data): bool
    {
        $updates = [];
        $params = ['id' => $id];
        
        $allowed = ['nome', 'telefone', 'whatsapp', 'cpf', 'data_nascimento', 'cep', 'logradouro', 'numero', 
                'complemento', 'bairro', 'cidade', 'estado', 'foto_url', 'foto_base64', 'ativo'];
        
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                // Tratar campos específicos
                if ($field === 'cpf') {
                    $value = $data[$field] ? preg_replace('/[^0-9]/', '', $data[$field]) : null;
                } elseif (in_array($field, ['telefone', 'whatsapp'])) {
                    $value = $data[$field] ? preg_replace('/[^0-9]/', '', $data[$field]) : null;
                } elseif ($field === 'cep') {
                    $value = $data[$field] ? preg_replace('/[^0-9]/', '', $data[$field]) : null;
                } elseif (in_array($field, ['nome', 'logradouro', 'complemento', 'bairro', 'cidade', 'estado'])) {
                    $value = $data[$field] ? mb_strtoupper(trim($data[$field]), 'UTF-8') : null;
                } else {
                    $value = $data[$field];
                }
                
                $updates[] = "$field = :$field";
                $params[$field] = $value;
            }
        }
        
        if (empty($updates)) {
            return true;
        }
        
        $updates[] = "updated_at = CURRENT_TIMESTAMP";
        $sql = "UPDATE alunos SET " . implode(', ', $updates) . " WHERE id = :id";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Atualizar aluno pelo usuario_id
     */
    public function updateByUsuarioId(int $usuarioId, array $data): bool
    {
        $aluno = $this->findByUsuarioId($usuarioId);
        if (!$aluno) {
            return false;
        }
        return $this->update($aluno['id'], $data);
    }

    /**
     * Desativar aluno (soft delete)
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE alunos SET ativo = 0, updated_at = CURRENT_TIMESTAMP WHERE id = :id"
        );
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Deletar aluno completamente (hard delete) com limpeza de registros órfãos
     * Remove: aluno, usuário associado, vínculos com tenant, e todos os dados relacionados
     */
    public function hardDelete(int $id): bool
    {
        try {
            $this->db->beginTransaction();
            
            // 1. Obter o usuario_id associado
            $stmt = $this->db->prepare("SELECT usuario_id FROM alunos WHERE id = :id");
            $stmt->execute(['id' => $id]);
            $aluno = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$aluno) {
                $this->db->rollBack();
                return false;
            }
            
            $usuarioId = $aluno['usuario_id'];
            
            // 2. Deletar registros em cascata (ordem importante para respeitar FKs)
            
            // Deletar checkins do aluno
            $this->db->prepare("DELETE FROM checkins WHERE aluno_id = :aluno_id")
                ->execute(['aluno_id' => $id]);
            
            // Deletar matrículas
            $this->db->prepare("DELETE FROM matriculas WHERE aluno_id = :aluno_id")
                ->execute(['aluno_id' => $id]);
            
            // Deletar pagamentos do aluno
            $this->db->prepare("DELETE FROM pagamentos_plano WHERE aluno_id = :aluno_id")
                ->execute(['aluno_id' => $id]);
            
            // Deletar WOD resultados do aluno
            $this->db->prepare("DELETE FROM wod_resultados WHERE aluno_id = :aluno_id")
                ->execute(['aluno_id' => $id]);
            
            // Deletar vínculo com tenant
            $this->db->prepare("DELETE FROM usuario_tenant WHERE usuario_id = :usuario_id")
                ->execute(['usuario_id' => $usuarioId]);
            
            // Deletar papéis do usuário no tenant
            $this->db->prepare("DELETE FROM tenant_usuario_papel WHERE usuario_id = :usuario_id")
                ->execute(['usuario_id' => $usuarioId]);
            
            // Deletar logs de email
            $this->db->prepare("DELETE FROM email_logs WHERE usuario_id = :usuario_id")
                ->execute(['usuario_id' => $usuarioId]);
            
            // Deletar o aluno
            $this->db->prepare("DELETE FROM alunos WHERE id = :id")
                ->execute(['id' => $id]);
            
            // Deletar o usuário (último, após remover todas as referências)
            $this->db->prepare("DELETE FROM usuarios WHERE id = :id")
                ->execute(['id' => $usuarioId]);
            
            $this->db->commit();
            return true;
            
        } catch (\PDOException $e) {
            $this->db->rollBack();
            error_log("Erro ao fazer hard delete do aluno: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Verificar se aluno pertence ao tenant
     */
    public function pertenceAoTenant(int $alunoId, int $tenantId): bool
    {
        $stmt = $this->db->prepare(
            "SELECT a.id FROM alunos a
             INNER JOIN tenant_usuario_papel tup ON tup.usuario_id = a.usuario_id 
                AND tup.tenant_id = :tenant_id 
                AND tup.papel_id = 1 
                AND tup.ativo = 1
             WHERE a.id = :id"
        );
        $stmt->execute(['id' => $alunoId, 'tenant_id' => $tenantId]);
        
        return (bool) $stmt->fetch();
    }

    /**
     * Verificar se um usuário é aluno no tenant
     */
    public function isUsuarioAluno(int $userId, int $tenantId): bool
    {
        $stmt = $this->db->prepare(
            "SELECT 1 
             FROM tenant_usuario_papel tup
             INNER JOIN alunos a ON a.usuario_id = tup.usuario_id AND a.ativo = 1
             WHERE tup.usuario_id = :user_id 
             AND tup.tenant_id = :tenant_id 
             AND tup.papel_id = 1 
             AND tup.ativo = 1"
        );
        $stmt->execute(['user_id' => $userId, 'tenant_id' => $tenantId]);
        
        return $stmt->fetch() !== false;
    }

    /**
     * Contar alunos ativos no tenant
     */
    public function contarPorTenant(int $tenantId, bool $apenasAtivos = true): int
    {
        $sql = "SELECT COUNT(DISTINCT a.id)
                FROM alunos a
                INNER JOIN tenant_usuario_papel tup ON tup.usuario_id = a.usuario_id 
                    AND tup.tenant_id = :tenant_id 
                    AND tup.papel_id = 1";
        
        if ($apenasAtivos) {
            $sql .= " AND a.ativo = 1 AND tup.ativo = 1";
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['tenant_id' => $tenantId]);
        
        return (int) $stmt->fetchColumn();
    }

    /**
     * Buscar alunos com paginação
     */
    public function listarPaginado(int $tenantId, int $pagina = 1, int $porPagina = 20, ?string $busca = null): array
    {
        $offset = ($pagina - 1) * $porPagina;
        
        $sql = "SELECT a.id, a.nome, a.telefone, a.cpf, a.foto_caminho, a.ativo, a.usuario_id,
                       u.email
                FROM alunos a
                INNER JOIN tenant_usuario_papel tup ON tup.usuario_id = a.usuario_id 
                    AND tup.tenant_id = :tenant_id 
                    AND tup.papel_id = 1
                LEFT JOIN usuarios u ON u.id = a.usuario_id
                WHERE a.ativo = 1 AND tup.ativo = 1";
        
        $params = ['tenant_id' => $tenantId];
        
        if ($busca) {
            $sql .= " AND (a.nome LIKE :busca OR u.email LIKE :busca OR a.cpf LIKE :busca_cpf)";
            $params['busca'] = "%{$busca}%";
            $params['busca_cpf'] = "%{$busca}%";
        }
        
        $sql .= " ORDER BY a.nome ASC LIMIT :limit OFFSET :offset";
        
        $stmt = $this->db->prepare($sql);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue('limit', $porPagina, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
