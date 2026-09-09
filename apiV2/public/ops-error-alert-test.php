<?php

/**
 * Teste de alerta de erro ops (sem depender de artisan command registrado).
 *
 * https://apiv2.appcheckin.com.br/ops-error-alert-test.php?token=MAIL_DIAG_TOKEN&force=1
 */

header('Content-Type: application/json; charset=utf-8');

$root = dirname(__DIR__);
$response = [
    'ok' => false,
    'script' => __FILE__,
    'deploy_check' => [
        'OpsErrorAlertTestRunner' => is_file($root.'/app/Support/OpsErrorAlertTestRunner.php'),
        'OpsErrorAlertTestCommand' => is_file($root.'/app/Console/Commands/OpsErrorAlertTestCommand.php'),
        'ApplicationErrorAlertMailBuilder' => is_file($root.'/app/Services/ApplicationErrorAlertMailBuilder.php'),
    ],
];

$token = isset($_GET['token']) ? trim((string) $_GET['token']) : '';
$force = ! isset($_GET['force']) || $_GET['force'] === '1' || $_GET['force'] === 'true';
$clearThrottle = isset($_GET['clear_throttle']) && $_GET['clear_throttle'] !== '0';

try {
    if (! is_file($root.'/vendor/autoload.php')) {
        throw new RuntimeException('vendor/autoload.php ausente — rode composer install');
    }

    require $root.'/vendor/autoload.php';
    $app = require $root.'/bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

    $expectedToken = (string) env('MAIL_DIAG_TOKEN', '');
    if ($expectedToken === '' || ! hash_equals($expectedToken, $token)) {
        throw new RuntimeException('Token inválido. Passe ?token=MAIL_DIAG_TOKEN (defina MAIL_DIAG_TOKEN no .env)');
    }

    if (class_exists(\App\Support\OpsErrorAlertTestRunner::class)) {
        $response = array_merge($response, \App\Support\OpsErrorAlertTestRunner::run($force, $clearThrottle));
    } else {
        $response = array_merge($response, runFallbackAlertTest($force, $clearThrottle));
    }

    $response['ok'] = (bool) ($response['ok'] ?? false);
} catch (Throwable $e) {
    $response['ok'] = false;
    $response['error'] = $e->getMessage();
    $response['hint'] = 'Faça deploy completo (git pull) ou envie os arquivos em app/Support/ e app/Services/.';
}

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

/**
 * @return array<string, mixed>
 */
function runFallbackAlertTest(bool $force, bool $clearThrottle): array
{
    $message = 'Teste de alerta ops';
    $fingerprint = hash('sha256', $message);
    $cacheKey = 'error_alert_sent:'.$fingerprint;

    if ($clearThrottle) {
        Illuminate\Support\Facades\Cache::forget($cacheKey);
    }

    if (! $force && Illuminate\Support\Facades\Cache::has($cacheKey)) {
        return [
            'ok' => false,
            'fallback' => true,
            'error' => 'Throttle ativo. Use force=1 ou clear_throttle=1.',
        ];
    }

    Illuminate\Support\Facades\Log::error($message, [
        'origem' => 'error manual',
        'via' => 'ops-error-alert-test.php-fallback',
    ]);

    $to = (string) config('appcheckin.error_alert_email');
    if ($to === '' || ! filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return [
            'ok' => false,
            'fallback' => true,
            'error' => 'ERROR_ALERT_EMAIL inválido ou vazio no .env',
        ];
    }

    $subject = 'AppCheckin [ERRO] '.$message;
    $html = '<p>Teste fallback de alerta ops (deploy incompleto — envie arquivos app/Support e app/Services).</p>';
    $text = 'Teste fallback de alerta ops';

    \App\Services\TransactionalMailSender::send(
        $to,
        'Admin AppCheckin',
        $subject,
        $html,
        $text,
    );

    Illuminate\Support\Facades\Cache::put($cacheKey, true, now()->addMinutes(
        max(1, (int) config('appcheckin.error_alert_throttle_minutes', 15))
    ));

    return [
        'ok' => true,
        'fallback' => true,
        'to' => $to,
        'subject' => $subject,
        'hint' => 'E-mail enviado via fallback. Faça deploy completo para o template novo.',
    ];
}
