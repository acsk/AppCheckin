<?php

namespace App\Repositories;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ApplicationErrorLogRepository
{
    public function tableExists(): bool
    {
        try {
            return DB::getSchemaBuilder()->hasTable('application_error_logs');
        } catch (\Throwable) {
            return false;
        }
    }

    public function insert(array $data): ?int
    {
        if (! $this->tableExists()) {
            return null;
        }

        return (int) DB::table('application_error_logs')->insertGetId([
            'fingerprint' => $data['fingerprint'],
            'description' => $data['description'],
            'message' => $data['message'],
            'level' => $data['level'] ?? 'error',
            'exception_class' => $data['exception_class'] ?? null,
            'source_file' => $data['source_file'] ?? null,
            'source_line' => $data['source_line'] ?? null,
            'request_method' => $data['request_method'] ?? null,
            'request_path' => $data['request_path'] ?? null,
            'ip' => $data['ip'] ?? null,
            'user_id' => $data['user_id'] ?? null,
            'tenant_id' => $data['tenant_id'] ?? null,
            'context' => isset($data['context']) ? json_encode($data['context'], JSON_UNESCAPED_UNICODE) : null,
            'created_at' => $data['created_at'] ?? Carbon::now('UTC')->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listGrouped(int $limit = 100): array
    {
        if (! $this->tableExists()) {
            return [];
        }

        return DB::table('application_error_logs')
            ->select([
                'fingerprint',
                'description',
                DB::raw('COUNT(*) as occurrences'),
                DB::raw('MIN(created_at) as first_seen'),
                DB::raw('MAX(created_at) as last_seen'),
                DB::raw('MAX(level) as max_level'),
            ])
            ->groupBy('fingerprint', 'description')
            ->orderByDesc('last_seen')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listByFingerprint(string $fingerprint, int $limit = 50): array
    {
        if (! $this->tableExists()) {
            return [];
        }

        return DB::table('application_error_logs')
            ->where('fingerprint', $fingerprint)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    public function countSince(string $fingerprint, int $minutes): int
    {
        if (! $this->tableExists()) {
            return 0;
        }

        return (int) DB::table('application_error_logs')
            ->where('fingerprint', $fingerprint)
            ->where('created_at', '>=', now()->subMinutes($minutes))
            ->count();
    }

    public function totalCount(): int
    {
        if (! $this->tableExists()) {
            return 0;
        }

        return (int) DB::table('application_error_logs')->count();
    }
}
