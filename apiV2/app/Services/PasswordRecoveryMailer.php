<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class PasswordRecoveryMailer
{
    public function __construct(
        private readonly EmailTemplateService $templates,
    ) {}

    public function send(string $email, string $nome, string $token, int $expirationMinutes = 60): void
    {
        try {
            $subject = '🔐 Código de Recuperação de Senha - App Check-in';
            $html = $this->templates->passwordRecovery($nome, $token, $expirationMinutes);
            $text = $this->templates->passwordRecoveryPlainText($nome, $token, $expirationMinutes);

            TransactionalMailSender::send($email, $nome, $subject, $html, $text);

            Log::info('Email de recuperação enviado', [
                'to' => $email,
                'mailer' => config('mail.default'),
            ]);
        } catch (\Throwable $e) {
            Log::error('Erro ao enviar email de recuperação: '.$e->getMessage(), [
                'to' => $email,
                'mailer' => config('mail.default'),
                'exception' => $e::class,
            ]);
        }
    }
}
