<?php

namespace App\Services;

use PDO;

/**
 * Rate Limiter baseado em IP
 * Armazena tentativas em banco de dados para persistência
 */
class RateLimiter
{
    private PDO $db;
    private int $maxAttempts;
    private int $decayMinutes;
    private string $tableName = 'rate_limits';

    /**
     * @param PDO $db Conexão com banco de dados
     * @param int $maxAttempts Número máximo de tentativas permitidas
     * @param int $decayMinutes Tempo em minutos para resetar o contador
     */
    public function __construct(PDO $db, int $maxAttempts = 5, int $decayMinutes = 15)
    {
        $this->db = $db;
        $this->maxAttempts = $maxAttempts;
        $this->decayMinutes = $decayMinutes;
        $this->ensureTableExists();
    }

    /**
     * Cria a tabela se não existir
     */
    private function ensureTableExists(): void
    {
        try {
            $sql = "CREATE TABLE IF NOT EXISTS {$this->tableName} (
                id INT AUTO_INCREMENT PRIMARY KEY,
                ip VARCHAR(45) NOT NULL,
                action VARCHAR(100) NOT NULL,
                attempts INT DEFAULT 1,
                expires_at DATETIME NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_ip_action (ip, action),
                INDEX idx_expires (expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            $this->db->exec($sql);
        } catch (\PDOException $e) {
            error_log("Erro ao criar tabela rate_limits: " . $e->getMessage());
        }
    }

    /**
     * Verifica se o IP excedeu o limite de tentativas
     * 
     * @param string $ip Endereço IP
     * @param string $action Ação/endpoint sendo limitado (ex: 'register-mobile')
     * @return array ['allowed' => bool, 'remaining' => int, 'retryAfter' => int|null]
     */
    public function attempt(string $ip, string $action = 'default'): array
    {
        $this->cleanup(); // Limpar registros expirados

        $stmt = $this->db->prepare(
            "SELECT attempts, expires_at FROM {$this->tableName} 
             WHERE ip = :ip AND action = :action AND expires_at > NOW()
             LIMIT 1"
        );
        $stmt->execute(['ip' => $ip, 'action' => $action]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$record) {
            // Primeira tentativa - criar registro
            $expiresAt = date('Y-m-d H:i:s', time() + ($this->decayMinutes * 60));
            $stmt = $this->db->prepare(
                "INSERT INTO {$this->tableName} (ip, action, attempts, expires_at) 
                 VALUES (:ip, :action, 1, :expires_at)"
            );
            $stmt->execute([
                'ip' => $ip,
                'action' => $action,
                'expires_at' => $expiresAt
            ]);

            return [
                'allowed' => true,
                'remaining' => $this->maxAttempts - 1,
                'retryAfter' => null
            ];
        }

        $attempts = (int)$record['attempts'];
        $expiresAt = strtotime($record['expires_at']);
        $remaining = max(0, $this->maxAttempts - $attempts);

        if ($attempts >= $this->maxAttempts) {
            $retryAfter = max(0, $expiresAt - time());
            return [
                'allowed' => false,
                'remaining' => 0,
                'retryAfter' => $retryAfter
            ];
        }

        // Incrementar contador
        $stmt = $this->db->prepare(
            "UPDATE {$this->tableName} 
             SET attempts = attempts + 1, updated_at = CURRENT_TIMESTAMP 
             WHERE ip = :ip AND action = :action AND expires_at > NOW()"
        );
        $stmt->execute(['ip' => $ip, 'action' => $action]);

        return [
            'allowed' => true,
            'remaining' => $remaining - 1,
            'retryAfter' => null
        ];
    }

    /**
     * Verifica se o IP pode fazer a ação (sem incrementar contador)
     */
    public function check(string $ip, string $action = 'default'): bool
    {
        $stmt = $this->db->prepare(
            "SELECT attempts FROM {$this->tableName} 
             WHERE ip = :ip AND action = :action AND expires_at > NOW()
             LIMIT 1"
        );
        $stmt->execute(['ip' => $ip, 'action' => $action]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$record) {
            return true; // Sem registro = permitido
        }

        return (int)$record['attempts'] < $this->maxAttempts;
    }

    /**
     * Reseta o contador para um IP específico
     */
    public function reset(string $ip, string $action = 'default'): bool
    {
        $stmt = $this->db->prepare(
            "DELETE FROM {$this->tableName} WHERE ip = :ip AND action = :action"
        );
        return $stmt->execute(['ip' => $ip, 'action' => $action]);
    }

    /**
     * Limpa registros expirados (manutenção)
     */
    public function cleanup(): void
    {
        try {
            $this->db->exec("DELETE FROM {$this->tableName} WHERE expires_at < NOW()");
        } catch (\PDOException $e) {
            error_log("Erro ao limpar rate_limits: " . $e->getMessage());
        }
    }

    /**
     * Obtém o IP real do cliente considerando proxies
     */
    public static function getClientIp(): string
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_REAL_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR'
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                // Se for lista de IPs (X-Forwarded-For), pegar o primeiro
                if (strpos($ip, ',') !== false) {
                    $ips = explode(',', $ip);
                    $ip = trim($ips[0]);
                }
                // Validar IP
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0'; // Fallback
    }
}
