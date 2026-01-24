<?php

namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Resend;
use App\Models\EmailLog;
use App\Services\EmailTemplateService;
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
    
    // Resend API
    private ?string $resendApiKey;
    
    // Auditoria
    private ?EmailLog $emailLog = null;
    private ?PDO $db = null;
    
    // Template Service
    private EmailTemplateService $templateService;

    public function __construct(?PDO $db = null)
    {
        // Conexão com banco para auditoria
        $this->db = $db;
        if ($this->db) {
            $this->emailLog = new EmailLog($this->db);
        }
        
        // Inicializar serviço de templates
        $this->templateService = new EmailTemplateService();
        
        // Configurar remetente
        $this->fromAddress = getenv('MAIL_FROM_ADDRESS') ?: $_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@appcheckin.com.br';
        $this->fromName = getenv('MAIL_FROM_NAME') ?: $_ENV['MAIL_FROM_NAME'] ?? 'App Check-in';
        
        // Driver de email: 'resend', 'ses', 'smtp', 'sendgrid'
        $this->mailDriver = getenv('MAIL_DRIVER') ?: $_ENV['MAIL_DRIVER'] ?? 'resend';
        
        // Resend API Key
        $this->resendApiKey = getenv('RESEND_API_KEY') ?: $_ENV['RESEND_API_KEY'] ?? null;
        
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
            $appUrl = getenv('APP_URL') ?: $_ENV['APP_URL'] ?? 'https://painel.appcheckin.com.br';
            $recoveryUrl = $appUrl . '/recuperar-senha?token=' . urlencode($token);
            
            // HTML do email usando o novo template service
            $html = $this->templateService->passwordRecovery($nome, $recoveryUrl, $expirationMinutes);
            $subject = '🔐 Recuperação de Senha - App Check-in';

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
     * Enviar email de confirmação de alteração de senha
     */
    public function sendPasswordChangedEmail(string $email, string $nome, ?int $tenantId = null, ?int $usuarioId = null): bool
    {
        try {
            $html = $this->templateService->passwordChanged($nome);
            $subject = '✅ Senha Alterada com Sucesso - App Check-in';

            return $this->sendViaSMTP(
                $email, 
                $nome, 
                $subject, 
                $html,
                'password_changed',
                $tenantId,
                $usuarioId
            );
        } catch (\Exception $e) {
            error_log("Erro ao enviar email de confirmação: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Enviar email de boas-vindas
     */
    public function sendWelcomeEmail(string $email, string $nome, ?int $tenantId = null, ?int $usuarioId = null): bool
    {
        try {
            $html = $this->templateService->welcome($nome, $email);
            $subject = '🎉 Bem-vindo ao App Check-in!';

            return $this->sendViaSMTP(
                $email, 
                $nome, 
                $subject, 
                $html,
                'welcome',
                $tenantId,
                $usuarioId
            );
        } catch (\Exception $e) {
            error_log("Erro ao enviar email de boas-vindas: " . $e->getMessage());
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
            
            // Se o driver for Resend, usar a API do Resend
            if ($this->mailDriver === 'resend') {
                return $this->sendViaResend($to, $toName, $subject, $html, $logId);
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
     * Enviar via Resend API
     */
    private function sendViaResend(string $to, string $toName, string $subject, string $html, ?int $logId): bool
    {
        try {
            if (!$this->resendApiKey) {
                $errorMsg = "Resend API Key não configurada. Configure RESEND_API_KEY no .env";
                error_log($errorMsg);
                
                if ($logId && $this->emailLog) {
                    $this->emailLog->updateStatus($logId, EmailLog::STATUS_FAILED, $errorMsg);
                }
                return false;
            }

            $resend = Resend::client($this->resendApiKey);
            
            $toAddress = $toName ? "{$toName} <{$to}>" : $to;
            $fromAddress = $this->fromName ? "{$this->fromName} <{$this->fromAddress}>" : $this->fromAddress;
            
            $result = $resend->emails->send([
                'from' => $fromAddress,
                'to' => [$toAddress],
                'subject' => $subject,
                'html' => $html,
                'text' => strip_tags($html)
            ]);
            
            if ($result && $result->id) {
                error_log("Email enviado com sucesso via Resend para: {$to} (ID: {$result->id})");
                
                if ($logId && $this->emailLog) {
                    $this->emailLog->updateStatus($logId, EmailLog::STATUS_SENT, null, $result->id);
                }
                return true;
            }
            
            $errorMsg = "Resend não retornou ID de mensagem";
            error_log($errorMsg);
            
            if ($logId && $this->emailLog) {
                $this->emailLog->updateStatus($logId, EmailLog::STATUS_FAILED, $errorMsg);
            }
            return false;
            
        } catch (\Exception $e) {
            $errorMsg = $e->getMessage();
            error_log("Erro Resend: " . $errorMsg);
            
            if ($logId && $this->emailLog) {
                $this->emailLog->updateStatus($logId, EmailLog::STATUS_FAILED, $errorMsg);
            }
            return false;
        }
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
