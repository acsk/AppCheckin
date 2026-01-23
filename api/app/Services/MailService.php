<?php

namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use App\Models\EmailLog;
use PDO;

class MailService
{
    private string $fromAddress;
    private string $fromName;
    private string $mailDriver;
    
    // Configurações SMTP (Amazon SES ou outro)
    private ?string $smtpHost;
    private ?int $smtpPort;
    private ?string $smtpUsername;
    private ?string $smtpPassword;
    private ?string $smtpEncryption;
    
    // Auditoria
    private ?EmailLog $emailLog = null;
    private ?PDO $db = null;

    public function __construct(?PDO $db = null)
    {
        // Conexão com banco para auditoria
        $this->db = $db;
        if ($this->db) {
            $this->emailLog = new EmailLog($this->db);
        }
        
        // Configurar remetente
        $this->fromAddress = getenv('MAIL_FROM_ADDRESS') ?: $_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@appcheckin.com.br';
        $this->fromName = getenv('MAIL_FROM_NAME') ?: $_ENV['MAIL_FROM_NAME'] ?? 'App Check-in';
        
        // Driver de email: 'ses', 'smtp', 'sendgrid'
        $this->mailDriver = getenv('MAIL_DRIVER') ?: $_ENV['MAIL_DRIVER'] ?? 'ses';
        
        // Configurações SMTP (usadas para SES e SMTP genérico)
        $this->smtpHost = getenv('MAIL_HOST') ?: $_ENV['MAIL_HOST'] ?? null;
        $this->smtpPort = (int)(getenv('MAIL_PORT') ?: $_ENV['MAIL_PORT'] ?? 587);
        $this->smtpUsername = getenv('MAIL_USERNAME') ?: $_ENV['MAIL_USERNAME'] ?? null;
        $this->smtpPassword = getenv('MAIL_PASSWORD') ?: $_ENV['MAIL_PASSWORD'] ?? null;
        $this->smtpEncryption = getenv('MAIL_ENCRYPTION') ?: $_ENV['MAIL_ENCRYPTION'] ?? 'tls';
    }

    /**
     * Enviar email de recuperação de senha
     */
    public function sendPasswordRecoveryEmail(string $email, string $nome, string $token, int $expirationMinutes = 15, ?int $tenantId = null, ?int $usuarioId = null): bool
    {
        try {
            $appUrl = getenv('APP_URL') ?: $_ENV['APP_URL'] ?? 'https://api.appcheckin.com.br';
            $recoveryUrl = $appUrl . '/password-recovery?token=' . urlencode($token);
            
            // HTML do email
            $html = $this->getPasswordRecoveryTemplate($nome, $recoveryUrl, $expirationMinutes);
            $subject = 'Recuperação de Senha - App Check-in';

            return $this->sendViaSMTP(
                $email, 
                $nome, 
                $subject, 
                $html,
                EmailLog::TYPE_PASSWORD_RECOVERY,
                $tenantId,
                $usuarioId
            );
        } catch (\Exception $e) {
            error_log("Erro ao enviar email de recuperação: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Enviar via SMTP (Amazon SES ou outro servidor SMTP)
     */
    private function sendViaSMTP(
        string $to, 
        string $toName, 
        string $subject, 
        string $html,
        string $emailType = 'generic',
        ?int $tenantId = null,
        ?int $usuarioId = null
    ): bool {
        $logId = null;
        
        try {
            // Registrar tentativa de envio no log (status: pending)
            if ($this->emailLog) {
                $logId = $this->emailLog->create([
                    'tenant_id' => $tenantId,
                    'usuario_id' => $usuarioId,
                    'to_email' => $to,
                    'to_name' => $toName,
                    'from_email' => $this->fromAddress,
                    'from_name' => $this->fromName,
                    'subject' => $subject,
                    'email_type' => $emailType,
                    'body' => $html,
                    'status' => EmailLog::STATUS_PENDING,
                    'provider' => $this->mailDriver
                ]);
            }
            
            if (!$this->smtpHost || !$this->smtpUsername || !$this->smtpPassword) {
                $errorMsg = "SMTP não configurado. Configure MAIL_HOST, MAIL_USERNAME e MAIL_PASSWORD no .env";
                error_log($errorMsg);
                
                if ($logId && $this->emailLog) {
                    $this->emailLog->updateStatus($logId, EmailLog::STATUS_FAILED, $errorMsg);
                }
                return false;
            }

            $mailer = new PHPMailer(true);
            
            // Configuração SMTP
            $mailer->isSMTP();
            $mailer->Host = $this->smtpHost;
            $mailer->SMTPAuth = true;
            $mailer->Username = $this->smtpUsername;
            $mailer->Password = $this->smtpPassword;
            $mailer->Port = $this->smtpPort;
            
            // Configurar criptografia
            if ($this->smtpEncryption === 'tls') {
                $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } elseif ($this->smtpEncryption === 'ssl') {
                $mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            }
            
            // Opções SSL para evitar problemas de certificado
            $mailer->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                    'allow_self_signed' => false
                ]
            ];
            
            $mailer->CharSet = 'UTF-8';
            $mailer->isHTML(true);
            
            // Configurar email
            $mailer->setFrom($this->fromAddress, $this->fromName);
            $mailer->addAddress($to, $toName);
            $mailer->Subject = $subject;
            $mailer->Body = $html;
            $mailer->AltBody = strip_tags($html);
            
            $success = $mailer->send();
            
            if ($success) {
                error_log("Email enviado com sucesso para: {$to}");
                
                // Atualizar log com sucesso
                if ($logId && $this->emailLog) {
                    $messageId = $mailer->getLastMessageID();
                    $this->emailLog->updateStatus($logId, EmailLog::STATUS_SENT, null, $messageId);
                }
            }
            
            return $success;
        } catch (Exception $e) {
            $errorMsg = $e->getMessage();
            error_log("Erro SMTP ({$this->mailDriver}): " . $errorMsg);
            
            // Atualizar log com erro
            if ($logId && $this->emailLog) {
                $this->emailLog->updateStatus($logId, EmailLog::STATUS_FAILED, $errorMsg);
            }
            
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
    public function send(
        string $to, 
        string $subject, 
        string $htmlBody, 
        ?string $altBody = null,
        string $emailType = 'generic',
        ?int $tenantId = null,
        ?int $usuarioId = null
    ): bool {
        return $this->sendViaSMTP(
            $to,
            '', // toName vazio para envio genérico
            $subject,
            $htmlBody,
            $emailType,
            $tenantId,
            $usuarioId
        );
    }

    /**
     * Obter instância do EmailLog para consultas externas
     */
    public function getEmailLog(): ?EmailLog
    {
        return $this->emailLog;
    }
}
