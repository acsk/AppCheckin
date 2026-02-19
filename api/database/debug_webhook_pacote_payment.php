<?php
require __DIR__ . '/../config/database.php';

echo "🔍 Procurando webhooks para transação 146878181324 e referência PAC-4-1771502066\n\n";

// Buscar por payment_id
$stmt = $db->prepare("
    SELECT id, created_at, tipo, data_id, status, external_reference, payment_id, preapproval_id, erro_processamento, payload
    FROM webhook_payloads_mercadopago
    WHERE payment_id = '146878181324' OR external_reference LIKE 'PAC-4-%'
    ORDER BY created_at DESC
    LIMIT 10
");
$stmt->execute();
$webhooks = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($webhooks)) {
    echo "❌ NENHUM WEBHOOK ENCONTRADO!\n";
    echo "   Isso significa que Mercado Pago NÃO ENVIOU O WEBHOOK para a API.\n\n";
} else {
    echo "✅ Webhooks encontrados: " . count($webhooks) . "\n\n";
    
    foreach ($webhooks as $webhook) {
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "ID: {$webhook['id']}\n";
        echo "Criado em: {$webhook['created_at']}\n";
        echo "Tipo: {$webhook['tipo']}\n";
        echo "Data ID: {$webhook['data_id']}\n";
        echo "Status: {$webhook['status']}\n";
        echo "External Reference: {$webhook['external_reference']}\n";
        echo "Payment ID: {$webhook['payment_id']}\n";
        echo "Preapproval ID: {$webhook['preapproval_id']}\n";
        
        if ($webhook['erro_processamento']) {
            echo "❌ ERRO: {$webhook['erro_processamento']}\n";
        }
        
        echo "\n📋 Payload:\n";
        $payload = json_decode($webhook['payload'], true);
        echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        echo "\n";
    }
}

// Verificar assinaturas com pacote_contrato_id = 4
echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "🔍 Procurando assinaturas com pacote_contrato_id = 4\n\n";

$stmtA = $db->prepare("
    SELECT id, tenant_id, usuario_id, created_at, status_id, gateway_assinatura_id, pacote_contrato_id
    FROM assinaturas
    WHERE pacote_contrato_id = 4
    ORDER BY created_at DESC
    LIMIT 5
");
$stmtA->execute();
$assinaturas = $stmtA->fetchAll(PDO::FETCH_ASSOC);

if (empty($assinaturas)) {
    echo "❌ NENHUMA ASSINATURA CRIADA COM pacote_contrato_id = 4\n";
} else {
    echo "✅ Assinaturas encontradas: " . count($assinaturas) . "\n\n";
    foreach ($assinaturas as $ass) {
        echo "ID: {$ass['id']}\n";
        echo "Tenant: {$ass['tenant_id']}\n";
        echo "Usuário: {$ass['usuario_id']}\n";
        echo "Criada em: {$ass['created_at']}\n";
        echo "Status: {$ass['status_id']}\n";
        echo "Gateway ID: {$ass['gateway_assinatura_id']}\n";
        echo "Pacote ID: {$ass['pacote_contrato_id']}\n";
        echo "\n";
    }
}

// Verificar logs de erro
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "📊 Resumo:\n";
echo "- Webhooks recebidos: " . count($webhooks) . "\n";
echo "- Assinaturas criadas (pacote 4): " . count($assinaturas) . "\n";
echo "\n⚠️  Possíveis problemas:\n";
echo "1. Webhook não foi enviado por MP (verificar configuração em MP)\n";
echo "2. Webhook foi rejeitado (erro HTTP)\n";
echo "3. Webhook foi recebido mas erro no processamento\n";
?>
