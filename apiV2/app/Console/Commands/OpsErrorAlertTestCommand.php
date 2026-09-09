<?php

namespace App\Console\Commands;

use App\Services\ApplicationErrorLogService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class OpsErrorAlertTestCommand extends Command
{
    protected $signature = 'ops:error-alert-test
        {--force : Ignora throttle de 15 minutos}
        {--clear-throttle : Limpa cache de throttle antes do teste}';

    protected $description = 'Dispara log de erro de teste e tenta enviar o e-mail de alerta ops';

    public function handle(ApplicationErrorLogService $errorLogs): int
    {
        $message = 'Teste de alerta ops';
        $context = ['origem' => 'error manual', 'via' => 'ops:error-alert-test'];

        if ($this->option('clear-throttle')) {
            foreach ($errorLogs->groupedForView(20) as $group) {
                if (($group['description'] ?? '') === 'Teste de alerta ops') {
                    Cache::forget('error_alert_sent:'.(string) $group['fingerprint']);
                    break;
                }
            }
        }

        Log::error($message, $context);

        $groups = $errorLogs->groupedForView(5);
        $latest = null;
        foreach ($groups as $group) {
            if (($group['description'] ?? '') === 'Teste de alerta ops') {
                $events = $errorLogs->detailsForFingerprint((string) $group['fingerprint'], 1);
                $latest = $events[0] ?? null;
                break;
            }
        }

        if ($latest === null) {
            $this->error('Erro gravado no log, mas não encontrado em application_error_logs. Verifique LOG_STACK e a tabela.');

            return self::FAILURE;
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

        $force = (bool) $this->option('force');
        $sent = $errorLogs->sendAlertEmailForTest($payload, (int) $latest['id'], $force);

        $this->line(json_encode([
            'ok' => $sent,
            'log_id' => $latest['id'],
            'force' => $force,
            'to' => config('appcheckin.error_alert_email'),
            'hint' => $sent
                ? 'E-mail enviado. Confira a caixa de entrada.'
                : 'Falhou ou throttle ativo. Use --force ou --clear-throttle. Veja storage/logs/laravel.log.',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $sent ? self::SUCCESS : self::FAILURE;
    }
}
