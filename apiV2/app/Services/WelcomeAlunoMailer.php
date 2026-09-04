<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class WelcomeAlunoMailer
{
    public function __construct(
        private readonly EmailTemplateService $templates,
    ) {}

    public function send(string $email, string $nome, string $cpf): void
    {
        try {
            $subject = '🎉 Bem-vindo ao AppCheckin - Seus Dados de Acesso';
            $html = $this->templates->welcomeAluno($nome, $email, $cpf);
            $text = $this->templates->welcomeAlunoPlainText($nome, $email, $cpf);

            TransactionalMailSender::send($email, $nome, $subject, $html, $text);
        } catch (\Throwable $e) {
            Log::error('Erro ao enviar email de boas-vindas: '.$e->getMessage());
        }
    }
}
