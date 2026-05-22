<?php

namespace App\Services;

use PDO;

/**
 * Bloqueio de check-in de alunos em uma turma (aula em data específica).
 */
class TurmaCheckinBloqueioService
{
    public function __construct(private PDO $db)
    {
    }

    public function isBloqueada(int $turmaId, int $tenantId): bool
    {
        $stmt = $this->db->prepare("
            SELECT 1 FROM turma_checkin_bloqueios
            WHERE turma_id = :turma_id AND tenant_id = :tenant_id
            LIMIT 1
        ");
        $stmt->execute(['turma_id' => $turmaId, 'tenant_id' => $tenantId]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * @param list<int> $turmaIds
     * @return array<int, true> turma_id => true
     */
    public function listarTurmaIdsBloqueadas(int $tenantId, array $turmaIds): array
    {
        $turmaIds = array_values(array_unique(array_filter(array_map('intval', $turmaIds))));
        if ($turmaIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($turmaIds), '?'));
        $sql = "
            SELECT turma_id FROM turma_checkin_bloqueios
            WHERE tenant_id = ? AND turma_id IN ({$placeholders})
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_merge([$tenantId], $turmaIds));

        $map = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $map[(int) $row['turma_id']] = true;
        }
        return $map;
    }

    /**
     * @param list<array<string, mixed>> $turmas
     * @return list<array<string, mixed>>
     */
    public function anexarFlagNasTurmas(array $turmas, int $tenantId): array
    {
        if ($turmas === []) {
            return [];
        }

        $ids = array_map(static fn (array $t) => (int) ($t['id'] ?? 0), $turmas);
        $bloqueadas = $this->listarTurmaIdsBloqueadas($tenantId, $ids);

        return array_map(static function (array $turma) use ($bloqueadas) {
            $id = (int) ($turma['id'] ?? 0);
            $turma['checkin_bloqueado'] = isset($bloqueadas[$id]);
            return $turma;
        }, $turmas);
    }

    public function bloquear(int $turmaId, int $tenantId, ?int $usuarioId, ?string $motivo = null): void
    {
        if ($usuarioId !== null && $usuarioId <= 0) {
            $usuarioId = null;
        }

        $motivo = $motivo !== null ? trim($motivo) : null;
        if ($motivo === '') {
            $motivo = null;
        }

        $stmt = $this->db->prepare("
            INSERT INTO turma_checkin_bloqueios (tenant_id, turma_id, bloqueado_por_usuario_id, motivo)
            VALUES (:tenant_id, :turma_id, :usuario_id, :motivo)
            ON DUPLICATE KEY UPDATE
                bloqueado_por_usuario_id = VALUES(bloqueado_por_usuario_id),
                motivo = VALUES(motivo),
                updated_at = NOW()
        ");
        $stmt->execute([
            'tenant_id' => $tenantId,
            'turma_id' => $turmaId,
            'usuario_id' => $usuarioId,
            'motivo' => $motivo,
        ]);
    }

    public function desbloquear(int $turmaId, int $tenantId): bool
    {
        $stmt = $this->db->prepare("
            DELETE FROM turma_checkin_bloqueios
            WHERE turma_id = :turma_id AND tenant_id = :tenant_id
        ");
        $stmt->execute(['turma_id' => $turmaId, 'tenant_id' => $tenantId]);
        return $stmt->rowCount() > 0;
    }

    public function usuarioEhStaffNoTenant(int $usuarioId, int $tenantId): bool
    {
        $stmt = $this->db->prepare("
            SELECT 1 FROM tenant_usuario_papel
            WHERE usuario_id = :usuario_id
              AND tenant_id = :tenant_id
              AND ativo = 1
              AND papel_id IN (2, 3, 4)
            LIMIT 1
        ");
        $stmt->execute(['usuario_id' => $usuarioId, 'tenant_id' => $tenantId]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Admin/super admin: qualquer turma. Professor: apenas turmas em que é o professor vinculado.
     */
    public function usuarioPodeGerenciarTurma(int $usuarioId, int $tenantId, int $turmaId, ?array $papelRequest): bool
    {
        if ($papelRequest === null) {
            $stmt = $this->db->prepare("
                SELECT MAX(papel_id) FROM tenant_usuario_papel
                WHERE usuario_id = :usuario_id AND tenant_id = :tenant_id AND ativo = 1
            ");
            $stmt->execute(['usuario_id' => $usuarioId, 'tenant_id' => $tenantId]);
            $papelId = (int) ($stmt->fetchColumn() ?: 0);
        } else {
            $papelId = (int) ($papelRequest['id'] ?? 0);
        }

        if ($papelId >= 3) {
            return true;
        }

        if ($papelId !== 2) {
            return false;
        }

        $stmt = $this->db->prepare("
            SELECT 1
            FROM turmas t
            INNER JOIN professores pr ON pr.id = t.professor_id
            WHERE t.id = :turma_id
              AND t.tenant_id = :tenant_id
              AND pr.usuario_id = :usuario_id
            LIMIT 1
        ");
        $stmt->execute([
            'turma_id' => $turmaId,
            'tenant_id' => $tenantId,
            'usuario_id' => $usuarioId,
        ]);

        return (bool) $stmt->fetchColumn();
    }
}
