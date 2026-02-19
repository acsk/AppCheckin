<?php
/**
 * Script para investigar qual payment ID vem no webhook subscription_authorized_payment
 * e o que o MP retorna quando consultamos esse payment
 */

require __DIR__ . '/vendor/autoload.php';

$dotenv = new \Dotenv\Dotenv(__DIR__);
$dotenv->load();

// ID do último payment do webhook
$payment_id = '7025650338';  // Do webhook que vimos: "id":"7025650338"

$mp_token = getenv('MERCADOPAGO_ACCESS_TOKEN');
if (!$mp_token) {
    echo "❌ MERCADOPAGO_ACCESS_TOKEN não configurado\n";
    exit(1);
}

echo "🔍 Investigando payment ID: $payment_id\n";
echo "───────────────────────────────────────────────────────────────\n\n";

// Fazer requisição ao MP
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://api.mercadopago.com/v1/payments/$payment_id");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer $mp_token",
    "Content-Type: application/json"
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code === 200) {
    $payment = json_decode($response, true);
    
    echo "✅ Payment encontrado no MP!\n\n";
    echo "=== DADOS DO PAYMENT ===\n";
    echo json_encode($payment, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
    
    // Campos importantes
    echo "🔑 CAMPOS IMPORTANTES:\n";
    echo "  - ID: " . ($payment['id'] ?? 'N/A') . "\n";
    echo "  - Status: " . ($payment['status'] ?? 'N/A') . "\n";
    echo "  - External Reference: " . ($payment['external_reference'] ?? 'NULL') . "\n";
    echo "  - Valor: " . ($payment['transaction_amount'] ?? 'N/A') . "\n";
    echo "  - Payer ID: " . ($payment['payer']['id'] ?? 'NULL') . "\n";
    echo "  - Metadata: " . json_encode($payment['metadata'] ?? []) . "\n";
    
    // Procurar por campos relacionados a assinatura/preapproval
    echo "\n📋 Procurando referências a assinatura/preapproval:\n";
    foreach ($payment as $key => $value) {
        if (stripos($key, 'subscription') !== false || 
            stripos($key, 'preapproval') !== false ||
            stripos($key, 'recurring') !== false) {
            echo "  - $key: " . json_encode($value) . "\n";
        }
    }
    
} else {
    echo "❌ Erro ao buscar payment: HTTP $http_code\n";
    echo $response . "\n";
}
