<?php

use App\Services\TransactionalMailSender;
use App\Support\OpsErrorAlertTestRunner;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('ops:error-alert-test {--force : Ignora throttle} {--clear-throttle : Limpa cache de throttle}', function () {
    $force = (bool) $this->option('force');
    $clearThrottle = (bool) $this->option('clear-throttle');

    if (class_exists(OpsErrorAlertTestRunner::class)) {
        $report = OpsErrorAlertTestRunner::run($force, $clearThrottle);
    } else {
        $message = 'Teste de alerta ops';
        $cacheKey = 'error_alert_sent:'.hash('sha256', $message);

        if ($clearThrottle) {
            Cache::forget($cacheKey);
        }

        if (! $force && Cache::has($cacheKey)) {
            $report = ['ok' => false, 'error' => 'Throttle ativo — use --force ou --clear-throttle'];
        } else {
            Log::error($message, ['origem' => 'error manual', 'via' => 'artisan-fallback']);
            $to = (string) config('appcheckin.error_alert_email');
            $subject = 'AppCheckin [ERRO] '.$message;
            TransactionalMailSender::send(
                $to,
                'Admin AppCheckin',
                $subject,
                '<p>Teste fallback ops — faça deploy de app/Support/OpsErrorAlertTestRunner.php</p>',
                'Teste fallback ops',
            );
            Cache::put($cacheKey, true, now()->addMinutes(max(1, (int) config('appcheckin.error_alert_throttle_minutes', 15))));
            $report = ['ok' => true, 'fallback' => true, 'to' => $to, 'subject' => $subject];
        }
    }

    $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    return ($report['ok'] ?? false) ? self::SUCCESS : self::FAILURE;
})->purpose('Dispara log de erro de teste e envia alerta ops por e-mail');
