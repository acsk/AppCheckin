<?php
/**
 * Script para ver detalhes completos de um webhook
 *
 * Uso:
 *   php database/show_webhook_payload.php 1607              # Webhook ID
 *   php database/show_webhook_payload.php last              # Último webhook
 *   php database/show_webhook_payload.php last erro         # Último com erro
 *   php database/show_webhook_payload.php payment 160879679884
 *   php database/show_webhook_payload.php mat 337           # Por external_reference MAT-337-*
 */

require_once __DIR__ . '/../config/database.php';

try {
    $db = require __DIR__ . '/../config/database.php';

    $arg1 = $argv[1] ?? 'last';
    $arg2 = $argv[2] ?? null;

    $checkTable = $db->query("SHOW TABLES LIKE 'webhook_payloads_mercadopago'");
    if ($checkTable->rowCount() === 0) {
        echo "❌ Tabela webhook_payloads_mercadopago não existe!\n";
        exit(1);
    }

    $sql = 'SELECT * FROM webhook_payloads_mercadopago WHERE 1=1';
    $params = [];
    $orderLimit = ' ORDER BY id DESC LIMIT 1';
    $listMode = false;

    if ($arg1 === 'payment' && $arg2 !== null && $arg2 !== '') {
        $paymentId = preg_replace('/\D/', '', (string) $arg2);
        $sql .= ' AND (payment_id = ? OR data_id = ? OR payload LIKE ?)';
        $params = [$paymentId, $paymentId, '%' . $paymentId . '%'];
        $orderLimit = ' ORDER BY id DESC LIMIT 5';
        $listMode = true;
        echo "🔍 Buscando webhooks com payment_id / payload contendo: {$paymentId}\n";
    } elseif (in_array(strtolower($arg1), ['mat', 'matricula'], true) && $arg2 !== null && $arg2 !== '') {
        $matriculaId = (int) $arg2;
        $sql .= ' AND (external_reference LIKE ? OR external_reference LIKE ?)';
        $params = ["MAT-{$matriculaId}-%", "MAT-{$matriculaId}"];
        $orderLimit = ' ORDER BY id DESC LIMIT 10';
        $listMode = true;
        echo "🔍 Buscando webhooks da matrícula #{$matriculaId}\n";
    } elseif ($arg1 === 'last') {
        if ($arg2 !== null && $arg2 !== '') {
            $sql .= ' AND status = ?';
            $params[] = $arg2;
        }
    } elseif (ctype_digit((string) $arg1)) {
        $sql .= ' AND id = ?';
        $params[] = (int) $arg1;
    } else {
        echo "Uso:\n";
        echo "  php database/show_webhook_payload.php last [erro]\n";
        echo "  php database/show_webhook_payload.php <webhook_id>\n";
        echo "  php database/show_webhook_payload.php payment <payment_id>\n";
        echo "  php database/show_webhook_payload.php mat <matricula_id>\n";
        exit(1);
    }

    $stmt = $db->prepare($sql . $orderLimit);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    if ($rows === []) {
        echo "❌ Nenhum webhook encontrado!\n";
        echo "   Se o PIX foi em 24/05/2026, o MP pode não ter notificado este servidor.\n";
        exit(1);
    }

    if ($listMode && count($rows) > 1) {
        echo "\nEncontrados " . count($rows) . " registro(s):\n";
        foreach ($rows as $i => $row) {
            $icon = ($row['status'] ?? '') === 'sucesso' ? '✅' : '❌';
            echo sprintf(
                "  [%d] #%s %s | %s | payment=%s | ref=%s\n",
                $i + 1,
                $row['id'],
                $row['created_at'],
                $icon . ' ' . ($row['status'] ?? '-'),
                $row['payment_id'] ?? $row['data_id'] ?? '-',
                $row['external_reference'] ?? '-'
            );
        }
        echo "\nExibindo o mais recente (#{$rows[0]['id']})...\n";
    }

    $webhook = $rows[0];

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
