<?php
/**
 * Script de teste para Amazon SES
 * Execute: php test_ses.php
 */

require __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Carregar variáveis de ambiente
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value, " \t\n\r\0\x0B\"'");
        putenv("$key=$value");
        $_ENV[$key] = $value;
    }
}

echo "╔══════════════════════════════════════════════════════════╗\n";
echo "║           TESTE DE EMAIL - AMAZON SES                     ║\n";
echo "╚══════════════════════════════════════════════════════════╝\n\n";

// Configurações
$host = getenv('MAIL_HOST') ?: 'email-smtp.us-east-1.amazonaws.com';
$port = (int)(getenv('MAIL_PORT') ?: 587);
$username = getenv('MAIL_USERNAME');
$password = getenv('MAIL_PASSWORD');
$encryption = getenv('MAIL_ENCRYPTION') ?: 'tls';
$fromAddress = getenv('MAIL_FROM_ADDRESS') ?: 'noreply@appcheckin.com.br';
$fromName = getenv('MAIL_FROM_NAME') ?: 'App Check-in';

echo "📧 Configurações atuais:\n";
echo "   Host: {$host}\n";
echo "   Porta: {$port}\n";
echo "   Username: " . ($username ? substr($username, 0, 10) . '...' : '❌ NÃO CONFIGURADO') . "\n";
echo "   Password: " . ($password ? '****' : '❌ NÃO CONFIGURADO') . "\n";
echo "   Encryption: {$encryption}\n";
echo "   From: {$fromAddress} ({$fromName})\n\n";

if (!$username || !$password) {
    echo "❌ ERRO: Configure MAIL_USERNAME e MAIL_PASSWORD no arquivo .env\n\n";
    echo "📋 Como obter credenciais SMTP do Amazon SES:\n";
    echo "   1. Acesse o Console AWS: https://console.aws.amazon.com/ses\n";
    echo "   2. No menu lateral, clique em 'SMTP settings'\n";
    echo "   3. Clique em 'Create SMTP credentials'\n";
    echo "   4. Copie o SMTP username e SMTP password gerados\n";
    echo "   5. Adicione no seu .env:\n";
    echo "      MAIL_USERNAME=seu_smtp_username\n";
    echo "      MAIL_PASSWORD=sua_smtp_password\n\n";
    exit(1);
}

// Email de destino
$toEmail = $argv[1] ?? 'andrecabrall@gmail.com';
echo "📬 Enviando email de teste para: {$toEmail}\n\n";

$mailer = new PHPMailer(true);

try {
    // Debug
    $mailer->SMTPDebug = 2;
    $mailer->Debugoutput = function($str, $level) {
        echo "   [{$level}] {$str}\n";
    };
    
    // Configuração SMTP
    $mailer->isSMTP();
    $mailer->Host = $host;
    $mailer->SMTPAuth = true;
    $mailer->Username = $username;
    $mailer->Password = $password;
    $mailer->Port = $port;
    
    if ($encryption === 'tls') {
        $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    } elseif ($encryption === 'ssl') {
        $mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    }
    
    $mailer->CharSet = 'UTF-8';
    $mailer->isHTML(true);
    
    // Email
    $mailer->setFrom($fromAddress, $fromName);
    $mailer->addAddress($toEmail);
    $mailer->Subject = '✅ Teste Amazon SES - App Check-in';
    $mailer->Body = '
    <html>
    <body style="font-family: Arial, sans-serif; padding: 20px;">
        <h2 style="color: #232f3e;">🎉 Amazon SES Configurado com Sucesso!</h2>
        <p>Este é um email de teste enviado pelo <strong>App Check-in</strong> usando Amazon SES.</p>
        <p>Se você está vendo este email, a configuração está funcionando corretamente.</p>
        <hr style="border: 1px solid #eee; margin: 20px 0;">
        <p style="color: #666; font-size: 12px;">
            Enviado em: ' . date('d/m/Y H:i:s') . '<br>
            Host: ' . $host . '<br>
            Região: ' . (preg_match('/email-smtp\.([^.]+)\.amazonaws/', $host, $m) ? $m[1] : 'N/A') . '
        </p>
    </body>
    </html>';
    $mailer->AltBody = 'Amazon SES configurado com sucesso! Este é um email de teste do App Check-in.';
    
    echo "\n🚀 Tentando enviar...\n\n";
    
    if ($mailer->send()) {
        echo "\n╔══════════════════════════════════════════════════════════╗\n";
        echo "║  ✅ EMAIL ENVIADO COM SUCESSO!                           ║\n";
        echo "╚══════════════════════════════════════════════════════════╝\n";
        echo "\n📬 Verifique a caixa de entrada de: {$toEmail}\n";
        echo "   (Confira também a pasta de spam)\n\n";
    }
    
} catch (Exception $e) {
    echo "\n╔══════════════════════════════════════════════════════════╗\n";
    echo "║  ❌ ERRO AO ENVIAR EMAIL                                  ║\n";
    echo "╚══════════════════════════════════════════════════════════╝\n";
    echo "\nErro: " . $mailer->ErrorInfo . "\n\n";
    
    echo "🔍 Possíveis causas:\n";
    echo "   1. Credenciais SMTP incorretas\n";
    echo "   2. Email remetente não verificado no SES\n";
    echo "   3. Conta SES ainda em sandbox (só envia para emails verificados)\n";
    echo "   4. Região do SES incorreta no MAIL_HOST\n\n";
    
    echo "📋 Verifique:\n";
    echo "   - Se o email '{$fromAddress}' está verificado no SES\n";
    echo "   - Se sua conta SES está fora do sandbox\n";
    echo "   - Se as credenciais SMTP estão corretas\n\n";
}
