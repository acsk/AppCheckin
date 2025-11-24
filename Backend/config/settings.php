<?php

return [
    'displayErrorDetails' => $_ENV['APP_ENV'] === 'development',
    'logErrorDetails' => true,
    'logErrors' => true,
    'jwt' => [
        'secret' => $_ENV['JWT_SECRET'],
        'expiration' => $_ENV['JWT_EXPIRATION'] ?? 86400
    ]
];
