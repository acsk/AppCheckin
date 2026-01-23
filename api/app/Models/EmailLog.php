<?php

namespace App\Models;

use PDO;

/**
 * Model para auditoria de emails enviados
 */
class EmailLog
{
    private PDO $db;

    // Tipos de email
    public const TYPE_PASSWORD_RECOVERY = 'password_recovery';
    public const TYPE_WELCOME = 'welcome';
    public const TYPE_NOTIFICATION = 'notification';
    public const TYPE_GENERIC = 'generic';
    
    // Status
    public const STATUS_PENDING = 'pending';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';
    public const STATUS_BOUNCED = 'bounced';

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Registrar um novo email no log
     */
    public function create(array $data): ?int
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO email_logs (
                    tenant_id, usuario_id, to_email, to_name, from_email, from_name,
                    subject, email_type, body_preview, status, error_message,
                    provider, ip_address, user_agent, message_id, sent_at
                ) VALUES (
                    :tenant_id, :usuario_id, :to_email, :to_name, :from_email, :from_name,
                    :subject, :email_type, :body_preview, :status, :error_message,
                    :provider, :ip_address, :user_agent, :message_id, :sent_at
                )
            ");

            // Limitar preview do body a 500 caracteres
            $bodyPreview = isset($data['body']) ? substr(strip_tags($data['body']), 0, 500) : null;

            $stmt->execute([
                'tenant_id' => $data['tenant_id'] ?? null,
                'usuario_id' => $data['usuario_id'] ?? null,
                'to_email' => $data['to_email'],
                'to_name' => $data['to_name'] ?? null,
                'from_email' => $data['from_email'],
                'from_name' => $data['from_name'] ?? null,
                'subject' => $data['subject'],
                'email_type' => $data['email_type'] ?? self::TYPE_GENERIC,
                'body_preview' => $bodyPreview,
                'status' => $data['status'] ?? self::STATUS_PENDING,
                'error_message' => $data['error_message'] ?? null,
                'provider' => $data['provider'] ?? 'ses',
                'ip_address' => $data['ip_address'] ?? $this->getClientIp(),
                'user_agent' => $data['user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? null),
                'message_id' => $data['message_id'] ?? null,
                'sent_at' => $data['sent_at'] ?? null
            ]);

            return (int) $this->db->lastInsertId();
        } catch (\PDOException $e) {
            error_log("Erro ao registrar email log: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Atualizar status de um email
     */
    public function updateStatus(int $id, string $status, ?string $errorMessage = null, ?string $messageId = null): bool
    {
        try {
            $updates = ['status = :status'];
            $params = ['id' => $id, 'status' => $status];

            if ($errorMessage !== null) {
                $updates[] = 'error_message = :error_message';
                $params['error_message'] = $errorMessage;
            }

            if ($messageId !== null) {
                $updates[] = 'message_id = :message_id';
                $params['message_id'] = $messageId;
            }

            if ($status === self::STATUS_SENT) {
                $updates[] = 'sent_at = NOW()';
            }

            $sql = "UPDATE email_logs SET " . implode(', ', $updates) . " WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            
            return $stmt->execute($params);
        } catch (\PDOException $e) {
            error_log("Erro ao atualizar email log: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Buscar email por ID
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM email_logs WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Listar emails por tenant
     */
    public function findByTenant(int $tenantId, int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM email_logs 
            WHERE tenant_id = :tenant_id 
            ORDER BY created_at DESC 
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Listar emails por destinatário
     */
    public function findByEmail(string $email, int $limit = 50): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM email_logs 
            WHERE to_email = :email 
            ORDER BY created_at DESC 
            LIMIT :limit
        ");
        $stmt->bindValue(':email', $email, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Listar emails por usuário
     */
    public function findByUsuario(int $usuarioId, int $limit = 50): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM email_logs 
            WHERE usuario_id = :usuario_id 
            ORDER BY created_at DESC 
            LIMIT :limit
        ");
        $stmt->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Estatísticas de emails por tenant
     */
    public function getStatsByTenant(int $tenantId, ?string $startDate = null, ?string $endDate = null): array
    {
        $where = "WHERE tenant_id = :tenant_id";
        $params = ['tenant_id' => $tenantId];

        if ($startDate) {
            $where .= " AND created_at >= :start_date";
            $params['start_date'] = $startDate;
        }
        if ($endDate) {
            $where .= " AND created_at <= :end_date";
            $params['end_date'] = $endDate;
        }

        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as enviados,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as falhas,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pendentes,
                SUM(CASE WHEN status = 'bounced' THEN 1 ELSE 0 END) as bounced
            FROM email_logs
            {$where}
        ");
        $stmt->execute($params);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Estatísticas por tipo de email
     */
    public function getStatsByType(int $tenantId): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                email_type,
                COUNT(*) as total,
                SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as enviados,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as falhas
            FROM email_logs
            WHERE tenant_id = :tenant_id
            GROUP BY email_type
            ORDER BY total DESC
        ");
        $stmt->execute(['tenant_id' => $tenantId]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obter IP do cliente
     */
    private function getClientIp(): ?string
    {
        $headers = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                // Se houver múltiplos IPs, pegar o primeiro
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                return $ip;
            }
        }
        
        return null;
    }

    /**
     * Limpar logs antigos (para manutenção)
     */
    public function cleanOldLogs(int $daysToKeep = 90): int
    {
        $stmt = $this->db->prepare("
            DELETE FROM email_logs 
            WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)
        ");
        $stmt->execute(['days' => $daysToKeep]);
        
        return $stmt->rowCount();
    }
}
