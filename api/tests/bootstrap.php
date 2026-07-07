<?php

declare(strict_types=1);

/**
 * Bootstrap dos testes CLI sem Composer platform_check.
 * Requer PHP >= 8.2 (mesma versão da API).
 */

if (PHP_VERSION_ID < 80200) {
    fwrite(
        STDERR,
        "PHP 8.2+ necessário. Versão atual: ".PHP_VERSION."\n".
        "No servidor Hostinger, tente: /opt/alt/php82/usr/bin/php tests/...\n"
    );
    exit(1);
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $file = dirname(__DIR__).'/app/'.$relative.'.php';

    if (is_file($file)) {
        require_once $file;
    }
});

date_default_timezone_set('America/Sao_Paulo');
