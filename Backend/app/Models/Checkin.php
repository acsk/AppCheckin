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
                "INSERT INTO checkins (usuario_id, horario_id) VALUES (:usuario_id, :horario_id)"
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
            "SELECT c.*, h.hora, d.data
             FROM checkins c
             INNER JOIN horarios h ON c.horario_id = h.id
             INNER JOIN dias d ON h.dia_id = d.id
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
}
