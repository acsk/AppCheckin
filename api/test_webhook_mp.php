<?php
/**
 * Script de teste para simular webhook de pagamento do Mercado Pago
 * 
 * Uso:
 * php test_webhook_mp.php [external_reference] [status] [payment_type]
 * 
 * Exemplos:
 * php test_webhook_mp.php MAT-158-1771524282 approved credit_card
 * php test_webhook_mp.php MAT-1-1708380000 approved pix
 * php test_webhook_mp.php PAC-5-1708380000 approved credit_card
 */

// Parâmetros da linha de comando
$externalReference = $argv[1] ?? 'MAT-1-' . time();
$status = $argv[2] ?? 'approved';
$paymentType = $argv[3] ?? 'credit_card';

// URL do endpoint de teste
// Tentar usar localhost em primeiro lugar (para servidor local)
// Se estiver em produção com domínio, usar: https://appcheckin.com.br/api
$baseUrl = 'http://localhost:8000/api';
if (!empty($_ENV['APP_URL'])) {
    $baseUrl = $_ENV['APP_URL'] . '/api';
} elseif (getenv('APP_URL')) {
    $baseUrl = getenv('APP_URL') . '/api';
}

$testUrl = "{$baseUrl}/webhooks/mercadopago/test";

// Montar URL com query parameters
$queryParams = [
    'external_reference' => $externalReference,
    'status' => $status,
    'payment_type' => $paymentType
];

$fullUrl = $testUrl . '?' . http_build_query($queryParams);

echo "\n╔════════════════════════════════════════════════════════════╗\n";
echo "║         TESTE DE WEBHOOK MERCADO PAGO                      ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

echo "📋 Parâmetros:\n";
echo "   External Reference: {$externalReference}\n";
echo "   Status: {$status}\n";
echo "   Payment Type: {$paymentType}\n";
echo "   URL: {$fullUrl}\n\n";

echo "🔄 Enviando requisição...\n\n";

// Fazer requisição usando curl
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $fullUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

// Headers
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// Processar resposta
if ($curlError) {
    echo "❌ ERRO na requisição:\n";
    echo "   {$curlError}\n\n";
    exit(1);
}

echo "✅ Resposta HTTP: {$httpCode}\n\n";

// Decodificar e exibir JSON
$responseData = json_decode($response, true);

if ($responseData) {
    echo "📊 Resposta da API:\n";
    echo json_encode($responseData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
    
    // Verificar sucesso
    if (!empty($responseData['success'])) {
        echo "✅ ✅ ✅ WEBHOOK SIMULADO COM SUCESSO! ✅ ✅ ✅\n\n";
        
        if (!empty($responseData['data'])) {
            echo "📌 Detalhes:\n";
            foreach ($responseData['data'] as $key => $value) {
                echo "   {$key}: {$value}\n";
            }
        }
    } else {
        echo "⚠️  Resposta retornou success=false\n";
        if (!empty($responseData['error'])) {
            echo "   Erro: {$responseData['error']}\n";
        }
    }
} else {
    echo "❌ Erro ao decodificar resposta JSON\n";
    echo "Resposta bruta:\n{$response}\n";
}

echo "\n";
?>
