<?php

return [
    'jwt_secret' => env('JWT_SECRET', ''),
    'jwt_expiration' => (int) env('JWT_EXPIRATION', 86400),
    'api_version' => 'v2',
];
