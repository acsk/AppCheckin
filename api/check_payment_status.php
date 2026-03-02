<?php
/**
 * Debug: Verificar estado do pagamento e contrato
 */
$db = require __DIR__ . '/config/database.php';

echo "=== VERIFICANDO ESTADO ATUAL ===\n\n";

// 1. Contrato 13
echo "1. PACOTE_CONTRATOS id=13:\n";
$stmt = $db->prepare("SELECT id, assinatura_id, status, payment_url, payment_preference_id FROM pacote_contratos WHERE id = 13");
$stmt->execute();
$contrato = $stmt->fetch(PDO::FETCH_ASSOC);
print_r($contrato);

// 2. Assinaturas do contrato
echo "\n2. ASSINATURAS do contrato 13:\n";
$stmt = $db->prepare("SELECT id, gateway_preference_id, gateway_assinatura_id, external_reference, status_id, payment_url, matricula_id FROM assinaturas WHERE pacote_contrato_id = 13 ORDER BY id DESC");
$stmt->execute();
$assinaturas = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($assinaturas);

// 3. Webhooks recentes para PAC-13
echo "\n3. WEBHOOK_PAYLOADS para PAC-13:\n";
$stmt = $db->prepare("SELECT id, tipo, data_id, external_reference, status, created_at FROM webhook_payloads_mercadopago WHERE external_reference LIKE '%PAC-13%' ORDER BY id DESC LIMIT 5");
$stmt->execute();
$webhooks = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (empty($webhooks)) {
    echo "⚠️ NENHUM WEBHOOK REGISTRADO PARA PAC-13\n";
    echo "   Isso significa que o simulador NÃO disparou o webhook para a API.\n";
    echo "   O simulador só cria o pagamento, mas não chama automaticamente /api/webhooks/mercadopago.\n\n";
    echo "📋 SOLUÇÃO:\n";
    echo "   Após aprovar pagamento no simulador, chamar manualmente:\n";
    echo "   curl -X POST http://localhost:8085/fake/webhook \\\n";
    echo "     -H 'Content-Type: application/json' \\\n";
    echo "     -d '{\"payment_id\": \"<ID_DO_PAGAMENTO>\", \"status\": \"approved\"}'\n\n";
} else {
    print_r($webhooks);
}

// 4. Matrículas associadas
echo "\n4. MATRÍCULAS do contrato 13:\n";
$stmt = $db->prepare("SELECT id, aluno_id, status_id, pacote_contrato_id FROM matriculas WHERE pacote_contrato_id = 13");
$stmt->execute();
$matriculas = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($matriculas);

// 5. Verificar status de matrículas
echo "\n5. STATUS_MATRICULA de referência:\n";
$stmt = $db->prepare("SELECT id, codigo, nome FROM status_matricula WHERE id IN (1, 5)");
$stmt->execute();
$statusMatr = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($statusMatr);

// 6. Verificar detalhes do webhook payload
echo "\n6. DETALHE WEBHOOK id=385 (último de PAC-13):\n";
$stmt = $db->prepare("SELECT payload, resultado_processamento FROM webhook_payloads_mercadopago WHERE id = 385");
$stmt->execute();
$whDetail = $stmt->fetch(PDO::FETCH_ASSOC);
$payloadData = json_decode($whDetail['payload'] ?? '{}', true);
echo "Payload type: " . ($payloadData['type'] ?? 'N/A') . "\n";
echo "Payload data.id: " . ($payloadData['data']['id'] ?? 'N/A') . "\n";
echo "Resultado: " . ($whDetail['resultado_processamento'] ?? 'NULL') . "\n";

// 7. Verificar se o pagamento foi buscado na API fake
echo "\n7. PROBLEMA IDENTIFICADO:\n";
echo "O webhook foi registrado como 'sucesso', mas:\n";
echo "- Contrato status = 'pendente'\n";
echo "- Matrículas status_id = 5 (pendente)\n\n";
echo "Possíveis causas:\n";
echo "1. A API fake não está retornando dados corretos de pagamento\n";
echo "2. O método ativarPacoteContrato não foi chamado\n";
echo "3. O metadata 'tipo=pacote' não está sendo detectado\n\n";

// 8. Verificar o que a API fake retorna para este pagamento
echo "8. SIMULANDO BUSCA DE PAGAMENTO NA API FAKE:\n";
$fakeApiUrl = $_ENV['MP_FAKE_API_URL'] ?? getenv('MP_FAKE_API_URL') ?? 'http://localhost:8085';
$paymentId = 894741707031;
$ch = curl_init("{$fakeApiUrl}/v1/payments/{$paymentId}");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Authorization: Bearer TEST_TOKEN', 'Content-Type: application/json'],
    CURLOPT_TIMEOUT => 5
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "HTTP Code: {$httpCode}\n";
if ($httpCode === 200) {
    $payment = json_decode($response, true);
    echo "Status: " . ($payment['status'] ?? 'N/A') . "\n";
    echo "External Ref: " . ($payment['external_reference'] ?? 'N/A') . "\n";
    echo "Metadata tipo: " . ($payment['metadata']['tipo'] ?? 'N/A') . "\n";
    echo "Metadata pacote_contrato_id: " . ($payment['metadata']['pacote_contrato_id'] ?? 'N/A') . "\n";
} else {
    echo "Erro ao buscar pagamento: {$response}\n";
}

echo "\n=== FIM ===\n";
