<?php

return [
    'jwt_secret' => env('JWT_SECRET', ''),
    'jwt_expiration' => (int) env('JWT_EXPIRATION', 86400),
    // App do aluno (X-Session-Renew): o token é renovado a cada uso e só expira após N dias sem abrir o app
    'jwt_app_session_days' => (int) env('JWT_APP_SESSION_DAYS', 30),

    // Usuário de suporte do Check-in: Admin + Aluno em toda academia, oculto para o tenant
    // (App\Support\UsuarioSuporte). A senha nunca fica no código: SUPORTE_SENHA só no .env.
    'suporte' => [
        'nome' => env('SUPORTE_NOME', 'André Cabral'),
        'email' => env('SUPORTE_EMAIL', 'andrecabrall@gmail.com'),
        'cpf' => env('SUPORTE_CPF'),
        'senha' => env('SUPORTE_SENHA'),
    ],
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
    'mail_allowed_subject_prefixes' => [
        'AppCheckin [ERRO]',
    ],

    /*
    | Monitoramento de erros (DB + e-mail + painel /ops/errors).
    */
    'error_alert_email' => env('ERROR_ALERT_EMAIL', 'andrecabrall@gmail.com'),
    'error_alert_from_name' => env('ERROR_ALERT_FROM_NAME', 'AppCheckin Ops'),
    'error_alert_throttle_minutes' => (int) env('ERROR_ALERT_THROTTLE_MINUTES', 15),
    'ops_view_token' => env('OPS_VIEW_TOKEN', ''),
    'app_version_update_token' => env('APP_VERSION_UPDATE_TOKEN', ''),
    'app_versions' => [
        'mobile' => [
            'version' => (int) env('APP_MOBILE_VERSION', 1),
            'release' => (int) env('APP_MOBILE_RELEASE', 0),
            'release_date' => env('APP_MOBILE_RELEASE_DATE', '2026-10-15'),
        ],
    ],

    /*
    | APK Android publicado. version = expo.version do app.json usado no build (ex.: 1.0.2).
    | O app instalado com versão menor mostra "Nova versão disponível" com o link do APK.
    | Se bloqueia ou só avisa: Super Admin → App mobile (app_versions.obrigatoria).
    */
    'android_release' => [
        'version' => env('APP_ANDROID_VERSION', ''),
        'apk_url' => env('APP_ANDROID_APK_URL', ''),
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

    // Tenant do próprio AppCheckin: a conta Asaas dele cobra os contratos das academias.
    'sistema_tenant_id' => (int) env('APPCHECKIN_SISTEMA_TENANT_ID', 1),

    'asaas' => [
        'sandbox_url' => env('ASAAS_SANDBOX_URL', 'https://api-sandbox.asaas.com/v3'),
        'production_url' => env('ASAAS_PRODUCTION_URL', 'https://api.asaas.com/v3'),
    ],
];
