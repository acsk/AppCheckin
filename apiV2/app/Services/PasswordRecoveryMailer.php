<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class PasswordRecoveryMailer
{
    public function send(string $email, string $nome, string $token, int $expirationMinutes = 60): void
    {
        try {
            $html = $this->buildHtml($nome, $token, $expirationMinutes);
            $subject = '🔐 Código de Recuperação de Senha - App Check-in';

            Mail::html($html, function ($message) use ($email, $nome, $subject) {
                $message->to($email, $nome)->subject($subject);
            });
        } catch (\Throwable $e) {
            Log::error('Erro ao enviar email de recuperação: '.$e->getMessage());
        }
    }

    private function buildHtml(string $nome, string $token, int $expirationMinutes): string
    {
        $appName = config('app.name', 'App Checkin');
        $year = date('Y');

        return <<<HTML
<!DOCTYPE html>
<html><body style="font-family: Arial, sans-serif; color: #333;">
  <h2>Recuperação de Senha</h2>
  <p>Olá <strong>{$nome}</strong>,</p>
  <p>Use o código abaixo no aplicativo <strong>{$appName}</strong> para criar uma nova senha:</p>
  <p style="font-family: monospace; font-size: 16px; font-weight: bold; word-break: break-all;">{$token}</p>
  <p style="color: #888; font-size: 14px;">Este código expira em {$expirationMinutes} minutos.</p>
  <p style="color: #888; font-size: 12px;">© {$year} {$appName}</p>
</body></html>
HTML;
    }
}
