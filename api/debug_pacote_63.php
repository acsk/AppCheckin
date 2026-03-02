<?php
/**
 * Diagnóstico: Por que assinatura 63 (pacote) não processa
 */
require_once __DIR__ . '/vendor/autoload.php';

// O config/database.php já retorna um PDO
$db = require __DIR__ . '/config/database.php';

echo "=== DIAGNÓSTICO ASSINATURA 63 (PACOTE) ===\n\n";

// 1. Verificar assinatura 63
echo "1️⃣ ASSINATURA 63:\n";
$stmt = $db->query("SELECT * FROM assinaturas WHERE id = 63");
$ass = $stmt->fetch(PDO::FETCH_ASSOC);
if ($ass) {
    echo "   - External Reference: {$ass['external_reference']}\n";
    echo "   - Status ID: {$ass['status_id']}\n";
    echo "   - Gateway Status: {$ass['status_gateway']}\n";
    echo "   - Pacote Contrato ID: {$ass['pacote_contrato_id']}\n";
    echo "   - Gateway Assinatura ID: " . ($ass['gateway_assinatura_id'] ?? 'NULL') . "\n";
} else {
    echo "   ❌ Não encontrada!\n";
}

// 2. Verificar webhook para essa assinatura
echo "\n2️⃣ ÚLTIMO WEBHOOK PARA PAC-14-1772459140:\n";
$stmt = $db->query("
    SELECT id, tipo, data_id, external_reference, status, resultado_processamento, created_at 
    FROM webhook_payloads_mercadopago 
    WHERE external_reference = 'PAC-14-1772459140'
    ORDER BY id DESC LIMIT 1
");
$wh = $stmt->fetch(PDO::FETCH_ASSOC);
if ($wh) {
    echo "   - ID: {$wh['id']}\n";
    echo "   - Tipo: {$wh['tipo']}\n";
    echo "   - Data ID: {$wh['data_id']}\n";
    echo "   - Status: {$wh['status']}\n";
    echo "   - Resultado: " . ($wh['resultado_processamento'] ?? 'NULL') . "\n";
    
    // 3. Tentar buscar pagamento no fake MP
    echo "\n3️⃣ BUSCANDO PAGAMENTO {$wh['data_id']} NO FAKE MP:\n";
    $paymentId = $wh['data_id'];
    
    // Verificar qual URL estamos usando
    $mpFakeUrl = getenv('MP_FAKE_API_URL');
    echo "   - MP_FAKE_API_URL: " . ($mpFakeUrl ?: 'NÃO DEFINIDO') . "\n";
    
    if (!$mpFakeUrl) {
        $mpFakeUrl = 'http://host.docker.internal:8085';
    }
    
    $url = "{$mpFakeUrl}/v1/payments/{$paymentId}";
    echo "   - URL: {$url}\n";
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer TEST-fake-token']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    echo "   - HTTP Code: {$httpCode}\n";
    if ($curlError) {
        echo "   - Curl Error: {$curlError}\n";
    }
    
    if ($response) {
        $payment = json_decode($response, true);
        if ($payment && is_array($payment)) {
            echo "   - Status: " . ($payment['status'] ?? 'N/A') . "\n";
            echo "   - External Reference: " . ($payment['external_reference'] ?? 'N/A') . "\n";
            echo "   - Payment Method: " . ($payment['payment_method_id'] ?? 'N/A') . "\n";
        } else {
            echo "   - Response: " . substr($response, 0, 200) . "\n";
        }
    }
} else {
    echo "   ❌ Nenhum webhook encontrado!\n";
}

// 4. Verificar contrato 14
echo "\n4️⃣ CONTRATO 14:\n";
$stmt = $db->query("SELECT * FROM pacote_contratos WHERE id = 14");
$contrato = $stmt->fetch(PDO::FETCH_ASSOC);
if ($contrato) {
    echo "   - Status: {$contrato['status']}\n";
    echo "   - Pacote ID: {$contrato['pacote_id']}\n";
    echo "   - Pagante ID: {$contrato['pagante_aluno_id']}\n";
} else {
    echo "   ❌ Não encontrado!\n";
}

// 5. Verificar beneficiários do contrato
echo "\n5️⃣ BENEFICIÁRIOS DO CONTRATO 14:\n";
$stmt = $db->query("SELECT * FROM pacote_beneficiarios WHERE pacote_contrato_id = 14");
$beneficiarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($beneficiarios as $b) {
    echo "   - Beneficiário {$b['id']}: aluno_id={$b['aluno_id']}, matricula_id=" . ($b['matricula_id'] ?? 'NULL') . ", status={$b['status']}\n";
}

// 6. Verificar se o webhook foi processado corretamente
echo "\n6️⃣ DEBUG: Simulando processamento do webhook...\n";
if ($wh) {
    $externalRef = $wh['external_reference'];
    $tipo = null;
    
    if (strpos($externalRef, 'PAC-') === 0) {
        $tipo = 'pacote';
        echo "   ✅ Tipo detectado: pacote (via PAC- no external_reference)\n";
        
        if (preg_match('/PAC-(\d+)-/', $externalRef, $matches)) {
            $pacoteContratoId = (int) $matches[1];
            echo "   ✅ Pacote Contrato ID extraído: {$pacoteContratoId}\n";
        }
    } elseif (strpos($externalRef, 'MAT-') === 0) {
        $tipo = 'matricula';
        echo "   - Tipo: matricula\n";
    }
    
    echo "\n   CONCLUSÃO: O fluxo deveria chamar ativarPacoteContrato({$pacoteContratoId})\n";
}

echo "\n=== FIM DIAGNÓSTICO ===\n";
