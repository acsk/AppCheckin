<?php

namespace App\Console\Commands;

use App\Services\TransactionalMailSender;
use App\Support\OpsErrorAlertTestRunner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class OpsErrorAlertTestCommand extends Command
{
    protected $signature = 'error-alert:test {--force : Ignora throttle de 15 minutos} {--clear-throttle : Limpa cache de throttle antes do teste}';

    protected $description = 'Dispara log de erro de teste e envia alerta ops por e-mail';

    public function handle(): int
    {
        $force = (bool) $this->option('force');
        $clearThrottle = (bool) $this->option('clear-throttle');

        if (class_exists(OpsErrorAlertTestRunner::class)) {
            $report = OpsErrorAlertTestRunner::run($force, $clearThrottle);
        } else {
            $report = $this->runFallback($force, $clearThrottle);
        }

        $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return ($report['ok'] ?? false) ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return array<string, mixed>
     */
    private function runFallback(bool $force, bool $clearThrottle): array
    {
        $message = 'Teste de alerta ops';
        $cacheKey = 'error_alert_sent:'.hash('sha256', $message);

        if ($clearThrottle) {
            Cache::forget($cacheKey);
        }

        if (! $force && Cache::has($cacheKey)) {
            return ['ok' => false, 'error' => 'Throttle ativo — use --force ou --clear-throttle'];
        }

        Log::error($message, ['origem' => 'error manual', 'via' => 'error-alert:test']);

        $to = (string) config('appcheckin.error_alert_email');
        if ($to === '' || ! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'ERROR_ALERT_EMAIL inválido ou vazio'];
        }

        $subject = 'AppCheckin [ERRO] '.$message;
        TransactionalMailSender::send(
            $to,
            'Admin AppCheckin',
            $subject,
            '<p>Teste fallback — envie app/Support/OpsErrorAlertTestRunner.php</p>',
            'Teste fallback ops',
        );
        Cache::put($cacheKey, true, now()->addMinutes(max(1, (int) config('appcheckin.error_alert_throttle_minutes', 15))));

        return ['ok' => true, 'fallback' => true, 'to' => $to, 'subject' => $subject];
    }
}
