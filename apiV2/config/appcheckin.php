<?php

return [
    'jwt_secret' => env('JWT_SECRET', ''),
    'jwt_expiration' => (int) env('JWT_EXPIRATION', 86400),
    'api_version' => 'v2',
    'rate_limit_register_max' => (int) env('RATE_LIMIT_REGISTER_MAX_ATTEMPTS', 5),
    'rate_limit_register_decay' => (int) env('RATE_LIMIT_REGISTER_DECAY_MINUTES', 15),
    'recaptcha_secret' => env('RECAPTCHA_SECRET_KEY', ''),
    'recaptcha_min_score' => (float) env('RECAPTCHA_MINIMUM_SCORE', 0.5),
];
