<?php

// Configurar timezone Brasil para todo o sistema
date_default_timezone_set('America/Sao_Paulo');

return [
    'displayErrorDetails' => $_ENV['APP_ENV'] === 'development',
    'logErrorDetails' => true,
    'logErrors' => true,
    'jwt' => [
        'secret' => $_ENV['JWT_SECRET'],
        'expiration' => $_ENV['JWT_EXPIRATION'] ?? 86400
    ]
];
