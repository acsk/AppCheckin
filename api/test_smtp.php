<?php

require __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mailer = new PHPMailer(true);

try {
    // Configuração SMTP
    echo "Testando conexão SMTP...\n";
    
    $mailer->isSMTP();
    $mailer->Host = 'smtp.hostinger.com';
    $mailer->SMTPAuth = true;
    $mailer->Username = 'mail@appcheckin.com.br';
    $mailer->Password = 'Emm@3108';
    $mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mailer->Port = 465;
    $mailer->SMTPDebug = 2; // Verbose debug
    
    // SSL options
    $mailer->SMTPOptions = [
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        ]
    ];
    
    $mailer->CharSet = 'UTF-8';
    $mailer->isHTML(true);
    
    echo "\n✓ Conectando ao servidor SMTP...\n";
    $mailer->setFrom('mail@appcheckin.com.br', 'App Check-in');
    
    echo "✓ Configurações aplicadas\n";
    echo "✓ Pronto para enviar email\n\n";
    
    // Enviar email de teste
    $mailer->addAddress('andrecabrall@gmail.com', 'Teste');
    $mailer->Subject = 'Teste SMTP - App Check-in';
    $mailer->Body = 'Este é um email de teste da configuração SMTP.';
    $mailer->AltBody = 'Este é um email de teste da configuração SMTP.';
    
    if ($mailer->send()) {
        echo "✅ EMAIL ENVIADO COM SUCESSO!\n";
        echo "Email foi para: andrecabrall@gmail.com\n";
    } else {
        echo "❌ ERRO ao enviar: " . $mailer->ErrorInfo . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
    echo "Debug: " . $mailer->ErrorInfo . "\n";
}
