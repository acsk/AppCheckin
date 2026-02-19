#!/usr/bin/env php
<?php
/**
 * Verificar logs de webhook e tentativas de acesso
 */

$logFile = '/home/u304177849/domains/appcheckin.com.br/public_html/storage/logs/webhook_mercadopago.log';
$errorLog = '/var/log/php/u304177849-error.log';

echo "🔍 VERIFICANDO LOGS DE WEBHOOK\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Verificar arquivo de log dedicado do webhook
if (file_exists($logFile)) {
    echo "✅ Arquivo webhook_mercadopago.log encontrado\n";
    $lines = file($logFile);
    $lastLines = array_slice($lines, -20);
    
    echo "\n📋 ÚLTIMAS 20 LINHAS:\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    foreach ($lastLines as $line) {
        echo trim($line) . "\n";
    }
} else {
    echo "❌ Arquivo webhook_mercadopago.log NÃO encontrado\n";
    echo "   Caminho esperado: {$logFile}\n";
}

echo "\n\n🔍 PROCURANDO WEBHOOKS EM ERROS DO SISTEMA\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

// Verificar error_log PHP
$cmd = "tail -50 {$errorLog} 2>/dev/null | grep -i 'webhook\\|mercadopago\\|/api/webhooks' || echo 'Nenhum erro relacionado encontrado'";
echo shell_exec($cmd);

echo "\n\n📊 INTERPRETAÇÃO:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "✅ Se vir linhas com 'WEBHOOK MERCADO PAGO' → webhooks foram recebidos\n";
echo "❌ Se nada aparecer → MP não está enviando webhooks\n";
echo "\n💡 PRÓXIMO PASSO:\n";
echo "   1. Ir para https://www.mercadopago.com.br/developers/pt/docs/checkout-pro/notifications/webhooks\n";
echo "   2. Verificar se webhook URL está registrada em: https://www.mercadopago.com/developers/panel\n";
echo "   3. A URL deve ser: https://appcheckin.com.br/api/webhooks/mercadopago\n";
?>
