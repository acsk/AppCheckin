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
                m.cor as modalidade_cor,
                d.data as dia_data,
                (SELECT COUNT(*) FROM inscricoes_turmas it WHERE it.turma_id = t.id AND it.ativo = 1 AND it.status = 'ativa') as alunos_count
                FROM turmas t
                LEFT JOIN professores p ON t.professor_id = p.id
                LEFT JOIN modalidades m ON t.modalidade_id = m.id
                LEFT JOIN dias d ON t.dia_id = d.id
                WHERE t.tenant_id = :tenant_id";
        
        if ($apenasAtivas) {
            $sql .= " AND t.ativo = 1";
        }
        
        $sql .= " ORDER BY d.data ASC, t.horario_inicio ASC";
        
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
                m.cor as modalidade_cor,
                d.data as dia_data,
                (SELECT COUNT(*) FROM inscricoes_turmas it WHERE it.turma_id = t.id AND it.ativo = 1 AND it.status = 'ativa') as alunos_count
                FROM turmas t
                LEFT JOIN professores p ON t.professor_id = p.id
                LEFT JOIN modalidades m ON t.modalidade_id = m.id
                LEFT JOIN dias d ON t.dia_id = d.id
                WHERE t.tenant_id = :tenant_id AND t.dia_id = :dia_id";
        
        if ($apenasAtivas) {
            $sql .= " AND t.ativo = 1";
        }
        
        $sql .= " ORDER BY t.horario_inicio ASC";
        
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
                m.cor as modalidade_cor,
                d.data as dia_data,
                (SELECT COUNT(*) FROM inscricoes_turmas it WHERE it.turma_id = t.id AND it.ativo = 1 AND it.status = 'ativa') as alunos_count
                FROM turmas t
                LEFT JOIN professores p ON t.professor_id = p.id
                LEFT JOIN modalidades m ON t.modalidade_id = m.id
                LEFT JOIN dias d ON t.dia_id = d.id
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
                m.cor as modalidade_cor,
                d.data as dia_data,
                (SELECT COUNT(*) FROM inscricoes_turmas it WHERE it.turma_id = t.id AND it.ativo = 1 AND it.status = 'ativa') as alunos_count
                FROM turmas t
                LEFT JOIN modalidades m ON t.modalidade_id = m.id
                LEFT JOIN dias d ON t.dia_id = d.id
                WHERE t.professor_id = :professor_id AND t.tenant_id = :tenant_id";
        
        if ($apenasAtivas) {
            $sql .= " AND t.ativo = 1";
        }
        
        $sql .= " ORDER BY d.data ASC, t.horario_inicio ASC";
        
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
        // Normalizar horários para formato TIME (HH:MM:SS)
        $horarioInicio = $this->normalizarHorario($data['horario_inicio']);
        $horarioFim = $this->normalizarHorario($data['horario_fim']);
        
        $stmt = $this->db->prepare(
            "INSERT INTO turmas (tenant_id, professor_id, modalidade_id, dia_id, horario_inicio, horario_fim, nome, limite_alunos, tolerancia_minutos, tolerancia_antes_minutos, ativo) 
             VALUES (:tenant_id, :professor_id, :modalidade_id, :dia_id, :horario_inicio, :horario_fim, :nome, :limite_alunos, :tolerancia_minutos, :tolerancia_antes_minutos, :ativo)"
        );
        
        $stmt->execute([
            'tenant_id' => $data['tenant_id'],
            'professor_id' => $data['professor_id'],
            'modalidade_id' => $data['modalidade_id'],
            'dia_id' => $data['dia_id'],
            'horario_inicio' => $horarioInicio,
            'horario_fim' => $horarioFim,
            'nome' => $data['nome'],
            'limite_alunos' => $data['limite_alunos'] ?? 20,
            'tolerancia_minutos' => $data['tolerancia_minutos'] ?? 10,
            'tolerancia_antes_minutos' => $data['tolerancia_antes_minutos'] ?? 480,
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
        
        $allowed = ['professor_id', 'modalidade_id', 'dia_id', 'horario_inicio', 'horario_fim', 'nome', 'limite_alunos', 'tolerancia_minutos', 'tolerancia_antes_minutos', 'ativo'];
        
        foreach ($allowed as $field) {
            if (isset($data[$field])) {
                if ($field === 'horario_inicio' || $field === 'horario_fim') {
                    // Normalizar horários
                    $params[$field] = $this->normalizarHorario($data[$field]);
                } else {
                    $params[$field] = $data[$field];
                }
                $updates[] = "$field = :$field";
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

    /**     * Desativar turma (alias para delete)
     */
    public function desativar(int $id): bool
    {
        return $this->delete($id);
    }

    /**
     * Deletar turma permanentemente (hard delete)
     */
    public function deleteHard(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM turmas WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }

    /**     * Verificar se turma pertence ao tenant
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

    /**
     * Verificar se já existe turma com conflito de horário
     * Detecta sobreposição: nova turma começa antes do fim de uma existente E termina depois do início de uma existente
     * @param int $tenantId ID do tenant
     * @param int $diaId ID do dia
     * @param string $horarioInicio Horário de início (HH:MM ou HH:MM:SS)
     * @param string $horarioFim Horário de término (HH:MM ou HH:MM:SS)
     * @param int|null $turmaIdExcluir ID da turma a excluir (para update)
     * @param int|null $professorId ID do professor (para validar conflito de professor)
     * @return array Turmas com conflito encontradas
     */
    public function verificarHorarioOcupado(int $tenantId, int $diaId, string $horarioInicio, string $horarioFim, ?int $turmaIdExcluir = null, ?int $professorId = null): array
    {
        // Normalizar horários
        $horarioInicio = $this->normalizarHorario($horarioInicio);
        $horarioFim = $this->normalizarHorario($horarioFim);
        
        // Se professor foi informado, buscar conflitos APENAS para este professor
        // pois modalidades diferentes podem ter o mesmo horário sem problema
        $sql = "SELECT t.id, t.nome, t.professor_id, t.horario_inicio, t.horario_fim, t.modalidade_id
                FROM turmas t
                WHERE t.tenant_id = :tenant_id 
                AND t.dia_id = :dia_id 
                AND t.ativo = 1
                AND (t.horario_inicio < :horario_fim AND t.horario_fim > :horario_inicio)";
        
        // Se professor foi informado, validar apenas para este professor
        if ($professorId !== null) {
            $sql .= " AND t.professor_id = :professor_id";
        }
        
        $params = [
            'tenant_id' => $tenantId,
            'dia_id' => $diaId,
            'horario_inicio' => $horarioInicio,
            'horario_fim' => $horarioFim
        ];
        
        if ($professorId !== null) {
            $params['professor_id'] = $professorId;
        }
        
        // Se for update, excluir a turma em questão
        if ($turmaIdExcluir !== null) {
            $sql .= " AND t.id != :turma_id";
            $params['turma_id'] = $turmaIdExcluir;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Normalizar horário para formato TIME (HH:MM:SS)
     * Aceita formatos: "HH:MM" ou "HH:MM:SS"
     * @param string $horario
     * @return string
     */
    private function normalizarHorario(string $horario): string
    {
        // Se não tiver segundos, adicionar :00
        if (strlen($horario) === 5 && substr_count($horario, ':') === 1) {
            $horario .= ':00';
        }
        return $horario;
    }
}
