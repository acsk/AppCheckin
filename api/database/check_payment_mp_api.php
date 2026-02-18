<?php
/**
 * Script para buscar pagamento na API do Mercado Pago
 * 
 * Uso: php database/check_payment_mp_api.php 146749614928
 */

try {
    $paymentId = $argv[1] ?? null;
    
    if (!$paymentId) {
        echo "❌ Uso: php database/check_payment_mp_api.php <payment_id>\n";
        exit(1);
    }
    
    echo "\n🔍 Buscando pagamento na API do Mercado Pago...\n";
    echo "Payment ID: {$paymentId}\n\n";
    
    // Ler credenciais do .env
    $envFile = __DIR__ . '/../.env';
    $env = [];
    if (file_exists($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
                list($key, $value) = explode('=', $line, 2);
                $env[trim($key)] = trim($value);
            }
        }
    }
    
    $accessToken = $env['MERCADOPAGO_ACCESS_TOKEN'] ?? getenv('MERCADOPAGO_ACCESS_TOKEN') ?? null;
    $isProduction = ($env['MERCADOPAGO_MODE'] ?? getenv('MERCADOPAGO_MODE') ?? 'sandbox') === 'production';
    
    if (!$accessToken) {
        echo "❌ Access token não encontrado no .env\n";
        exit(1);
    }
    
    echo "✅ Access Token encontrado\n";
    echo "   Modo: " . ($isProduction ? 'PRODUÇÃO' : 'SANDBOX') . "\n\n";
    
    // Buscar pagamento
    $url = "https://api.mercadopago.com/v1/payments/{$paymentId}";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer {$accessToken}",
        "Content-Type: application/json"
    ]);
    
    echo "📡 Chamando API: {$url}\n\n";
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        echo "❌ Erro CURL: " . curl_error($ch) . "\n";
        exit(1);
    }
    
    curl_close($ch);
    
    if ($httpCode !== 200) {
        echo "❌ HTTP {$httpCode}\n";
        echo "Response: {$response}\n";
        exit(1);
    }
    
    $data = json_decode($response, true);
    
    echo "✅ Pagamento encontrado na API:\n\n";
    echo "📋 DADOS DO PAGAMENTO:\n";
    echo "   ID: " . ($data['id'] ?? 'N/A') . "\n";
    echo "   Status: " . ($data['status'] ?? 'N/A') . "\n";
    echo "   Status Detail: " . ($data['status_detail'] ?? 'N/A') . "\n";
    echo "   Valor: R$ " . ($data['transaction_amount'] ?? 'N/A') . "\n";
    echo "   Data Criação: " . ($data['date_created'] ?? 'N/A') . "\n";
    echo "   Data Aprovação: " . ($data['date_approved'] ?? 'N/A') . "\n";
    echo "   Tipo Pagamento: " . ($data['payment_type_id'] ?? 'N/A') . "\n";
    echo "   Método Pagamento: " . ($data['payment_method_id'] ?? 'N/A') . "\n\n";
    
    echo "📌 REFERÊNCIAS:\n";
    echo "   External Reference: " . ($data['external_reference'] ?? '❌ VAZIO') . "\n";
    echo "   Preference ID: " . ($data['preference_id'] ?? 'N/A') . "\n\n";
    
    if (!empty($data['metadata'])) {
        echo "📦 METADATA:\n";
        foreach ($data['metadata'] as $key => $value) {
            echo "   {$key}: {$value}\n";
        }
        echo "\n";
    }
    
    echo "💳 PAGADOR:\n";
    echo "   Email: " . ($data['payer']['email'] ?? 'N/A') . "\n";
    echo "   ID: " . ($data['payer']['id'] ?? 'N/A') . "\n\n";
    
    // Se external_reference está vazio, isso é o problema
    if (empty($data['external_reference'])) {
        echo "⚠️ ⚠️ ⚠️ PROBLEMA IDENTIFICADO ⚠️ ⚠️ ⚠️\n";
        echo "O pagamento foi criado SEM external_reference!\n";
        echo "Isso significa que a preferência de checkout não tinha um external_reference definido.\n\n";
        
        echo "📝 CHECKLIST:\n";
        echo "1. Verificar se pagarPacote() está setando external_reference\n";
        echo "2. Verificar se criarPreferenciaPagamento() está passando external_reference\n";
        echo "3. Verificar o valor de PAC-{contratoId}-{timestamp}\n\n";
    }
    
    echo "✅ Análise completa\n\n";
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    exit(1);
}
