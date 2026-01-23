<?php

require __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Credenciais para testar
$email = $argv[1] ?? 'mail@appcheckin.com.br';
$senha = $argv[2] ?? 'Emm@3108';

echo "Testando SMTP com credenciais:\n";
echo "Email: $email\n";
echo "Senha: " . str_repeat('*', strlen($senha)) . "\n\n";

$mailer = new PHPMailer(true);

try {
    $mailer->isSMTP();
    $mailer->Host = 'smtp.hostinger.com';
    $mailer->SMTPAuth = true;
    $mailer->Username = $email;
    $mailer->Password = $senha;
    $mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mailer->Port = 465;
    $mailer->SMTPDebug = 2;
    
    $mailer->SMTPOptions = [
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        ]
    ];
    
    $mailer->CharSet = 'UTF-8';
    $mailer->isHTML(true);
    $mailer->setFrom($email, 'App Check-in');
    
    $mailer->addAddress('andrecabrall@gmail.com', 'Teste');
    $mailer->Subject = 'Teste SMTP - App Check-in';
    $mailer->Body = 'Este é um email de teste da configuração SMTP.';
    $mailer->AltBody = 'Este é um email de teste da configuração SMTP.';
    
    echo "\nEnviando email...\n";
    
    if ($mailer->send()) {
        echo "\n✅ EMAIL ENVIADO COM SUCESSO!\n";
        echo "Credenciais funcionam!\n";
    } else {
        echo "\n❌ ERRO ao enviar: " . $mailer->ErrorInfo . "\n";
    }
    
} catch (Exception $e) {
    echo "\n❌ ERRO: " . $e->getMessage() . "\n";
}
