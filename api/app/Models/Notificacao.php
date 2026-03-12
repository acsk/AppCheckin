<?php

namespace App\Models;

use PDO;

class Notificacao
{
    private PDO $db;
    private ?string $lastError = null;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function listForUser(int $tenantId, int $usuarioId, int $limit = 200): array
    {
        $stmt = $this->db->prepare("SELECT id, tenant_id, usuario_id, tipo, titulo, mensagem, dados, lida, created_at, updated_at FROM notificacoes WHERE tenant_id = ? AND usuario_id = ? ORDER BY created_at DESC LIMIT ?");
        $stmt->execute([$tenantId, $usuarioId, $limit]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return $rows;
    }

    public function listUnread(int $tenantId, int $usuarioId, int $limit = 200): array
    {
        $stmt = $this->db->prepare("SELECT id, tenant_id, usuario_id, tipo, titulo, mensagem, dados, lida, created_at, updated_at FROM notificacoes WHERE tenant_id = ? AND usuario_id = ? AND lida = 0 ORDER BY created_at DESC LIMIT ?");
        $stmt->execute([$tenantId, $usuarioId, $limit]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return $rows;
    }

    public function find(int $id, int $tenantId, int $usuarioId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM notificacoes WHERE id = ? AND tenant_id = ? AND usuario_id = ? LIMIT 1");
        $stmt->execute([$id, $tenantId, $usuarioId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(array $data): ?int
    {
        try {
            $stmt = $this->db->prepare("INSERT INTO notificacoes (tenant_id, usuario_id, tipo, titulo, mensagem, dados, lida, created_at) VALUES (?, ?, ?, ?, ?, ?, 0, NOW())");
            $dadosJson = isset($data['dados']) ? json_encode($data['dados'], JSON_UNESCAPED_UNICODE) : null;
            $stmt->execute([
                $data['tenant_id'],
                $data['usuario_id'],
                $data['tipo'] ?? 'info',
                $data['titulo'],
                $data['mensagem'] ?? null,
                $dadosJson
            ]);
            return (int)$this->db->lastInsertId();
        } catch (\PDOException $e) {
            $this->lastError = $e->getMessage();
            error_log('[Notificacao@create] ' . $this->lastError);
            return null;
        }
    }

    public function markAsRead(int $id, int $tenantId, int $usuarioId): bool
    {
        $stmt = $this->db->prepare("UPDATE notificacoes SET lida = 1, updated_at = NOW() WHERE id = ? AND tenant_id = ? AND usuario_id = ?");
        $stmt->execute([$id, $tenantId, $usuarioId]);
        return $stmt->rowCount() > 0;
    }

    public function markAllRead(int $tenantId, int $usuarioId): int
    {
        $stmt = $this->db->prepare("UPDATE notificacoes SET lida = 1, updated_at = NOW() WHERE tenant_id = ? AND usuario_id = ? AND lida = 0");
        $stmt->execute([$tenantId, $usuarioId]);
        return $stmt->rowCount();
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }
}
