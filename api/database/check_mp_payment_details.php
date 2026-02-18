<?php
/**
 * Verificar detalhes completos de um payment no Mercado Pago
 * Script para debugar quais metadados foram enviados
 */

// Configuração
$paymentId = 146065563049;
$accessToken = 'APP_USR-5463428115477491-020510-9307ab7d667f2330239a33d35886e52f-195078879';
$environment = 'production'; // ou 'sandbox'

$apiBaseUrl = $environment === 'production' 
    ? 'https://api.mercadopago.com'
    : 'https://sandbox.mercadopago.com';

echo "=== VERIFICANDO PAYMENT #{$paymentId} NO MERCADO PAGO ===\n\n";
echo "Ambiente: {$environment}\n";
echo "URL: {$apiBaseUrl}/v1/payments/{$paymentId}\n\n";

// Fazer requisição
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => "{$apiBaseUrl}/v1/payments/{$paymentId}",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json'
    ],
    CURLOPT_TIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => true
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    echo "❌ ERRO HTTP {$httpCode}\n";
    echo "Response: {$response}\n";
    exit(1);
}

$payment = json_decode($response, true);

if (!$payment) {
    echo "❌ Erro ao fazer parse do JSON\n";
    exit(1);
}

echo "=== DADOS DO PAGAMENTO ===\n";
echo "ID: {$payment['id']}\n";
echo "Status: {$payment['status']}\n";
echo "Status Detail: {$payment['status_detail']}\n";
echo "External Reference: {$payment['external_reference'] ?? 'N/A'}\n";
echo "Transaction Amount: {$payment['transaction_amount']}\n";
echo "Date Created: {$payment['date_created']}\n";
echo "Date Approved: {$payment['date_approved'] ?? 'N/A'}\n";

echo "\n=== METADATA (CRÍTICO!) ===\n";
if (empty($payment['metadata'])) {
    echo "❌ METADATA VAZIA OU NÃO EXISTE!\n";
    echo "   Payment nos metadados: " . json_encode($payment['metadata'] ?? []) . "\n";
} else {
    echo "✅ METADATA ENCONTRADA:\n";
    echo json_encode($payment['metadata'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
}

echo "\n=== PREFERENCE ID ===\n";
echo "Preference ID: {$payment['preference_id'] ?? 'N/A'}\n";

// Se tiver preference_id, buscar a preferência também
if (!empty($payment['preference_id'])) {
    echo "\n=== BUSCANDO DETALHES DA PREFERENCE ===\n";
    $prefId = $payment['preference_id'];
    
    $ch2 = curl_init();
    curl_setopt_array($ch2, [
        CURLOPT_URL => "{$apiBaseUrl}/checkout/preferences/{$prefId}",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json'
        ],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true
    ]);
    
    $prefResponse = curl_exec($ch2);
    $prefHttpCode = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
    curl_close($ch2);
    
    if ($prefHttpCode === 200) {
        $preference = json_decode($prefResponse, true);
        echo "✅ Preference encontrada:\n";
        echo "ID: {$preference['id']}\n";
        echo "External Reference: {$preference['external_reference']}\n";
        
        echo "\n--- METADATA DA PREFERENCE ---\n";
        if (!empty($preference['metadata'])) {
            echo json_encode($preference['metadata'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        } else {
            echo "❌ METADATA VAZIA NA PREFERENCE!\n";
        }
    } else {
        echo "❌ Erro ao buscar preference ({$prefHttpCode})\n";
    }
}

echo "\n=== ANÁLISE ===\n";
if (empty($payment['metadata'])) {
    echo "PROBLEMA: O payment NÃO tem metadata.\n";
    echo "Isso significa que a preference foi criada SEM os metadados ou\n";
    echo "o Mercado Pago não retorna metadata em payments?\n";
    echo "\nSOLUÇÃO: Verificar preference_id para ver se os dados estão lá.\n";
} else if (empty($payment['metadata']['pacote_contrato_id'])) {
    echo "PROBLEMA: Metadata existe mas NÃO tem 'pacote_contrato_id'\n";
    echo "Isso significa que metadata_extra não foi passado ao criar a preference.\n";
}
?>
