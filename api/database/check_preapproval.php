<?php
/**
 * Verificar se existe PREAPPROVAL vinculado ao pagamento
 */

$paymentId = $argv[1] ?? null;

if (!$paymentId) {
    echo "❌ Uso: php database/check_preapproval.php <payment_id>\n";
    exit(1);
}

try {
    // Ler credenciais
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
    
    $mpEnv = $env['MP_ENVIRONMENT'] ?? 'sandbox';
    $accessToken = $mpEnv === 'production' 
        ? ($env['MP_ACCESS_TOKEN_PROD'] ?? null)
        : ($env['MP_ACCESS_TOKEN_TEST'] ?? null);
    
    echo "🔍 Verificando se payment {$paymentId} tem preapproval vinculado...\n\n";
    
    // Buscar pagamento
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.mercadopago.com/v1/payments/{$paymentId}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer {$accessToken}",
        "Content-Type: application/json"
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $pagamento = json_decode($response, true);
    
    echo "📋 DADOS DO PAGAMENTO:\n";
    echo "   ID: {$pagamento['id']}\n";
    echo "   Status: {$pagamento['status']}\n";
    echo "   External Reference: " . ($pagamento['external_reference'] ?? 'NULL') . "\n";
    echo "   Valor: R$ {$pagamento['transaction_amount']}\n\n";
    
    // Procurar por preapproval_id em qualquer lugar da resposta
    echo "🔍 Procurando referência a PREAPPROVAL na resposta...\n\n";
    
    $haystack = json_encode($pagamento);
    if (strpos($haystack, 'preapproval') !== false) {
        echo "✅ Encontrou 'preapproval' na resposta!\n";
        
        // Procurar campos específicos
        if (!empty($pagamento['preapproval_id'])) {
            echo "   preapproval_id: {$pagamento['preapproval_id']}\n";
        }
        if (!empty($pagamento['subscription_id'])) {
            echo "   subscription_id: {$pagamento['subscription_id']}\n";
        }
        if (!empty($pagamento['processing_mode']) && strpos($pagamento['processing_mode'], 'preapproval') !== false) {
            echo "   processing_mode: {$pagamento['processing_mode']}\n";
        }
    } else {
        echo "❌ Nenhuma referência a preapproval encontrada\n";
    }
    
    echo "\n📦 FULL RESPONSE FIELDS:\n";
    foreach ($pagamento as $key => $value) {
        $valStr = is_array($value) ? json_encode($value) : $value;
        if (strlen($valStr) > 100) {
            $valStr = substr($valStr, 0, 100) . '...';
        }
        echo "   {$key}: {$valStr}\n";
    }
    
} catch (\Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    exit(1);
}
