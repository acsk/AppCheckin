#!/usr/bin/env php
<?php
// Script para monitorar webhooks recentes em produção

require __DIR__ . '/config/database.php';

echo "\n🔍 VERIFICANDO WEBHOOKS RECENTES DA REFERÊNCIA PAC-4...\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Buscar webhooks para PAC-4
$stmt = $db->prepare("
    SELECT id, created_at, tipo, data_id, status, external_reference, payment_id, preapproval_id, erro_processamento
    FROM webhook_payloads_mercadopago
    WHERE external_reference LIKE 'PAC-4-%'
    ORDER BY created_at DESC
    LIMIT 10
");
$stmt->execute();
$webhooks = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($webhooks)) {
    echo "❌ NENHUM WEBHOOK COM REFERÊNCIA PAC-4 ENCONTRADO\n";
} else {
    echo "✅ WEBHOOKS ENCONTRADOS: " . count($webhooks) . "\n\n";
    
    foreach ($webhooks as $webhook) {
        echo "· ID: {$webhook['id']}\n";
        echo "  Criado: {$webhook['created_at']}\n";
        echo "  Tipo: {$webhook['tipo']}\n";
        echo "  Referência: {$webhook['external_reference']}\n";
        echo "  Payment ID: {$webhook['payment_id']}\n";
        echo "  Preapproval ID: {$webhook['preapproval_id']}\n";
        echo "  Status: {$webhook['status']}\n";
        if ($webhook['erro_processamento']) {
            echo "  ❌ ERRO: {$webhook['erro_processamento']}\n";
        }
        echo "\n";
    }
}

echo "\n🔍 VERIFICANDO ASSINATURAS COM PACOTE_CONTRATO_ID = 4...\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$stmtA = $db->prepare("
    SELECT id, tenant_id, usuario_id, created_at, status_id, 
           gateway_assinatura_id, gateway_preference_id, pacote_contrato_id
    FROM assinaturas
    WHERE pacote_contrato_id = 4
    ORDER BY created_at DESC
    LIMIT 5
");
$stmtA->execute();
$assinaturas = $stmtA->fetchAll(PDO::FETCH_ASSOC);

if (empty($assinaturas)) {
    echo "❌ NENHUMA ASSINATURA COM pacote_contrato_id = 4\n";
} else {
    echo "✅ ASSINATURAS ENCONTRADAS: " . count($assinaturas) . "\n\n";
    foreach ($assinaturas as $ass) {
        echo "· ID: {$ass['id']}\n";
        echo "  Tenant: {$ass['tenant_id']}\n";
        echo "  Usuário: {$ass['usuario_id']}\n";
        echo "  Criada: {$ass['created_at']}\n";
        echo "  Status ID: {$ass['status_id']}\n";
        echo "  Gateway Assinatura: {$ass['gateway_assinatura_id']}\n";
        echo "  Gateway Preference: {$ass['gateway_preference_id']}\n";
        echo "  Pacote ID: {$ass['pacote_contrato_id']}\n";
        echo "\n";
    }
}

echo "\n📊 RESUMO:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Total de webhooks PAC-4: " . count($webhooks) . "\n";
echo "Total de assinaturas pacote 4: " . count($assinaturas) . "\n";

if (!empty($webhooks) && !empty($assinaturas)) {
    echo "\n✅ TUDO OK! Webhooks foram recebidos e assinaturas criadas.\n";
} elseif (empty($webhooks)) {
    echo "\n❌ PROBLEMA: Nenhum webhook recebido de MP!\n";
    echo "   Possíveis causas:\n";
    echo "   1. Webhook URL não está registrada em MP\n";
    echo "   2. MP não está enviando webhooks\n";
    echo "   3. IP da VPS mudou ou está bloqueado\n";
} elseif (empty($assinaturas)) {
    echo "\n❌ PROBLEMA: Webhooks recebidos mas assinaturas não criadas!\n";
    echo "   Possíveis causas:\n";
    echo "   1. Erro no processamento do webhook\n";
    echo "   2. Assinatura foi criada com ID diferente\n";
}

echo "\n";
?>
