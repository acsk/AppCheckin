<?php

return [
    'jwt_secret' => env('JWT_SECRET', ''),
    'jwt_expiration' => (int) env('JWT_EXPIRATION', 86400),
    'api_version' => 'v2',
    'rate_limit_register_max' => (int) env('RATE_LIMIT_REGISTER_MAX_ATTEMPTS', 5),
    'rate_limit_register_decay' => (int) env('RATE_LIMIT_REGISTER_DECAY_MINUTES', 15),
    'rate_limit_password_recovery_max' => (int) env('RATE_LIMIT_PASSWORD_RECOVERY_MAX', 3),
    'rate_limit_password_recovery_decay' => (int) env('RATE_LIMIT_PASSWORD_RECOVERY_DECAY_MINUTES', 15),
    'password_recovery_require_recaptcha' => filter_var(
        env('PASSWORD_RECOVERY_REQUIRE_RECAPTCHA', false),
        FILTER_VALIDATE_BOOL,
    ),
    'recaptcha_secret' => env('RECAPTCHA_SECRET_KEY', ''),
    'recaptcha_min_score' => (float) env('RECAPTCHA_MINIMUM_SCORE', 0.5),

    /*
    | Guarda de e-mail: só permite assuntos/remetente oficiais via Laravel Mail.
    */
    'mail_guard_enabled' => filter_var(env('MAIL_GUARD_ENABLED', true), FILTER_VALIDATE_BOOL),
    'mail_from_address' => env('MAIL_FROM_ADDRESS', 'mail@appcheckin.com.br'),
    'mail_allowed_subjects' => [
        '🔐 Código de Recuperação de Senha - App Check-in',
        '🎉 Bem-vindo ao AppCheckin - Seus Dados de Acesso',
    ],

    /*
    | Diretório absoluto das fotos de perfil (produção Hostinger).
    | Padrão Slim: .../public_html/api/public/uploads/fotos
    | Com symlink em apiV2/public/uploads → api/public/uploads, pode omitir.
    */
    'uploads_fotos_dir' => env('UPLOADS_FOTOS_DIR', ''),

    /*
    | Pasta uploads da Slim (legado) para fallback e importação.
    */
    'uploads_legacy_dir' => env('UPLOADS_LEGACY_DIR', ''),
];
