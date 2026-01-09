<?php

namespace App\Models;

use PDO;

class Turma
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Listar turmas de um tenant
     */
    public function listarPorTenant(int $tenantId, bool $apenasAtivas = false): array
    {
        $sql = "SELECT t.*, 
                p.nome as professor_nome,
                m.nome as modalidade_nome,
                m.icone as modalidade_icone,
                d.data as dia_data,
                h.hora as horario_hora,
                (SELECT COUNT(*) FROM inscricoes_turmas it WHERE it.turma_id = t.id AND it.ativo = 1 AND it.status = 'ativa') as alunos_count
                FROM turmas t
                LEFT JOIN professores p ON t.professor_id = p.id
                LEFT JOIN modalidades m ON t.modalidade_id = m.id
                LEFT JOIN dias d ON t.dia_id = d.id
                LEFT JOIN horarios h ON t.horario_id = h.id
                WHERE t.tenant_id = :tenant_id";
        
        if ($apenasAtivas) {
            $sql .= " AND t.ativo = 1";
        }
        
        $sql .= " ORDER BY d.data ASC, h.hora ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['tenant_id' => $tenantId]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Listar turmas de um dia específico
     */
    public function listarPorDia(int $tenantId, int $diaId, bool $apenasAtivas = true): array
    {
        $sql = "SELECT t.*, 
                p.nome as professor_nome,
                p.id as professor_id,
                m.nome as modalidade_nome,
                m.icone as modalidade_icone,
                d.data as dia_data,
                h.hora as horario_hora,
                (SELECT COUNT(*) FROM inscricoes_turmas it WHERE it.turma_id = t.id AND it.ativo = 1 AND it.status = 'ativa') as alunos_count
                FROM turmas t
                LEFT JOIN professores p ON t.professor_id = p.id
                LEFT JOIN modalidades m ON t.modalidade_id = m.id
                LEFT JOIN dias d ON t.dia_id = d.id
                LEFT JOIN horarios h ON t.horario_id = h.id
                WHERE t.tenant_id = :tenant_id AND t.dia_id = :dia_id";
        
        if ($apenasAtivas) {
            $sql .= " AND t.ativo = 1";
        }
        
        $sql .= " ORDER BY h.hora ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['tenant_id' => $tenantId, 'dia_id' => $diaId]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Buscar turma por ID
     */
    public function findById(int $id, ?int $tenantId = null): ?array
    {
        $sql = "SELECT t.*, 
                p.nome as professor_nome,
                m.nome as modalidade_nome,
                m.icone as modalidade_icone,
                d.data as dia_data,
                h.hora as horario_hora,
                (SELECT COUNT(*) FROM inscricoes_turmas it WHERE it.turma_id = t.id AND it.ativo = 1 AND it.status = 'ativa') as alunos_count
                FROM turmas t
                LEFT JOIN professores p ON t.professor_id = p.id
                LEFT JOIN modalidades m ON t.modalidade_id = m.id
                LEFT JOIN dias d ON t.dia_id = d.id
                LEFT JOIN horarios h ON t.horario_id = h.id
                WHERE t.id = :id";
        
        $params = ['id' => $id];
        
        if ($tenantId) {
            $sql .= " AND t.tenant_id = :tenant_id";
            $params['tenant_id'] = $tenantId;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        $turma = $stmt->fetch(PDO::FETCH_ASSOC);
        return $turma ?: null;
    }

    /**
     * Listar turmas por professor
     */
    public function listarPorProfessor(int $professorId, int $tenantId, bool $apenasAtivas = false): array
    {
        $sql = "SELECT t.*, 
                m.nome as modalidade_nome,
                m.icone as modalidade_icone,
                d.data as dia_data,
                h.hora as horario_hora,
                (SELECT COUNT(*) FROM inscricoes_turmas it WHERE it.turma_id = t.id AND it.ativo = 1 AND it.status = 'ativa') as alunos_count
                FROM turmas t
                LEFT JOIN modalidades m ON t.modalidade_id = m.id
                LEFT JOIN dias d ON t.dia_id = d.id
                LEFT JOIN horarios h ON t.horario_id = h.id
                WHERE t.professor_id = :professor_id AND t.tenant_id = :tenant_id";
        
        if ($apenasAtivas) {
            $sql .= " AND t.ativo = 1";
        }
        
        $sql .= " ORDER BY d.data ASC, h.hora ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['professor_id' => $professorId, 'tenant_id' => $tenantId]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Verificar disponibilidade de vagas
     */
    public function temVagas(int $turmaId): bool
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) as alunos FROM inscricoes_turmas WHERE turma_id = :turma_id AND ativo = 1 AND status = 'ativa'"
        );
        $stmt->execute(['turma_id' => $turmaId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $turma = $this->findById($turmaId);
        if (!$turma) {
            return false;
        }
        
        return $result['alunos'] < $turma['limite_alunos'];
    }

    /**
     * Criar nova turma
     */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO turmas (tenant_id, professor_id, modalidade_id, dia_id, horario_id, nome, limite_alunos, ativo) 
             VALUES (:tenant_id, :professor_id, :modalidade_id, :dia_id, :horario_id, :nome, :limite_alunos, :ativo)"
        );
        
        $stmt->execute([
            'tenant_id' => $data['tenant_id'],
            'professor_id' => $data['professor_id'],
            'modalidade_id' => $data['modalidade_id'],
            'dia_id' => $data['dia_id'],
            'horario_id' => $data['horario_id'],
            'nome' => $data['nome'],
            'limite_alunos' => $data['limite_alunos'] ?? 20,
            'ativo' => $data['ativo'] ?? 1
        ]);
        
        return (int) $this->db->lastInsertId();
    }

    /**
     * Atualizar turma
     */
    public function update(int $id, array $data): bool
    {
        $updates = [];
        $params = ['id' => $id];
        
        $allowed = ['professor_id', 'modalidade_id', 'dia_id', 'horario_id', 'nome', 'limite_alunos', 'ativo'];
        
        foreach ($allowed as $field) {
            if (isset($data[$field])) {
                $updates[] = "$field = :$field";
                $params[$field] = $data[$field];
            }
        }
        
        if (empty($updates)) {
            return true;
        }
        
        $updates[] = "updated_at = CURRENT_TIMESTAMP";
        $sql = "UPDATE turmas SET " . implode(', ', $updates) . " WHERE id = :id";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Deletar turma (soft delete)
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE turmas SET ativo = 0, updated_at = CURRENT_TIMESTAMP WHERE id = :id"
        );
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Verificar se turma pertence ao tenant
     */
    public function pertenceAoTenant(int $turmaId, int $tenantId): bool
    {
        $stmt = $this->db->prepare(
            "SELECT id FROM turmas WHERE id = :id AND tenant_id = :tenant_id"
        );
        $stmt->execute(['id' => $turmaId, 'tenant_id' => $tenantId]);
        
        return (bool) $stmt->fetch();
    }

    /**
     * Contar alunos na turma
     */
    public function contarAlunos(int $turmaId): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) as total FROM inscricoes_turmas WHERE turma_id = :turma_id AND ativo = 1 AND status = 'ativa'"
        );
        $stmt->execute(['turma_id' => $turmaId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return (int) $result['total'];
    }
}
