<?php
/**
 * Script de teste para envio de email via Resend
 */

require_once __DIR__ . '/vendor/autoload.php';

// Carregar variáveis de ambiente
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        
        // Parse com suporte a aspas
        if (preg_match('/^([^=]+)=(.*)$/', $line, $matches)) {
            $key = trim($matches[1]);
            $value = trim($matches[2]);
            
            // Remover aspas
            if (preg_match('/^"(.*)"$/', $value, $m)) {
                $value = $m[1];
            } elseif (preg_match("/^'(.*)'$/", $value, $m)) {
                $value = $m[1];
            }
            
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }
}

use Resend;

echo "=== Teste de Envio via Resend ===\n\n";

// Configurações
$apiKey = getenv('RESEND_API_KEY') ?: $_ENV['RESEND_API_KEY'] ?? null;
$fromAddress = getenv('MAIL_FROM_ADDRESS') ?: $_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@appcheckin.com.br';
$fromName = getenv('MAIL_FROM_NAME') ?: $_ENV['MAIL_FROM_NAME'] ?? 'App Check-in';

echo "API Key: " . ($apiKey ? substr($apiKey, 0, 10) . '...' : 'NÃO CONFIGURADA') . "\n";
echo "From: {$fromName} <{$fromAddress}>\n\n";

if (!$apiKey) {
    die("❌ RESEND_API_KEY não configurada no .env\n");
}

// Destinatário para teste - altere para seu email
$testEmail = $argv[1] ?? 'teste@exemplo.com';

echo "Enviando email de teste para: {$testEmail}\n\n";

try {
    $resend = Resend::client($apiKey);
    
    $from = $fromName ? "{$fromName} <{$fromAddress}>" : $fromAddress;
    
    $result = $resend->emails->send([
        'from' => $from,
        'to' => [$testEmail],
        'subject' => '✅ Teste Resend - App Check-in',
        'html' => '
            <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
                <h1 style="color: #667eea;">🎉 Teste de Email</h1>
                <p>Este é um email de teste enviado via <strong>Resend</strong>.</p>
                <p>Se você está recebendo esta mensagem, a integração está funcionando corretamente!</p>
                <hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">
                <p style="color: #999; font-size: 12px;">App Check-in - ' . date('d/m/Y H:i:s') . '</p>
            </div>
        ',
        'text' => 'Este é um email de teste enviado via Resend. Se você está recebendo esta mensagem, a integração está funcionando corretamente!'
    ]);
    
    if (isset($result->id)) {
        echo "✅ Email enviado com sucesso!\n";
        echo "   Message ID: {$result->id}\n";
    } else {
        echo "⚠️ Email enviado, mas sem ID de confirmação\n";
        print_r($result);
    }
    
} catch (Exception $e) {
    echo "❌ Erro ao enviar email:\n";
    echo "   " . $e->getMessage() . "\n";
}
