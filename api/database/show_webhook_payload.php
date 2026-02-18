<?php
/**
 * Script para ver detalhes completos de um webhook
 * 
 * Uso: 
 *   php database/show_webhook_payload.php 1         # Webhook ID 1
 *   php database/show_webhook_payload.php last      # Último webhook
 *   php database/show_webhook_payload.php last erro # Último com erro
 */

require_once __DIR__ . '/../config/database.php';

try {
    $db = require __DIR__ . '/../config/database.php';
    
    $webhookId = $argv[1] ?? 'last';
    $filtroStatus = $argv[2] ?? null;
    
    // Verificar se tabela existe
    $checkTable = $db->query("SHOW TABLES LIKE 'webhook_payloads_mercadopago'");
    if ($checkTable->rowCount() === 0) {
        echo "❌ Tabela webhook_payloads_mercadopago não existe!\n";
        exit(1);
    }
    
    // Buscar webhook
    $stmt = $db->prepare("
        SELECT * FROM webhook_payloads_mercadopago
        WHERE " . ($webhookId === 'last' ? "1=1" : "id = ?") . "
        " . ($filtroStatus && $webhookId === 'last' ? "AND status = ?" : "") . "
        ORDER BY id DESC
        LIMIT 1
    ");
    
    $params = [];
    if ($webhookId !== 'last') {
        $params[] = (int)$webhookId;
    }
    if ($filtroStatus && $webhookId === 'last') {
        $params[] = $filtroStatus;
    }
    
    $stmt->execute($params);
    $webhook = $stmt->fetch(\PDO::FETCH_ASSOC);
    
    if (!$webhook) {
        echo "❌ Webhook não encontrado!\n";
        exit(1);
    }
    
    // Exibir detalhes
    echo "\n";
    echo str_repeat("=", 120) . "\n";
    echo "📋 DETALHES DO WEBHOOK ID: {$webhook['id']}\n";
    echo str_repeat("=", 120) . "\n";
    
    $statusIcon = $webhook['status'] === 'sucesso' ? '✅' : '❌';
    echo "\n{$statusIcon} Status: {$webhook['status']}\n";
    echo "⏰ Data: {$webhook['created_at']}\n";
    echo "📝 Tipo: {$webhook['tipo']}\n";
    echo "🔢 Data ID: {$webhook['data_id']}\n";
    
    if ($webhook['tenant_id']) {
        echo "🏢 Tenant ID: {$webhook['tenant_id']}\n";
    }
    
    if ($webhook['external_reference']) {
        echo "📌 External Reference: {$webhook['external_reference']}\n";
    }
    
    if ($webhook['payment_id']) {
        echo "💳 Payment ID: {$webhook['payment_id']}\n";
    }
    
    if ($webhook['preapproval_id']) {
        echo "🔁 Preapproval ID: {$webhook['preapproval_id']}\n";
    }
    
    // Payload
    echo "\n" . str_repeat("-", 120) . "\n";
    echo "📦 PAYLOAD RECEBIDO:\n";
    echo str_repeat("-", 120) . "\n";
    
    if ($webhook['payload']) {
        $payload = json_decode($webhook['payload'], true);
        echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    }
    
    // Resultado do processamento
    if ($webhook['resultado_processamento']) {
        echo "\n" . str_repeat("-", 120) . "\n";
        echo "✅ RESULTADO DO PROCESSAMENTO:\n";
        echo str_repeat("-", 120) . "\n";
        
        $resultado = json_decode($webhook['resultado_processamento'], true);
        echo json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    }
    
    // Erro
    if ($webhook['erro_processamento']) {
        echo "\n" . str_repeat("-", 120) . "\n";
        echo "❌ ERRO:\n";
        echo str_repeat("-", 120) . "\n";
        echo $webhook['erro_processamento'] . "\n";
    }
    
    echo "\n" . str_repeat("=", 120) . "\n\n";
    
} catch (\PDOException $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    exit(1);
} catch (\Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    exit(1);
}
