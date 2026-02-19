<?php
/**
 * Script para diagnosticar WEBHOOK → PAGAMENTOS_PLANO - SEM COMPOSER
 * 
 * Mostra que webhooks foram recebidos e se geraram pagamentos
 */

// ============================================
// CONFIGURAÇÕES
// ============================================
$DB_HOST = 'srv1314.hstgr.io';
$DB_USER = 'u304177849_api';
$DB_PASS = '+DEEJ&7t';
$DB_NAME = 'u304177849_api';
$DB_PORT = 3306;

// ============================================
// CONEXÃO
// ============================================
try {
    $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, $DB_PORT);
    
    if ($conn->connect_error) {
        die("❌ Erro de conexão: " . $conn->connect_error);
    }
    
    $conn->set_charset("utf8mb4");
    
} catch (Exception $e) {
    die("❌ Erro: " . $e->getMessage());
}

echo "\n═══════════════════════════════════════════════════════════════════════\n";
echo "DIAGNÓSTICO: WEBHOOK → PAGAMENTOS_PLANO\n";
echo "═══════════════════════════════════════════════════════════════════════\n\n";

// ============================================
// 1. WEBHOOKS RECEBIDOS (últimas 24h)
// ============================================
echo "1️⃣ WEBHOOKS RECEBIDOS (últimas 24h)\n";
echo "───────────────────────────────────────────────────────────────────────\n\n";

$sqlWebhooks = "
    SELECT 
        id,
        tipo,
        external_reference,
        payment_id,
        preapproval_id,
        status,
        created_at,
        erro_processamento
    FROM webhook_payloads_mercadopago
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ORDER BY created_at DESC
    LIMIT 15
";

$resultWebhooks = $conn->query($sqlWebhooks);
$webhooks = [];
$hasWebhooks = false;

if ($resultWebhooks && $resultWebhooks->num_rows > 0) {
    $hasWebhooks = true;
    while ($row = $resultWebhooks->fetch_assoc()) {
        $webhooks[] = $row;
        echo "Webhook ID: #{$row['id']}\n";
        echo "  Tipo: {$row['tipo']}\n";
        echo "  Status: {$row['status']}\n";
        echo "  External Ref: " . ($row['external_reference'] ?? 'NULL') . "\n";
        echo "  Payment ID: " . ($row['payment_id'] ?? 'NULL') . "\n";
        echo "  Preapproval ID: " . ($row['preapproval_id'] ?? 'NULL') . "\n";
        echo "  Recebido: {$row['created_at']}\n";
        if (!empty($row['erro_processamento'])) {
            echo "  ⚠️  ERRO: {$row['erro_processamento']}\n";
        }
        echo "\n";
    }
} else {
    echo "❌ Nenhum webhook recebido nas últimas 24 horas\n\n";
}

// ============================================
// 2. PAGAMENTOS ATUALIZADOS (últimas 24h)
// ============================================
echo "\n2️⃣ PAGAMENTOS CRIADOS/ATUALIZADOS (últimas 24h)\n";
echo "───────────────────────────────────────────────────────────────────────\n\n";

$sqlPagamentos = "
    SELECT 
        pp.id,
        pp.matricula_id,
        pp.valor,
        pp.data_pagamento,
        sp.codigo as status,
        pp.observacoes,
        pp.created_at,
        pp.updated_at
    FROM pagamentos_plano pp
    INNER JOIN status_pagamento sp ON sp.id = pp.status_pagamento_id
    WHERE pp.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
       OR pp.updated_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ORDER BY pp.updated_at DESC
    LIMIT 15
";

$resultPagamentos = $conn->query($sqlPagamentos);
$pagamentos = [];
$hasPagamentos = false;

if ($resultPagamentos && $resultPagamentos->num_rows > 0) {
    $hasPagamentos = true;
    while ($row = $resultPagamentos->fetch_assoc()) {
        $pagamentos[] = $row;
        echo "Pagamento ID: #{$row['id']} | Matrícula: #{$row['matricula_id']}\n";
        echo "  Status: {$row['status']}\n";
        echo "  Valor: R$ " . number_format($row['valor'], 2, ',', '.') . "\n";
        echo "  Data Pagamento: " . ($row['data_pagamento'] ?? 'NÃO PAGO') . "\n";
        echo "  Criado: {$row['created_at']}\n";
        echo "  Atualizado: {$row['updated_at']}\n";
        if (strpos($row['observacoes'] ?? '', 'MP') !== false || strpos($row['observacoes'] ?? '', 'Mercado') !== false) {
            echo "  ✅ Vem de webhook/MP\n";
        }
        echo "\n";
    }
} else {
    echo "❌ Nenhum pagamento criado/atualizado nas últimas 24 horas\n\n";
}

// ============================================
// 3. ASSINATURAS COM PAGAMENTOS
// ============================================
echo "\n3️⃣ ASSINATURAS ATIVAS E SEUS PAGAMENTOS\n";
echo "───────────────────────────────────────────────────────────────────────\n\n";

$sqlAssinaturas = "
    SELECT 
        a.id as assinatura_id,
        a.matricula_id,
        a.external_reference,
        a.gateway_assinatura_id,
        a.status_gateway,
        aus.codigo as status,
        COUNT(pp.id) as total_pagamentos,
        MAX(pp.data_pagamento) as ultimo_pagamento
    FROM assinaturas a
    INNER JOIN assinatura_status aus ON aus.id = a.status_id
    LEFT JOIN pagamentos_plano pp ON pp.matricula_id = a.matricula_id
    GROUP BY a.id
    ORDER BY a.updated_at DESC
    LIMIT 10
";

$resultAssinaturas = $conn->query($sqlAssinaturas);

if ($resultAssinaturas && $resultAssinaturas->num_rows > 0) {
    while ($row = $resultAssinaturas->fetch_assoc()) {
        echo "Assinatura ID: #{$row['assinatura_id']} | Matrícula: #{$row['matricula_id']}\n";
        echo "  Status: {$row['status']} (Gateway: {$row['status_gateway']})\n";
        echo "  External Ref: " . ($row['external_reference'] ?? 'NULL') . "\n";
        echo "  Preapproval: " . ($row['gateway_assinatura_id'] ?? 'NULL') . "\n";
        echo "  Total Pagamentos: {$row['total_pagamentos']}\n";
        echo "  Último Pagamento: " . ($row['ultimo_pagamento'] ?? 'NUNCA') . "\n";
        echo "\n";
    }
} else {
    echo "❌ Nenhuma assinatura encontrada\n\n";
}

// ============================================
// 4. DIAGNÓSTICO FINAL
// ============================================
echo "\n4️⃣ DIAGNÓSTICO FINAL\n";
echo "───────────────────────────────────────────────────────────────────────\n\n";

echo ($hasWebhooks ? "✅" : "❌") . " Webhooks sendo recebidos\n";
echo ($hasPagamentos ? "✅" : "❌") . " Pagamentos sendo criados/atualizados\n";

if ($hasWebhooks && !$hasPagamentos) {
    echo "\n⚠️  PROBLEMA DETECTADO:\n";
    echo "   Webhooks estão sendo recebidos MAS pagamentos NÃO está sendo atualizado!\n";
    echo "   Verifique:\n";
    echo "   1. O método baixarPagamentoPlanoAssinatura() em MercadoPagoWebhookController.php\n";
    echo "   2. Os erros salvos em webhook_payloads_mercadopago.erro_processamento\n";
    echo "   3. O arquivo de log: tail -50 /home/u304177849/domains/appcheckin.com.br/public_html/api/public/php-error.log\n";
} elseif ($hasWebhooks && $hasPagamentos) {
    echo "\n✅ TUDO FUNCIONANDO\n";
    echo "   Webhooks estão sendo recebidos E pagamentos estão sendo atualizados!\n";
}

echo "\n";

$conn->close();
?>
