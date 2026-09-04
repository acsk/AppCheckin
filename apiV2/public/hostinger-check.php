<?php

/**
 * Diagnóstico Hostinger (infra + e-mail Resend).
 *
 * Infra: https://apiv2.appcheckin.com.br/hostinger-check.php
 * E-mail: https://apiv2.appcheckin.com.br/hostinger-check.php?mail=1
 * Teste de envio (protegido): ?mail=1&send_test=1&to=seu@email.com&token=SEU_MAIL_DIAG_TOKEN
 *
 * Remova ou proteja este arquivo após o debug.
 */

header('Content-Type: application/json; charset=utf-8');

$root = dirname(__DIR__);
$checks = [];
$mailReport = null;
$laravelError = null;

$checks['php_version'] = [
    'ok' => version_compare(PHP_VERSION, '8.4.0', '>='),
    'value' => PHP_VERSION,
    'required' => '>= 8.4 (Laravel 13)',
];

$paths = [
    'vendor/autoload.php' => $root.'/vendor/autoload.php',
    '.env' => $root.'/.env',
    'bootstrap/app.php' => $root.'/bootstrap/app.php',
    'storage' => $root.'/storage',
    'storage/logs' => $root.'/storage/logs',
    'storage/framework/cache' => $root.'/storage/framework/cache',
    'storage/framework/sessions' => $root.'/storage/framework/sessions',
    'storage/framework/views' => $root.'/storage/framework/views',
    'bootstrap/cache' => $root.'/bootstrap/cache',
];

foreach ($paths as $label => $path) {
    $exists = file_exists($path);
    $writable = is_dir($path) ? is_writable($path) : null;
    $checks[$label] = [
        'ok' => $exists && ($writable === null || $writable),
        'exists' => $exists,
        'writable' => $writable,
        'path' => $path,
    ];
}

$envMailPreview = null;
if (file_exists($root.'/.env')) {
    $env = file_get_contents($root.'/.env');
    preg_match('/^APP_KEY=(.*)$/m', $env, $m);
    $envKey = trim($m[1] ?? '', " \t\n\r\0\x0B\"'");
    $checks['APP_KEY'] = [
        'ok' => $envKey !== '' && $envKey !== 'base64:',
        'set' => $envKey !== '',
    ];
} else {
    $checks['APP_KEY'] = ['ok' => false, 'set' => false];
}

$extensions = ['pdo', 'pdo_mysql', 'mbstring', 'openssl', 'tokenizer', 'json', 'ctype', 'fileinfo', 'curl'];
foreach ($extensions as $ext) {
    $checks['ext_'.$ext] = ['ok' => extension_loaded($ext)];
}

$wantMail = isset($_GET['mail']) && $_GET['mail'] !== '0' && $_GET['mail'] !== 'false';
$sendTestTo = null;

if ($checks['vendor/autoload.php']['ok'] ?? false) {
    try {
        require $root.'/vendor/autoload.php';
        $app = require $root.'/bootstrap/app.php';
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        $checks['laravel_bootstrap'] = ['ok' => true];

        if ($wantMail) {
            $envMailPreview = \App\Support\MailDiagnostics::readEnvFileMailVars($root.'/.env');

            $sendTestRequested = isset($_GET['send_test']) && $_GET['send_test'] !== '0';
            $to = isset($_GET['to']) ? trim((string) $_GET['to']) : '';
            $token = isset($_GET['token']) ? trim((string) $_GET['token']) : '';
            $expectedToken = (string) env('MAIL_DIAG_TOKEN', '');

            if ($sendTestRequested) {
                if ($expectedToken === '' || ! hash_equals($expectedToken, $token)) {
                    $mailReport = [
                        'ok' => false,
                        'error' => 'Token inválido. Defina MAIL_DIAG_TOKEN no .env e passe ?token=...',
                    ];
                } elseif ($to === '' || ! filter_var($to, FILTER_VALIDATE_EMAIL)) {
                    $mailReport = [
                        'ok' => false,
                        'error' => 'Informe to=email válido',
                    ];
                } else {
                    $sendTestTo = $to;
                }
            }

            if ($mailReport === null) {
                $mailReport = \App\Support\MailDiagnostics::run($sendTestTo);
            }
        }
    } catch (Throwable $e) {
        $checks['laravel_bootstrap'] = ['ok' => false];
        $laravelError = $e->getMessage();
    }
}

$infraOk = ! in_array(false, array_column($checks, 'ok'), true);
$mailOk = $mailReport === null ? null : (bool) ($mailReport['ok'] ?? false);
$status = $infraOk && ($mailOk === null || $mailOk) ? 'ok' : 'fail';

$response = [
    'status' => $status,
    'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? null,
    'script' => __FILE__,
    'checks' => $checks,
    'laravel_error' => $laravelError,
];

if ($wantMail) {
    $response['mail'] = $mailReport;
    $response['env_mail_preview'] = $envMailPreview;
    $response['hints'] = [
        'list_domains' => 'Chame sem send_test para ver config + teste API Resend',
        'send_test' => 'Adicione &send_test=1&to=SEU_EMAIL&token=MAIL_DIAG_TOKEN (defina MAIL_DIAG_TOKEN no .env)',
        'ssh' => '/opt/alt/php84/usr/bin/php artisan mail:diagnose --send-test=seu@email.com',
        'password_recovery' => 'POST /v2/auth/password-recovery/request só envia se o e-mail existir no banco',
    ];
}

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
