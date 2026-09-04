<?php

namespace App\Services;

/**
 * Templates HTML transacionais (portados da API Slim).
 */
class EmailTemplateService
{
    private string $appName;

    private string $appUrl;

    private string $supportEmail;

    private string $primaryColor = '#fd8a05';

    private string $secondaryColor = '#ec5903';

    private int $currentYear;

    public function __construct()
    {
        $this->appName = (string) config('app.name', 'App Check-in');
        $this->appUrl = (string) config('app.url', 'https://appcheckin.com.br');
        $this->supportEmail = (string) config('appcheckin.mail_from_address', config('mail.from.address', 'mail@appcheckin.com.br'));
        $this->currentYear = (int) date('Y');
    }

    public function passwordRecovery(string $nome, string $token, int $expirationMinutes = 60): string
    {
        $nomeEsc = htmlspecialchars($nome, ENT_QUOTES, 'UTF-8');
        $tokenEsc = htmlspecialchars($token, ENT_QUOTES, 'UTF-8');

        $content = <<<HTML
            <tr>
                <td style="padding: 40px 30px;">
                    <div style="text-align: center; margin-bottom: 30px;">
                        <div style="display: inline-block; background: linear-gradient(135deg, {$this->primaryColor} 0%, {$this->secondaryColor} 100%); border-radius: 50%; padding: 25px;">
                            <span style="font-size: 40px;">🔐</span>
                        </div>
                    </div>

                    <h2 style="color: #333333; font-size: 24px; font-weight: 600; margin: 0 0 20px 0; text-align: center;">
                        Recuperação de Senha
                    </h2>

                    <p style="color: #555555; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;">
                        Olá <strong>{$nomeEsc}</strong>,
                    </p>

                    <p style="color: #555555; font-size: 16px; line-height: 1.6; margin: 0 0 25px 0;">
                        Recebemos uma solicitação para redefinir a senha da sua conta no <strong>{$this->appName}</strong>.
                        Use o código abaixo no aplicativo para criar uma nova senha:
                    </p>

                    <div style="text-align: center; margin: 35px 0;">
                        <p style="color: #888888; font-size: 14px; margin: 0 0 15px 0;">Seu código de recuperação:</p>
                        <div style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border: 2px dashed {$this->primaryColor}; border-radius: 12px; padding: 25px 20px; display: inline-block; min-width: 280px;">
                            <span style="font-family: 'Courier New', monospace; font-size: 18px; font-weight: 700; color: #333333; letter-spacing: 1px; word-break: break-all;">
                                {$tokenEsc}
                            </span>
                        </div>
                    </div>

                    <div style="background-color: #fff8e6; border-left: 4px solid #f5a623; border-radius: 4px; padding: 15px 20px; margin: 25px 0;">
                        <p style="color: #8a6d3b; font-size: 14px; margin: 0;">
                            Este código é válido por <strong>{$expirationMinutes} minutos</strong>.
                        </p>
                    </div>

                    <div style="background-color: #f0f4ff; border-radius: 8px; padding: 20px; margin-top: 30px;">
                        <p style="color: #666666; font-size: 14px; line-height: 1.5; margin: 0;">
                            Se você não solicitou a recuperação de senha, ignore este email. Sua conta permanece segura.
                        </p>
                    </div>
                </td>
            </tr>
HTML;

        return $this->wrapInBaseTemplate($content, 'Recuperação de Senha');
    }

    public function passwordRecoveryPlainText(string $nome, string $token, int $expirationMinutes = 60): string
    {
        return <<<TEXT
Recuperação de Senha — {$this->appName}

Olá {$nome},

Use o código abaixo no aplicativo para criar uma nova senha:

{$token}

Este código expira em {$expirationMinutes} minutos.

Se você não solicitou esta alteração, ignore este email.

Suporte: {$this->supportEmail}
© {$this->currentYear} {$this->appName}
TEXT;
    }

    public function welcomeAluno(string $nome, string $email, string $cpf): string
    {
        $nomeEsc = htmlspecialchars($nome, ENT_QUOTES, 'UTF-8');
        $emailEsc = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
        $cpfEsc = htmlspecialchars($cpf, ENT_QUOTES, 'UTF-8');
        $appUrlEsc = htmlspecialchars($this->appUrl, ENT_QUOTES, 'UTF-8');

        $content = <<<HTML
            <tr>
                <td style="padding: 40px 30px;">
                    <div style="text-align: center; margin-bottom: 30px;">
                        <span style="font-size: 60px;">🎉</span>
                    </div>

                    <h2 style="color: #333333; font-size: 24px; font-weight: 600; margin: 0 0 20px 0; text-align: center;">
                        Bem-vindo ao AppCheckin!
                    </h2>

                    <p style="color: #555555; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;">
                        Olá <strong>{$nomeEsc}</strong>,
                    </p>

                    <p style="color: #555555; font-size: 16px; line-height: 1.6; margin: 0 0 25px 0;">
                        Seu cadastro foi realizado com sucesso. Use os dados abaixo para acessar o app:
                    </p>

                    <div style="background-color: #f8f9fa; border-radius: 8px; padding: 20px; margin: 25px 0;">
                        <p style="color: #333333; font-size: 14px; margin: 0 0 8px 0;"><strong>Email:</strong> {$emailEsc}</p>
                        <p style="color: #333333; font-size: 14px; margin: 0;"><strong>Senha inicial:</strong> seu CPF ({$cpfEsc})</p>
                    </div>

                    <div style="text-align: center; margin: 35px 0;">
                        <a href="{$appUrlEsc}" style="display: inline-block; background: linear-gradient(135deg, {$this->primaryColor} 0%, {$this->secondaryColor} 100%); color: #ffffff; font-size: 16px; font-weight: 600; text-decoration: none; padding: 16px 40px; border-radius: 8px;">
                            Acessar AppCheckin
                        </a>
                    </div>
                </td>
            </tr>
HTML;

        return $this->wrapInBaseTemplate($content, 'Bem-vindo ao AppCheckin');
    }

    public function welcomeAlunoPlainText(string $nome, string $email, string $cpf): string
    {
        return <<<TEXT
Bem-vindo ao AppCheckin!

Olá {$nome},

Seu cadastro foi realizado com sucesso.

Email: {$email}
Senha inicial: seu CPF ({$cpf})

Acesse: {$this->appUrl}

Suporte: {$this->supportEmail}
© {$this->currentYear} {$this->appName}
TEXT;
    }

    private function wrapInBaseTemplate(string $content, string $preheader = ''): string
    {
        $preheaderEsc = htmlspecialchars($preheader, ENT_QUOTES, 'UTF-8');

        return <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$this->appName}</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f4f4f7; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;">
    <div style="display: none; max-height: 0; overflow: hidden;">{$preheaderEsc}</div>
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #f4f4f7;">
        <tr>
            <td style="padding: 40px 20px;">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="600" style="margin: 0 auto; background-color: #ffffff; border-radius: 12px;">
                    <tr>
                        <td style="background: linear-gradient(135deg, {$this->primaryColor} 0%, {$this->secondaryColor} 100%); padding: 30px; text-align: center; border-radius: 12px 12px 0 0;">
                            <h1 style="color: #ffffff; font-size: 28px; font-weight: 700; margin: 0;">{$this->appName}</h1>
                        </td>
                    </tr>
                    {$content}
                    <tr>
                        <td style="background-color: #f8f9fa; padding: 30px; text-align: center; border-radius: 0 0 12px 12px;">
                            <p style="color: #999999; font-size: 13px; margin: 0 0 10px 0;">Precisa de ajuda?</p>
                            <p style="margin: 0 0 20px 0;">
                                <a href="mailto:{$this->supportEmail}" style="color: {$this->primaryColor}; text-decoration: none;">{$this->supportEmail}</a>
                            </p>
                            <p style="color: #cccccc; font-size: 12px; margin: 0;">© {$this->currentYear} {$this->appName}</p>
                            <p style="color: #cccccc; font-size: 11px; margin: 10px 0 0 0;">Email automático — não responda.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }
}
