<?php

/**
 * Diagnóstico pré-Laravel para deploy na Hostinger.
 * Acesse: https://apiv2.appcheckin.com.br/hostinger-check.php
 * Remova este arquivo após o deploy estar OK.
 */

header('Content-Type: application/json; charset=utf-8');

$root = dirname(__DIR__);
$checks = [];

$checks['php_version'] = [
    'ok' => version_compare(PHP_VERSION, '8.2.0', '>='),
    'value' => PHP_VERSION,
    'required' => '>= 8.2',
];

$paths = [
    'vendor/autoload.php' => $root . '/vendor/autoload.php',
    '.env' => $root . '/.env',
    'bootstrap/app.php' => $root . '/bootstrap/app.php',
    'storage' => $root . '/storage',
    'storage/logs' => $root . '/storage/logs',
    'storage/framework/cache' => $root . '/storage/framework/cache',
    'storage/framework/sessions' => $root . '/storage/framework/sessions',
    'storage/framework/views' => $root . '/storage/framework/views',
    'bootstrap/cache' => $root . '/bootstrap/cache',
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

$envKey = null;
if (file_exists($root . '/.env')) {
    $env = file_get_contents($root . '/.env');
    preg_match('/^APP_KEY=(.*)$/m', $env, $m);
    $envKey = trim($m[1] ?? '', " \t\n\r\0\x0B\"'");
    $checks['APP_KEY'] = [
        'ok' => $envKey !== '' && $envKey !== 'base64:',
        'set' => $envKey !== '',
    ];
} else {
    $checks['APP_KEY'] = ['ok' => false, 'set' => false];
}

$extensions = ['pdo', 'pdo_mysql', 'mbstring', 'openssl', 'tokenizer', 'json', 'ctype', 'fileinfo'];
foreach ($extensions as $ext) {
    $checks['ext_' . $ext] = ['ok' => extension_loaded($ext)];
}

$laravelError = null;
if ($checks['vendor/autoload.php']['ok'] ?? false) {
    try {
        require $root . '/vendor/autoload.php';
        $app = require $root . '/bootstrap/app.php';
        $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
        $checks['laravel_bootstrap'] = ['ok' => true];
    } catch (Throwable $e) {
        $checks['laravel_bootstrap'] = ['ok' => false];
        $laravelError = $e->getMessage();
    }
}

$allOk = !in_array(false, array_column($checks, 'ok'), true);

echo json_encode([
    'status' => $allOk ? 'ok' : 'fail',
    'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? null,
    'script' => __FILE__,
    'checks' => $checks,
    'laravel_error' => $laravelError,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
