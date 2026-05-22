<?php

// Configurar timezone Brasil para todo o sistema
date_default_timezone_set('America/Sao_Paulo');

$appEnv = strtolower($_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? getenv('APP_ENV') ?: 'production');

return [
    'displayErrorDetails' => in_array($appEnv, ['development', 'local', 'dev', 'testing'], true),
    'logErrorDetails' => true,
    'logErrors' => true,
    'jwt' => [
        'secret' => $_ENV['JWT_SECRET'],
        'expiration' => $_ENV['JWT_EXPIRATION'] ?? 86400
    ]
];
