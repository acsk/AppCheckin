<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Rate limiter por IP (paridade Slim — tabela rate_limits).
 */
class RateLimiter
{
    private const TABLE = 'rate_limits';

    public function __construct(
        private readonly int $maxAttempts = 5,
        private readonly int $decayMinutes = 15,
    ) {}

    /**
     * @return array{allowed: bool, remaining: int, retryAfter: int|null}
     */
    public function attempt(string $ip, string $action = 'default'): array
    {
        $this->ensureTableExists();
        $this->cleanup();

        $record = DB::table(self::TABLE)
            ->where('ip', $ip)
            ->where('action', $action)
            ->where('expires_at', '>', now())
            ->first(['attempts', 'expires_at']);

        if (! $record) {
            DB::table(self::TABLE)->insert([
                'ip' => $ip,
                'action' => $action,
                'attempts' => 1,
                'expires_at' => now()->addMinutes($this->decayMinutes),
            ]);

            return [
                'allowed' => true,
                'remaining' => $this->maxAttempts - 1,
                'retryAfter' => null,
            ];
        }

        $attempts = (int) $record->attempts;
        $expiresAt = strtotime((string) $record->expires_at);
        $remaining = max(0, $this->maxAttempts - $attempts);

        if ($attempts >= $this->maxAttempts) {
            return [
                'allowed' => false,
                'remaining' => 0,
                'retryAfter' => max(0, $expiresAt - time()),
            ];
        }

        DB::table(self::TABLE)
            ->where('ip', $ip)
            ->where('action', $action)
            ->where('expires_at', '>', now())
            ->update([
                'attempts' => DB::raw('attempts + 1'),
                'updated_at' => now(),
            ]);

        return [
            'allowed' => true,
            'remaining' => $remaining - 1,
            'retryAfter' => null,
        ];
    }

    public function reset(string $ip, string $action = 'default'): void
    {
        DB::table(self::TABLE)
            ->where('ip', $ip)
            ->where('action', $action)
            ->delete();
    }

    private function cleanup(): void
    {
        try {
            DB::table(self::TABLE)->where('expires_at', '<', now())->delete();
        } catch (\Throwable) {
            // tabela pode não existir ainda
        }
    }

    private function ensureTableExists(): void
    {
        try {
            DB::statement('CREATE TABLE IF NOT EXISTS '.self::TABLE.' (
                id INT AUTO_INCREMENT PRIMARY KEY,
                ip VARCHAR(45) NOT NULL,
                action VARCHAR(100) NOT NULL,
                attempts INT DEFAULT 1,
                expires_at DATETIME NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_ip_action (ip, action),
                INDEX idx_expires (expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        } catch (\Throwable) {
            // ignore — attempt falhará naturalmente se DB indisponível
        }
    }
}
