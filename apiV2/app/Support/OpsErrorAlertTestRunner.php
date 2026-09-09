<?php

namespace App\Support;

use App\Services\ApplicationErrorLogService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

final class OpsErrorAlertTestRunner
{
    /**
     * @return array<string, mixed>
     */
    public static function run(bool $force = true, bool $clearThrottle = false): array
    {
        /** @var ApplicationErrorLogService $errorLogs */
        $errorLogs = app(ApplicationErrorLogService::class);

        $message = 'Teste de alerta ops';
        $context = ['origem' => 'error manual', 'via' => 'ops-error-alert-test'];

        if ($clearThrottle) {
            foreach ($errorLogs->groupedForView(20) as $group) {
                if (($group['description'] ?? '') === $message) {
                    Cache::forget('error_alert_sent:'.(string) $group['fingerprint']);
                    break;
                }
            }
        }

        Log::error($message, $context);

        $latest = null;
        foreach ($errorLogs->groupedForView(5) as $group) {
            if (($group['description'] ?? '') === $message) {
                $events = $errorLogs->detailsForFingerprint((string) $group['fingerprint'], 1);
                $latest = $events[0] ?? null;
                break;
            }
        }

        if ($latest === null) {
            return [
                'ok' => false,
                'error' => 'Erro gravado no log, mas não encontrado em application_error_logs. Verifique LOG_STACK e a tabela.',
            ];
        }

        $payload = [
            'fingerprint' => (string) ($latest['fingerprint'] ?? hash('sha256', $message)),
            'description' => (string) ($latest['description'] ?? $message),
            'message' => (string) ($latest['message'] ?? $message),
            'level' => (string) ($latest['level'] ?? 'error'),
            'exception_class' => $latest['exception_class'] ?? null,
            'source_file' => $latest['source_file'] ?? null,
            'source_line' => $latest['source_line'] ?? null,
            'request_method' => $latest['request_method'] ?? null,
            'request_path' => $latest['request_path'] ?? null,
            'ip' => $latest['ip'] ?? null,
            'user_id' => $latest['user_id'] ?? null,
            'tenant_id' => $latest['tenant_id'] ?? null,
            'context' => is_string($latest['context'] ?? null)
                ? json_decode($latest['context'], true) ?? []
                : ($latest['context'] ?? []),
        ];

        $sent = $errorLogs->sendAlertEmailForTest($payload, (int) $latest['id'], $force);

        return [
            'ok' => $sent,
            'log_id' => $latest['id'],
            'force' => $force,
            'clear_throttle' => $clearThrottle,
            'to' => config('appcheckin.error_alert_email'),
            'hint' => $sent
                ? 'E-mail enviado. Confira a caixa de entrada.'
                : 'Falhou ou throttle ativo. Use force=1 ou clear_throttle=1. Veja storage/logs/laravel.log.',
        ];
    }
}
