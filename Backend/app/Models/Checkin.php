<?php

namespace App\Models;

use PDO;

class Checkin
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function create(int $usuarioId, int $horarioId): ?int
    {
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO checkins (usuario_id, horario_id, registrado_por_admin) 
                 VALUES (:usuario_id, :horario_id, 0)"
            );
            
            $stmt->execute([
                'usuario_id' => $usuarioId,
                'horario_id' => $horarioId
            ]);

            return (int) $this->db->lastInsertId();
        } catch (\PDOException $e) {
            // Viola constraint de unique (usuário já tem check-in nesse horário)
            if ($e->getCode() == 23000) {
                return null;
            }
            throw $e;
        }
    }

    public function createByAdmin(int $usuarioId, int $horarioId, int $adminId): ?int
    {
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO checkins (usuario_id, horario_id, registrado_por_admin, admin_id) 
                 VALUES (:usuario_id, :horario_id, 1, :admin_id)"
            );
            
            $stmt->execute([
                'usuario_id' => $usuarioId,
                'horario_id' => $horarioId,
                'admin_id' => $adminId
            ]);

            return (int) $this->db->lastInsertId();
        } catch (\PDOException $e) {
            // Viola constraint de unique (usuário já tem check-in nesse horário)
            if ($e->getCode() == 23000) {
                return null;
            }
            throw $e;
        }
    }

    public function getByUsuarioId(int $usuarioId): array
    {
        $stmt = $this->db->prepare(
            "SELECT c.*, 
                    h.hora, 
                    d.data,
                    CONCAT(d.data, ' ', h.hora) as data_hora_completa
             FROM checkins c
             INNER JOIN horarios h ON c.horario_id = h.id
             INNER JOIN dias d ON h.dia_id = d.id
             WHERE c.usuario_id = :usuario_id
             ORDER BY d.data DESC, h.hora DESC"
        );
        $stmt->execute(['usuario_id' => $usuarioId]);
        
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT 
                c.*, 
                h.hora, 
                h.horario_inicio,
                h.horario_fim,
                h.tolerancia_minutos,
                d.data,
                COALESCE(d.data, DATE(c.created_at)) as data_aula
             FROM checkins c
             LEFT JOIN horarios h ON c.horario_id = h.id
             LEFT JOIN dias d ON h.dia_id = d.id
             WHERE c.id = :id"
        );
        $stmt->execute(['id' => $id]);
        $checkin = $stmt->fetch();
        
        return $checkin ?: null;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM checkins WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }

    public function usuarioTemCheckin(int $usuarioId, int $horarioId): bool
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM checkins WHERE usuario_id = :usuario_id AND horario_id = :horario_id"
        );
        $stmt->execute([
            'usuario_id' => $usuarioId,
            'horario_id' => $horarioId
        ]);
        
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Criar check-in em turma (novo método para mobile app)
     */
    public function createEmTurma(int $usuarioId, int $turmaId): ?int
    {
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO checkins (usuario_id, turma_id, registrado_por_admin) 
                 VALUES (:usuario_id, :turma_id, 0)"
            );
            
            $stmt->execute([
                'usuario_id' => $usuarioId,
                'turma_id' => $turmaId
            ]);

            return (int) $this->db->lastInsertId();
        } catch (\PDOException $e) {
            // Viola constraint de unique (usuário já tem check-in nessa turma)
            if ($e->getCode() == 23000) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * Verificar se usuário já tem check-in em uma turma específica
     */
    public function usuarioTemCheckinNaTurma(int $usuarioId, int $turmaId): bool
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM checkins WHERE usuario_id = :usuario_id AND turma_id = :turma_id"
        );
        $stmt->execute([
            'usuario_id' => $usuarioId,
            'turma_id' => $turmaId
        ]);
        
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Verificar se usuário já tem check-in no mesmo dia (diferente turmas, mesma data)
     */
    public function usuarioTemCheckinNoDia(int $usuarioId, string $data): array
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) as total, MAX(c.id) as ultimo_checkin_id
             FROM checkins c
             INNER JOIN turmas t ON c.turma_id = t.id
             INNER JOIN dias d ON t.dia_id = d.id
             WHERE c.usuario_id = :usuario_id
             AND DATE(d.data) = :data"
        );
        $stmt->execute([
            'usuario_id' => $usuarioId,
            'data' => $data
        ]);
        
        $result = $stmt->fetch();
        return [
            'total' => (int) ($result['total'] ?? 0),
            'ultimo_checkin_id' => $result['ultimo_checkin_id'] ? (int) $result['ultimo_checkin_id'] : null
        ];
    }

    /**
     * Contar check-ins do usuário na semana atual
     */
    public function contarCheckinsNaSemana(int $usuarioId): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM checkins
             WHERE usuario_id = :usuario_id
             AND YEARWEEK(created_at, 1) = YEARWEEK(NOW(), 1)"
        );
        $stmt->execute(['usuario_id' => $usuarioId]);
        
        return (int) $stmt->fetchColumn();
    }

    /**
     * Obter limite de check-ins do plano do usuário
     */
    public function obterLimiteCheckinsPlano(int $usuarioId, int $tenantId): array
    {
        $stmt = $this->db->prepare(
            "SELECT p.checkins_semanais, p.nome as plano_nome
             FROM matriculas m
             INNER JOIN planos p ON m.plano_id = p.id
             WHERE m.usuario_id = :usuario_id
             AND m.tenant_id = :tenant_id
             AND m.status = 'ativa'
             AND m.data_inicio <= CURDATE()
             AND m.data_vencimento >= CURDATE()
             LIMIT 1"
        );
        $stmt->execute([
            'usuario_id' => $usuarioId,
            'tenant_id' => $tenantId
        ]);
        
        $result = $stmt->fetch();
        return [
            'limite' => $result ? (int) $result['checkins_semanais'] : 0,
            'plano_nome' => $result ? $result['plano_nome'] : 'Sem plano',
            'tem_plano' => $result !== false
        ];
    }
}
