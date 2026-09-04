<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Diagnóstico de e-mail (Resend / Laravel Mail) — uso em hostinger-check e artisan.
 */
final class MailDiagnostics
{
    /**
     * @return array<string, mixed>
     */
    public static function run(?string $testTo = null): array
    {
        $report = [
            'config' => self::configSnapshot(),
            'resend_api' => self::probeResendApi(),
            'mail_guard' => self::guardSnapshot(),
            'recent_log_errors' => self::recentMailLogLines(),
            'send_test' => null,
        ];

        if ($testTo !== null && filter_var($testTo, FILTER_VALIDATE_EMAIL)) {
            $report['send_test'] = self::sendTestMessage($testTo);
        }

        $report['ok'] = ($report['config']['ok'] ?? false)
            && ($report['resend_api']['ok'] ?? false)
            && (($report['send_test']['ok'] ?? true));

        return $report;
    }

    /**
     * @return array<string, mixed>
     */
    public static function configSnapshot(): array
    {
        $mailer = (string) config('mail.default', '');
        $fromAddress = (string) config('mail.from.address', '');
        $fromName = (string) config('mail.from.name', '');
        $resendKey = (string) config('services.resend.key', env('RESEND_API_KEY', ''));
        $appEnv = (string) config('app.env', '');

        $issues = [];
        if ($mailer !== 'resend') {
            $issues[] = "MAIL_MAILER deve ser 'resend' em produção (atual: {$mailer})";
        }
        if ($resendKey === '') {
            $issues[] = 'RESEND_API_KEY não configurada';
        }
        if ($fromAddress === '' || $fromAddress === 'hello@example.com') {
            $issues[] = 'MAIL_FROM_ADDRESS inválido ou padrão de exemplo';
        }
        if ($appEnv === 'production' && $mailer === 'log') {
            $issues[] = 'MAIL_MAILER=log em produção — e-mails não saem para Resend';
        }
        $resendSdkInstalled = class_exists('Resend');
        if ($mailer === 'resend' && ! $resendSdkInstalled) {
            $issues[] = 'Pacote resend/resend-php ausente (Class "Resend" not found) — composer require resend/resend-php';
        }

        return [
            'ok' => $issues === [],
            'app_env' => $appEnv,
            'mail_mailer' => $mailer,
            'resend_sdk_installed' => $resendSdkInstalled,
            'mail_from_address' => $fromAddress,
            'mail_from_name' => $fromName,
            'resend_api_key' => self::maskSecret($resendKey),
            'mail_guard_enabled' => (bool) config('appcheckin.mail_guard_enabled', true),
            'allowed_subjects_count' => count(config('appcheckin.mail_allowed_subjects', [])),
            'issues' => $issues,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function probeResendApi(): array
    {
        $apiKey = (string) config('services.resend.key', env('RESEND_API_KEY', ''));
        if ($apiKey === '') {
            return [
                'ok' => false,
                'http_status' => null,
                'message' => 'RESEND_API_KEY ausente',
            ];
        }

        $ch = curl_init('https://api.resend.com/domains');
        if ($ch === false) {
            return ['ok' => false, 'message' => 'curl_init falhou'];
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer '.$apiKey,
                'Accept: application/json',
            ],
        ]);

        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            return [
                'ok' => false,
                'http_status' => $status ?: null,
                'message' => 'Falha HTTP: '.$curlError,
            ];
        }

        $json = json_decode($body, true);
        $domains = [];
        if (is_array($json) && isset($json['data']) && is_array($json['data'])) {
            foreach ($json['data'] as $domain) {
                if (is_array($domain) && isset($domain['name'])) {
                    $domains[] = [
                        'name' => $domain['name'],
                        'status' => $domain['status'] ?? null,
                    ];
                }
            }
        }

        if ($status === 401 || $status === 403) {
            return [
                'ok' => false,
                'http_status' => $status,
                'message' => 'Chave Resend inválida ou revogada',
            ];
        }

        if ($status !== 200) {
            return [
                'ok' => false,
                'http_status' => $status,
                'message' => is_string($json['message'] ?? null) ? $json['message'] : 'Resposta inesperada da Resend',
                'raw' => mb_substr($body, 0, 300),
            ];
        }

        return [
            'ok' => true,
            'http_status' => $status,
            'domains' => $domains,
            'message' => count($domains) > 0
                ? 'API Resend OK — domínios verificados listados'
                : 'API Resend OK — nenhum domínio retornado (verifique verificação DNS)',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function guardSnapshot(): array
    {
        return [
            'enabled' => (bool) config('appcheckin.mail_guard_enabled', true),
            'from_enforced' => (string) config('appcheckin.mail_from_address', ''),
            'allowed_subjects' => config('appcheckin.mail_allowed_subjects', []),
        ];
    }

    /**
     * @return list<string>
     */
    public static function recentMailLogLines(int $maxLines = 15): array
    {
        $logPath = storage_path('logs/laravel.log');
        if (! is_readable($logPath)) {
            return [];
        }

        $content = @file_get_contents($logPath);
        if ($content === false || $content === '') {
            return [];
        }

        $lines = preg_split('/\r\n|\r|\n/', $content) ?: [];
        $matches = [];
        $pattern = '/recuperação|recuperacao|Resend|Mail guard|mail|email|boas-vindas/i';

        foreach (array_reverse($lines) as $line) {
            if ($line === '' || ! preg_match($pattern, $line)) {
                continue;
            }
            $matches[] = mb_substr($line, 0, 500);
            if (count($matches) >= $maxLines) {
                break;
            }
        }

        return array_reverse($matches);
    }

    /**
     * @return array<string, mixed>
     */
    public static function sendTestMessage(string $to): array
    {
        $subject = '🔐 Código de Recuperação de Senha - App Check-in';
        $html = '<p>Teste de diagnóstico AppCheckin — '.date('c').'</p>';

        try {
            Mail::html($html, function ($message) use ($to, $subject): void {
                $message->to($to)->subject($subject);
            });

            Log::info('Mail diagnostics: teste enviado', ['to' => $to]);

            return [
                'ok' => true,
                'to' => $to,
                'subject' => $subject,
                'message' => 'Mail::send concluiu sem exceção — confira Resend e a caixa de entrada',
            ];
        } catch (Throwable $e) {
            Log::error('Mail diagnostics: falha no teste', [
                'to' => $to,
                'error' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'to' => $to,
                'error' => $e->getMessage(),
            ];
        }
    }

    public static function maskSecret(string $secret): string
    {
        if ($secret === '') {
            return '(vazio)';
        }
        if (strlen($secret) <= 8) {
            return '****';
        }

        return substr($secret, 0, 4).'…'.substr($secret, -4);
    }

    /**
     * Lê variáveis de e-mail direto do .env (antes do bootstrap Laravel).
     *
     * @return array<string, mixed>
     */
    public static function readEnvFileMailVars(string $envPath): array
    {
        if (! is_readable($envPath)) {
            return ['ok' => false, 'message' => '.env não legível'];
        }

        $content = file_get_contents($envPath);
        if ($content === false) {
            return ['ok' => false, 'message' => 'Falha ao ler .env'];
        }

        $keys = [
            'MAIL_MAILER',
            'MAIL_FROM_ADDRESS',
            'MAIL_FROM_NAME',
            'RESEND_API_KEY',
            'MAIL_GUARD_ENABLED',
        ];

        $vars = [];
        foreach ($keys as $key) {
            $value = self::parseEnvLine($content, $key);
            if ($key === 'RESEND_API_KEY') {
                $vars[$key] = self::maskSecret((string) $value);
            } else {
                $vars[$key] = $value;
            }
        }

        return [
            'ok' => true,
            'vars' => $vars,
        ];
    }

    private static function parseEnvLine(string $content, string $key): ?string
    {
        if (! preg_match('/^'.preg_quote($key, '/').'=(.*)$/m', $content, $m)) {
            return null;
        }

        return trim($m[1], " \t\n\r\0\x0B\"'");
    }
}
