<?php

namespace App\Services;

use SendGrid;
use SendGrid\Mail\Mail;

class MailService
{
    private ?SendGrid $sendgrid = null;
    private string $fromAddress;
    private string $fromName;

    public function __construct()
    {
        // Configurar remetente
        $this->fromAddress = getenv('MAIL_FROM_ADDRESS') ?: $_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@appcheckin.com.br';
        $this->fromName = getenv('MAIL_FROM_NAME') ?: $_ENV['MAIL_FROM_NAME'] ?? 'App Check-in';

        // Inicializar SendGrid se houver API key
        $apiKey = getenv('SENDGRID_API_KEY') ?: $_ENV['SENDGRID_API_KEY'] ?? null;
        if ($apiKey) {
            $this->sendgrid = new SendGrid($apiKey);
        }
    }

    /**
     * Enviar email de recuperação de senha
     */
    public function sendPasswordRecoveryEmail(string $email, string $nome, string $token, int $expirationMinutes = 15): bool
    {
        try {
            $appUrl = getenv('APP_URL') ?: $_ENV['APP_URL'] ?? 'https://api.appcheckin.com.br';
            $recoveryUrl = $appUrl . '/password-recovery?token=' . urlencode($token);
            
            // HTML do email
            $html = $this->getPasswordRecoveryTemplate($nome, $recoveryUrl, $expirationMinutes);

            if ($this->sendgrid) {
                return $this->sendViaApi($email, $nome, 'Recuperação de Senha - App Check-in', $html);
            } else {
                error_log("SendGrid não configurado. Configure SENDGRID_API_KEY no .env");
                return false;
            }
        } catch (\Exception $e) {
            error_log("Erro ao enviar email de recuperação: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Enviar via SendGrid API
     */
    private function sendViaApi(string $to, string $toName, string $subject, string $html): bool
    {
        try {
            $email = new Mail();
            $email->setFrom($this->fromAddress, $this->fromName);
            $email->setSubject($subject);
            $email->addTo($to, $toName);
            $email->addContent("text/html", $html);
            $email->addContent("text/plain", strip_tags($html));

            $response = $this->sendgrid->send($email);
            
            // SendGrid retorna 202 em sucesso
            $success = $response->statusCode() >= 200 && $response->statusCode() < 300;
            
            if (!$success) {
                error_log("SendGrid Error: " . $response->statusCode() . " - " . $response->body());
            }
            
            return $success;
        } catch (\Exception $e) {
            error_log("Erro SendGrid: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Template HTML do email de recuperação
     */
    private function getPasswordRecoveryTemplate(string $nome, string $url, int $minutes): string
    {
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f5f5f5;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 20px auto;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .content {
            padding: 30px;
            color: #333;
        }
        .button {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 30px;
            border-radius: 5px;
            text-decoration: none;
            margin: 20px 0;
            font-weight: bold;
        }
        .button:hover {
            opacity: 0.9;
        }
        .warning {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
            color: #856404;
        }
        .footer {
            background-color: #f9f9f9;
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #999;
            border-top: 1px solid #eee;
        }
        .link-box {
            background-color: #f0f0f0;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
            word-break: break-all;
            font-size: 12px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔐 Recuperação de Senha</h1>
        </div>

        <div class="content">
            <p>Olá <strong>{$nome}</strong>,</p>

            <p>Recebemos uma solicitação para recuperar sua senha na plataforma <strong>App Check-in</strong>.</p>

            <p>Clique no botão abaixo para criar uma nova senha:</p>

            <center>
                <a href="{$url}" class="button">Recuperar Senha</a>
            </center>

            <p>Ou copie e cole o link no seu navegador:</p>
            <div class="link-box">{$url}</div>

            <div class="warning">
                ⚠️ <strong>Atenção!</strong> Este link expira em {$minutes} minutos por motivos de segurança.
            </div>

            <p><strong>Se você não solicitou esta recuperação</strong>, ignore este email. Sua conta está segura.</p>

            <p>Dúvidas? Entre em contato com nosso suporte através do email <strong>suporte@appcheckin.com.br</strong></p>
        </div>

        <div class="footer">
            <p>&copy; 2026 App Check-in. Todos os direitos reservados.</p>
            <p>Este é um email automático, não responda.</p>
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Enviar email genérico
     */
    public function send(string $to, string $subject, string $htmlBody, ?string $altBody = null): bool
    {
        try {
            if (!$this->sendgrid) {
                error_log("SendGrid não configurado");
                return false;
            }

            $email = new Mail();
            $email->setFrom($this->fromAddress, $this->fromName);
            $email->setSubject($subject);
            $email->addTo($to);
            $email->addContent("text/html", $htmlBody);
            
            if ($altBody) {
                $email->addContent("text/plain", $altBody);
            }

            $response = $this->sendgrid->send($email);
            return $response->statusCode() >= 200 && $response->statusCode() < 300;
        } catch (\Exception $e) {
            error_log("Erro ao enviar email: " . $e->getMessage());
            return false;
        }
    }
}
