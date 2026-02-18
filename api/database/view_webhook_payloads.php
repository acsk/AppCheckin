<?php
/**
 * Script para visualizar webhooks salvos do Mercado Pago
 * 
 * Uso: 
 *   php database/view_webhook_payloads.php           # Últimos 20
 *   php database/view_webhook_payloads.php 100       # Últimos 100
 *   php database/view_webhook_payloads.php erro      # Apenas com erro
 *   php database/view_webhook_payloads.php sucesso   # Apenas com sucesso
 */

require_once __DIR__ . '/../config/database.php';

try {
    $db = require __DIR__ . '/../config/database.php';
    
    // Parâmetro: limite (padrão 20) ou filtro (sucesso/erro)
    $param = $argv[1] ?? '20';
    $limite = is_numeric($param) ? (int)$param : 20;
    $filtro = !is_numeric($param) ? $param : null;
    
    // Verificar se tabela existe
    $checkTable = $db->query("SHOW TABLES LIKE 'webhook_payloads_mercadopago'");
    if ($checkTable->rowCount() === 0) {
        echo "❌ Tabela webhook_payloads_mercadopago não existe!\n";
        echo "Execute primeiro: php database/create_webhook_payloads_table.php\n";
        exit(1);
    }
    
    // Montar query
    $sql = "
        SELECT 
            id,
            created_at,
            tipo,
            data_id,
            status,
            external_reference,
            payment_id,
            preapproval_id,
            LENGTH(payload) as payload_size,
            erro_processamento
        FROM webhook_payloads_mercadopago
    ";
    
    if ($filtro === 'erro') {
        $sql .= " WHERE status = 'erro'";
    } elseif ($filtro === 'sucesso') {
        $sql .= " WHERE status = 'sucesso'";
    }
    
    $sql .= " ORDER BY id DESC LIMIT {$limite}";
    
    $result = $db->query($sql);
    $webhooks = $result->fetchAll(\PDO::FETCH_ASSOC);
    
    if (empty($webhooks)) {
        echo "ℹ️ Nenhum webhook encontrado" . ($filtro ? " com status '{$filtro}'" : "") . "!\n";
        exit(0);
    }
    
    // Exibir resultados
    echo "\n";
    echo "📋 WEBHOOKS DO MERCADO PAGO\n";
    echo str_repeat("=", 120) . "\n";
    
    foreach ($webhooks as $w) {
        $statusIcon = $w['status'] === 'sucesso' ? '✅' : '❌';
        $tipoIcon = match($w['tipo']) {
            'payment' => '💳',
            'subscription_preapproval' => '🔁',
            'subscription' => '🔁',
            'preapproval' => '🔁',
            default => '❓'
        };
        
        echo "\n{$statusIcon} ID: {$w['id']} | {$tipoIcon} {$w['tipo']} | {$w['created_at']}\n";
        echo "   Data ID: {$w['data_id']} | Status: {$w['status']}\n";
        
        if ($w['external_reference']) {
            echo "   External Ref: {$w['external_reference']}\n";
        }
        
        if ($w['payment_id']) {
            echo "   Payment ID: {$w['payment_id']}\n";
        }
        
        if ($w['preapproval_id']) {
            echo "   Preapproval ID: {$w['preapproval_id']}\n";
        }
        
        echo "   Payload: {$w['payload_size']} bytes\n";
        
        if ($w['erro_processamento']) {
            echo "   ❌ Erro: {$w['erro_processamento']}\n";
        }
    }
    
    echo "\n" . str_repeat("=", 120) . "\n";
    echo "Total: " . count($webhooks) . " registros\n\n";
    
    // Mostrar estatísticas
    echo "📊 ESTATÍSTICAS:\n";
    $statsResult = $db->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'sucesso' THEN 1 ELSE 0 END) as sucessos,
            SUM(CASE WHEN status = 'erro' THEN 1 ELSE 0 END) as erros,
            COUNT(DISTINCT tipo) as tipos_notificacao
        FROM webhook_payloads_mercadopago
    ");
    $stats = $statsResult->fetch(\PDO::FETCH_ASSOC);
    
    echo "   Total de webhooks: {$stats['total']}\n";
    echo "   ✅ Processados com sucesso: {$stats['sucessos']}\n";
    echo "   ❌ Com erro: {$stats['erros']}\n";
    echo "   Tipos de notificação: {$stats['tipos_notificacao']}\n\n";
    
} catch (\PDOException $e) {
    echo "❌ Erro ao consultar webhooks: " . $e->getMessage() . "\n";
    exit(1);
}
