<?php

namespace App\Services;

/**
 * Serviço de templates de email
 * Templates HTML responsivos e profissionais para emails transacionais
 */
class EmailTemplateService
{
    private string $appName = 'App Check-in';
    private string $appUrl;
    private string $supportEmail = 'mail@appcheckin.com.br';
    private string $primaryColor = '#667eea';
    private string $secondaryColor = '#764ba2';
    private int $currentYear;

    public function __construct()
    {
        $this->appUrl = getenv('APP_URL') ?: $_ENV['APP_URL'] ?? 'https://appcheckin.com.br';
        $this->currentYear = (int) date('Y');
    }

    /**
     * Template de recuperação de senha
     */
    public function passwordRecovery(string $nome, string $resetUrl, int $expirationMinutes = 15): string
    {
        $content = <<<HTML
            <tr>
                <td style="padding: 40px 30px;">
                    <!-- Ícone -->
                    <div style="text-align: center; margin-bottom: 30px;">
                        <div style="display: inline-block; background: linear-gradient(135deg, {$this->primaryColor} 0%, {$this->secondaryColor} 100%); border-radius: 50%; padding: 20px; width: 60px; height: 60px;">
                            <img src="https://api.appcheckin.com.br/assets/icons/lock-reset.png" alt="Recuperar Senha" style="width: 60px; height: 60px;" onerror="this.style.display='none'">
                        </div>
                    </div>

                    <h2 style="color: #333333; font-size: 24px; font-weight: 600; margin: 0 0 20px 0; text-align: center;">
                        Recuperação de Senha
                    </h2>

                    <p style="color: #555555; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;">
                        Olá <strong>{$nome}</strong>,
                    </p>

                    <p style="color: #555555; font-size: 16px; line-height: 1.6; margin: 0 0 25px 0;">
                        Recebemos uma solicitação para redefinir a senha da sua conta no <strong>{$this->appName}</strong>. 
                        Clique no botão abaixo para criar uma nova senha:
                    </p>

                    <!-- Botão CTA -->
                    <div style="text-align: center; margin: 35px 0;">
                        <a href="{$resetUrl}" 
                           style="display: inline-block; background: linear-gradient(135deg, {$this->primaryColor} 0%, {$this->secondaryColor} 100%); color: #ffffff; font-size: 16px; font-weight: 600; text-decoration: none; padding: 16px 40px; border-radius: 8px; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);">
                            🔐 Redefinir Minha Senha
                        </a>
                    </div>

                    <!-- Aviso de expiração -->
                    <div style="background-color: #fff8e6; border-left: 4px solid #f5a623; border-radius: 4px; padding: 15px 20px; margin: 25px 0;">
                        <p style="color: #8a6d3b; font-size: 14px; margin: 0;">
                            ⏱️ <strong>Atenção:</strong> Este link é válido por apenas <strong>{$expirationMinutes} minutos</strong>. 
                            Após esse período, você precisará solicitar um novo link.
                        </p>
                    </div>

                    <!-- Link alternativo -->
                    <p style="color: #888888; font-size: 13px; line-height: 1.6; margin: 25px 0 10px 0;">
                        Se o botão não funcionar, copie e cole o link abaixo no seu navegador:
                    </p>
                    <div style="background-color: #f5f5f5; border-radius: 6px; padding: 12px 15px; word-break: break-all;">
                        <a href="{$resetUrl}" style="color: {$this->primaryColor}; font-size: 12px; text-decoration: none;">
                            {$resetUrl}
                        </a>
                    </div>

                    <!-- Aviso de segurança -->
                    <div style="background-color: #f0f4ff; border-radius: 8px; padding: 20px; margin-top: 30px;">
                        <p style="color: #555555; font-size: 14px; margin: 0 0 10px 0;">
                            <strong>🛡️ Não solicitou esta alteração?</strong>
                        </p>
                        <p style="color: #666666; font-size: 14px; line-height: 1.5; margin: 0;">
                            Se você não solicitou a recuperação de senha, ignore este email. 
                            Sua conta permanece segura e nenhuma alteração será feita.
                        </p>
                    </div>
                </td>
            </tr>
HTML;

        return $this->wrapInBaseTemplate($content, 'Recuperação de Senha');
    }

    /**
     * Template de confirmação de alteração de senha
     */
    public function passwordChanged(string $nome): string
    {
        $content = <<<HTML
            <tr>
                <td style="padding: 40px 30px;">
                    <!-- Ícone de sucesso -->
                    <div style="text-align: center; margin-bottom: 30px;">
                        <div style="display: inline-block; background: linear-gradient(135deg, #28a745 0%, #20c997 100%); border-radius: 50%; padding: 25px;">
                            <span style="font-size: 40px;">✓</span>
                        </div>
                    </div>

                    <h2 style="color: #333333; font-size: 24px; font-weight: 600; margin: 0 0 20px 0; text-align: center;">
                        Senha Alterada com Sucesso!
                    </h2>

                    <p style="color: #555555; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;">
                        Olá <strong>{$nome}</strong>,
                    </p>

                    <p style="color: #555555; font-size: 16px; line-height: 1.6; margin: 0 0 25px 0;">
                        Sua senha foi alterada com sucesso. Você já pode fazer login com sua nova senha.
                    </p>

                    <!-- Informações do evento -->
                    <div style="background-color: #f8f9fa; border-radius: 8px; padding: 20px; margin: 25px 0;">
                        <table style="width: 100%;">
                            <tr>
                                <td style="color: #666666; font-size: 14px; padding: 5px 0;">
                                    <strong>📅 Data:</strong>
                                </td>
                                <td style="color: #333333; font-size: 14px; padding: 5px 0; text-align: right;">
                                    {$this->formatDate(date('Y-m-d H:i:s'))}
                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- Aviso de segurança -->
                    <div style="background-color: #fff3cd; border-left: 4px solid #ffc107; border-radius: 4px; padding: 15px 20px; margin: 25px 0;">
                        <p style="color: #856404; font-size: 14px; margin: 0;">
                            ⚠️ <strong>Não foi você?</strong> Se você não realizou esta alteração, 
                            entre em contato imediatamente com nosso suporte: <a href="mailto:{$this->supportEmail}" style="color: #856404;">{$this->supportEmail}</a>
                        </p>
                    </div>
                </td>
            </tr>
HTML;

        return $this->wrapInBaseTemplate($content, 'Senha Alterada');
    }

    /**
     * Template de boas-vindas
     */
    public function welcome(string $nome, string $email): string
    {
        $loginUrl = $this->appUrl . '/login';
        
        $content = <<<HTML
            <tr>
                <td style="padding: 40px 30px;">
                    <!-- Ícone de boas-vindas -->
                    <div style="text-align: center; margin-bottom: 30px;">
                        <span style="font-size: 60px;">🎉</span>
                    </div>

                    <h2 style="color: #333333; font-size: 24px; font-weight: 600; margin: 0 0 20px 0; text-align: center;">
                        Bem-vindo ao {$this->appName}!
                    </h2>

                    <p style="color: #555555; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;">
                        Olá <strong>{$nome}</strong>,
                    </p>

                    <p style="color: #555555; font-size: 16px; line-height: 1.6; margin: 0 0 25px 0;">
                        Sua conta foi criada com sucesso! Estamos muito felizes em tê-lo conosco.
                    </p>

                    <!-- Dados da conta -->
                    <div style="background-color: #f8f9fa; border-radius: 8px; padding: 20px; margin: 25px 0;">
                        <p style="color: #333333; font-size: 14px; margin: 0 0 10px 0;">
                            <strong>📧 Seu email de acesso:</strong>
                        </p>
                        <p style="color: {$this->primaryColor}; font-size: 16px; margin: 0;">
                            {$email}
                        </p>
                    </div>

                    <!-- Botão de acesso -->
                    <div style="text-align: center; margin: 35px 0;">
                        <a href="{$loginUrl}" 
                           style="display: inline-block; background: linear-gradient(135deg, {$this->primaryColor} 0%, {$this->secondaryColor} 100%); color: #ffffff; font-size: 16px; font-weight: 600; text-decoration: none; padding: 16px 40px; border-radius: 8px; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);">
                            Acessar Minha Conta
                        </a>
                    </div>

                    <!-- Dicas -->
                    <div style="background-color: #e8f4fd; border-radius: 8px; padding: 20px; margin: 25px 0;">
                        <p style="color: #0c5460; font-size: 14px; margin: 0 0 15px 0;">
                            <strong>💡 Dicas para começar:</strong>
                        </p>
                        <ul style="color: #0c5460; font-size: 14px; margin: 0; padding-left: 20px;">
                            <li style="margin-bottom: 8px;">Complete seu perfil com suas informações</li>
                            <li style="margin-bottom: 8px;">Explore as turmas disponíveis</li>
                            <li>Faça seu primeiro check-in!</li>
                        </ul>
                    </div>
                </td>
            </tr>
HTML;

        return $this->wrapInBaseTemplate($content, 'Bem-vindo');
    }

    /**
     * Template base que envolve o conteúdo
     */
    private function wrapInBaseTemplate(string $content, string $preheader = ''): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{$this->appName}</title>
    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <![endif]-->
    <style>
        /* Reset */
        body, table, td, p, a, li, blockquote {
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
        }
        table, td {
            mso-table-lspace: 0pt;
            mso-table-rspace: 0pt;
        }
        img {
            -ms-interpolation-mode: bicubic;
            border: 0;
            height: auto;
            line-height: 100%;
            outline: none;
            text-decoration: none;
        }
        /* Mobile */
        @media only screen and (max-width: 600px) {
            .email-container {
                width: 100% !important;
                max-width: 100% !important;
            }
            .mobile-padding {
                padding-left: 20px !important;
                padding-right: 20px !important;
            }
        }
    </style>
</head>
<body style="margin: 0; padding: 0; background-color: #f4f4f7; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;">
    
    <!-- Preheader invisível -->
    <div style="display: none; max-height: 0px; overflow: hidden;">
        {$preheader}
    </div>
    
    <!-- Container principal -->
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #f4f4f7;">
        <tr>
            <td style="padding: 40px 20px;">
                
                <!-- Email Container -->
                <table class="email-container" role="presentation" cellspacing="0" cellpadding="0" border="0" width="600" style="margin: 0 auto; background-color: #ffffff; border-radius: 12px; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);">
                    
                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, {$this->primaryColor} 0%, {$this->secondaryColor} 100%); padding: 30px; text-align: center; border-radius: 12px 12px 0 0;">
                            <h1 style="color: #ffffff; font-size: 28px; font-weight: 700; margin: 0; letter-spacing: -0.5px;">
                                {$this->appName}
                            </h1>
                            <p style="color: rgba(255,255,255,0.8); font-size: 14px; margin: 8px 0 0 0;">
                                Sistema de Gestão de Check-ins
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Conteúdo -->
                    {$content}
                    
                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f8f9fa; padding: 30px; text-align: center; border-radius: 0 0 12px 12px; border-top: 1px solid #eaeaea;">
                            <p style="color: #999999; font-size: 13px; margin: 0 0 10px 0;">
                                Precisa de ajuda? Entre em contato:
                            </p>
                            <p style="margin: 0 0 20px 0;">
                                <a href="mailto:{$this->supportEmail}" style="color: {$this->primaryColor}; text-decoration: none; font-size: 14px; font-weight: 500;">
                                    {$this->supportEmail}
                                </a>
                            </p>
                            
                            <p style="color: #cccccc; font-size: 12px; margin: 0;">
                                © {$this->currentYear} {$this->appName}. Todos os direitos reservados.
                            </p>
                            <p style="color: #cccccc; font-size: 11px; margin: 10px 0 0 0;">
                                Este é um email automático, por favor não responda.
                            </p>
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

    /**
     * Formatar data para exibição
     */
    private function formatDate(string $date): string
    {
        $timestamp = strtotime($date);
        return date('d/m/Y \à\s H:i', $timestamp);
    }
}
