<?php

namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class MailService
{
    private PHPMailer $mailer;

    public function __construct()
    {
        $this->mailer = new PHPMailer(true);

        try {
            // Configuração SMTP
            $this->mailer->isSMTP();
            $this->mailer->Host = getenv('MAIL_HOST') ?: $_ENV['MAIL_HOST'] ?? 'smtp.hostinger.com';
            $this->mailer->SMTPAuth = true;
            $this->mailer->Username = getenv('MAIL_USERNAME') ?: $_ENV['MAIL_USERNAME'] ?? '';
            $this->mailer->Password = getenv('MAIL_PASSWORD') ?: $_ENV['MAIL_PASSWORD'] ?? '';
            
            $encryption = getenv('MAIL_ENCRYPTION') ?: $_ENV['MAIL_ENCRYPTION'] ?? 'ssl';
            $this->mailer->SMTPSecure = $encryption === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
            
            $this->mailer->Port = (int)(getenv('MAIL_PORT') ?: $_ENV['MAIL_PORT'] ?? 465);
            $this->mailer->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ];

            // Debug modo (apenas em desenvolvimento)
            $debug = getenv('MAIL_DEBUG') ?: $_ENV['MAIL_DEBUG'] ?? 'false';
            if ($debug === 'true' || $debug === '1') {
                $this->mailer->SMTPDebug = 2;
            }

            // Charset
            $this->mailer->CharSet = 'UTF-8';
            $this->mailer->isHTML(true);

            // Remetente
            $fromAddress = getenv('MAIL_FROM_ADDRESS') ?: $_ENV['MAIL_FROM_ADDRESS'] ?? 'mail@appcheckin.com.br';
            $fromName = getenv('MAIL_FROM_NAME') ?: $_ENV['MAIL_FROM_NAME'] ?? 'App Check-in';
            $this->mailer->setFrom($fromAddress, $fromName);
        } catch (Exception $e) {
            throw new \RuntimeException("Erro ao configurar email: " . $e->getMessage());
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

            // Configurar destinatário
            $this->mailer->addAddress($email, $nome);

            // Assunto
            $this->mailer->Subject = 'Recuperação de Senha - App Check-in';

            // Corpo
            $this->mailer->Body = $html;
            $this->mailer->AltBody = "Clique no link para recuperar sua senha: {$recoveryUrl}";

            // Enviar
            $result = $this->mailer->send();

            // Limpar destinatários para próximo envio
            $this->mailer->clearAddresses();

            return $result;
        } catch (Exception $e) {
            $errorMsg = "Erro ao enviar email para {$email}: " . $e->getMessage();
            error_log($errorMsg);
            // Também escrever em stderr
            file_put_contents('php://stderr', $errorMsg . PHP_EOL);
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
            $this->mailer->addAddress($to);
            $this->mailer->Subject = $subject;
            $this->mailer->Body = $htmlBody;
            
            if ($altBody) {
                $this->mailer->AltBody = $altBody;
            }

            $result = $this->mailer->send();
            $this->mailer->clearAddresses();

            return $result;
        } catch (Exception $e) {
            error_log("Erro ao enviar email: " . $e->getMessage());
            return false;
        }
    }
}
