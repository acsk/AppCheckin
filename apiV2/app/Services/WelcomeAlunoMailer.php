<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class WelcomeAlunoMailer
{
    public function send(string $email, string $nome, string $cpf): void
    {
        try {
            $html = $this->buildHtml($nome, $email, $cpf);
            $subject = '🎉 Bem-vindo ao AppCheckin - Seus Dados de Acesso';

            Mail::html($html, function ($message) use ($email, $nome, $subject) {
                $message->to($email, $nome)->subject($subject);
            });
        } catch (\Throwable $e) {
            Log::error('Erro ao enviar email de boas-vindas: '.$e->getMessage());
        }
    }

    private function buildHtml(string $nome, string $email, string $cpf): string
    {
        $appUrl = config('app.url', 'https://app.appcheckin.com.br');
        $year = date('Y');
        $nomeEsc = htmlspecialchars($nome, ENT_QUOTES, 'UTF-8');
        $emailEsc = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
        $cpfEsc = htmlspecialchars($cpf, ENT_QUOTES, 'UTF-8');

        return <<<HTML
<!DOCTYPE html>
<html><body style="font-family: Arial, sans-serif; color: #333;">
  <h2>Bem-vindo ao AppCheckin!</h2>
  <p>Olá <strong>{$nomeEsc}</strong>,</p>
  <p>Seu cadastro foi realizado com sucesso. Use os dados abaixo para acessar o app:</p>
  <ul>
    <li><strong>Email:</strong> {$emailEsc}</li>
    <li><strong>Senha inicial:</strong> seu CPF ({$cpfEsc})</li>
  </ul>
  <p><a href="{$appUrl}">Acessar AppCheckin</a></p>
  <p style="color: #888; font-size: 12px;">© {$year} AppCheckin</p>
</body></html>
HTML;
    }
}
