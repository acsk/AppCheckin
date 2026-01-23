<?php

require __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$email = 'mail@appcheckin.com.br';
$senha = 'Emm@3108';

echo "Testando múltiplas configurações de SMTP...\n\n";

// Teste 1: SMTP com TLS na porta 587
echo "=== TESTE 1: TLS na porta 587 ===\n";
$mailer = new PHPMailer(true);
try {
    $mailer->isSMTP();
    $mailer->Host = 'smtp.hostinger.com';
    $mailer->SMTPAuth = true;
    $mailer->Username = $email;
    $mailer->Password = $senha;
    $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mailer->Port = 587;
    
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
    $mailer->Body = 'Email de teste.';
    
    if ($mailer->send()) {
        echo "✅ SUCESSO COM TLS/587!\n";
        exit(0);
    }
} catch (Exception $e) {
    echo "❌ Erro com TLS/587: " . $e->getMessage() . "\n\n";
}

// Teste 2: SMTP com SSL na porta 465 (já foi testado, mas tentando novamente)
echo "=== TESTE 2: SSL na porta 465 ===\n";
$mailer = new PHPMailer(true);
try {
    $mailer->isSMTP();
    $mailer->Host = 'smtp.hostinger.com';
    $mailer->SMTPAuth = true;
    $mailer->Username = $email;
    $mailer->Password = $senha;
    $mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mailer->Port = 465;
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
    $mailer->Body = 'Email de teste.';
    
    if ($mailer->send()) {
        echo "✅ SUCESSO COM SSL/465!\n";
        exit(0);
    }
} catch (Exception $e) {
    echo "❌ Erro com SSL/465: " . $e->getMessage() . "\n\n";
}

echo "❌ Nenhuma configuração funcionou. Verifique:\n";
echo "1. Se o email mail@appcheckin.com.br está ativado na Hostinger\n";
echo "2. Se SMTP está habilitado para este email\n";
echo "3. Se a senha está correta\n";
echo "4. Se há bloqueio de firewall/segurança na Hostinger\n";
