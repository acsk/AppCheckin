<?php
/**
 * Tentar buscar authorized_payment no MP
 * SEM COMPOSER (compatível com PHP 7.4)
 */

// Carregar .env
$env_file = __DIR__ . '/.env';
$env_vars = [];
if (file_exists($env_file)) {
    foreach (file($env_file) as $line) {
        $line = trim($line);
        if (empty($line) || $line[0] === '#') continue;
        
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $key = trim($parts[0]);
            $value = trim($parts[1], '\'"');
            $env_vars[$key] = $value;
        }
    }
}

// Determinar token
$environment = $env_vars['MP_ENVIRONMENT'] ?? 'test';
$token_key = ($environment === 'prod') ? 'MP_ACCESS_TOKEN_PROD' : 'MP_ACCESS_TOKEN_TEST';
$mp_token = $env_vars[$token_key] ?? null;

if (!$mp_token) {
    echo "❌ Token MP não configurado\n";
    exit(1);
}

echo "🔍 Investigando authorized_payment do MP\n";
echo "───────────────────────────────────────────────────────────────\n\n";

// ID do authorized_payment do webhook
$authorized_payment_id = '129110623059';

echo "1️⃣ Tentando buscar authorized_payment por ID: {$authorized_payment_id}\n";
echo "   (Este é o 'id' do webhook subscription_authorized_payment)\n\n";

// Tentar endpoint de authorized_payments
$endpoints = [
    "https://api.mercadopago.com/v1/authorized_payments/{$authorized_payment_id}",
    "https://api.mercadopago.com/authorized_payments/{$authorized_payment_id}"
];

foreach ($endpoints as $url) {
    echo "📍 Tentando: {$url}\n";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $mp_token",
        "Content-Type: application/json"
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        $data = json_decode($response, true);
        echo "✅ ENCONTRADO!\n\n";
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
        
        // Procurar preapproval_id
        echo "🔑 Procurando preapproval_id...\n";
        if (isset($data['preapproval_id'])) {
            echo "✅ Encontrado: {$data['preapproval_id']}\n";
        } else {
            echo "❌ preapproval_id não encontrado neste endpoint\n";
            echo "Chaves disponíveis: " . implode(", ", array_keys($data)) . "\n";
        }
        
        exit(0);
    }
    
    echo "❌ HTTP {$http_code}\n\n";
}

// Se nenhum endpoint funcionou
echo "2️⃣ Nenhum endpoint de authorized_payments funcionou.\n";
echo "   Tentaremos buscar preapprovals para extrair a relação.\n\n";

// Buscar preapprovals para encontrar qual gerou este authorized_payment
echo "📍 Buscando preapprovals para encontrar qual gerou este authorized_payment...\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://api.mercadopago.com/v1/preapprovals");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer $mp_token",
    "Content-Type: application/json"
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code === 200) {
    $result = json_decode($response, true);
    echo "✅ Preapprovals encontrados: " . count($result['items'] ?? []) . "\n\n";
    
    // Listar os primeiros 5
    if (isset($result['items'])) {
        echo "Primeiros 5 preapprovals:\n";
        foreach (array_slice($result['items'], 0, 5) as $i => $preapproval) {
            echo "\n{$i}. ID: " . $preapproval['id'] . "\n";
            echo "   Status: " . ($preapproval['status'] ?? 'N/A') . "\n";
            echo "   Payer: " . ($preapproval['payer']['id'] ?? 'N/A') . "\n";
        }
    }
} else {
    echo "❌ Erro ao buscar preapprovals: HTTP {$http_code}\n";
    echo $response . "\n";
}
