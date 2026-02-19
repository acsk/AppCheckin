<?php
/**
 * Script para diagnosticar se webhook está atualizando pagamentos_plano
 * 
 * Mostra:
 * 1. Webhooks recebidos
 * 2. Se geraram pagamentos_plano
 * 3. Diferenças entre o que foi recebido vs o que foi salvo
 */

require_once __DIR__ . '/config/database.php';

try {
    $db = require __DIR__ . '/config/database.php';
    
    echo "\n═══════════════════════════════════════════════════════════════════════\n";
    echo "DIAGNÓSTICO: WEBHOOK → PAGAMENTOS_PLANO\n";
    echo "═══════════════════════════════════════════════════════════════════════\n\n";
    
    // 1. Webhooks recebidos nos últimos 7 dias
    echo "1️⃣ WEBHOOKS RECEBIDOS (últimas 24h)\n";
    echo "───────────────────────────────────────────────────────────────────────\n\n";
    
    $sqlWebhooks = "
        SELECT 
            id,
            tipo,
            data_id,
            external_reference,
            payment_id,
            preapproval_id,
            status,
            created_at,
            erro_processamento
        FROM webhook_payloads_mercadopago
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ORDER BY created_at DESC
        LIMIT 10
    ";
    
    $stmtWebhooks = $db->query($sqlWebhooks);
    $webhooks = $stmtWebhooks->fetchAll(\PDO::FETCH_ASSOC);
    
    if (empty($webhooks)) {
        echo "❌ Nenhum webhook recebido nas últimas 24 horas\n\n";
    } else {
        foreach ($webhooks as $wh) {
            echo "Webhook ID: #{$wh['id']}\n";
            echo "  Tipo: {$wh['tipo']}\n";
            echo "  Status: {$wh['status']}\n";
            echo "  External Ref: {$wh['external_reference'] ?? 'N/A'}\n";
            echo "  Payment ID: {$wh['payment_id'] ?? 'N/A'}\n";
            echo "  Preapproval ID: {$wh['preapproval_id'] ?? 'N/A'}\n";
            echo "  Recebido em: {$wh['created_at']}\n";
            if ($wh['erro_processamento']) {
                echo "  ⚠️ ERRO: {$wh['erro_processamento']}\n";
            }
            echo "\n";
        }
    }
    
    // 2. Pagamentos criados/atualizados nas últimas 24h
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
        LIMIT 10
    ";
    
    $stmtPagamentos = $db->query($sqlPagamentos);
    $pagamentos = $stmtPagamentos->fetchAll(\PDO::FETCH_ASSOC);
    
    if (empty($pagamentos)) {
        echo "❌ Nenhum pagamento criado/atualizado nas últimas 24 horas\n\n";
    } else {
        foreach ($pagamentos as $pag) {
            echo "Pagamento ID: #{$pag['id']} | Matrícula: #{$pag['matricula_id']}\n";
            echo "  Status: {$pag['status']}\n";
            echo "  Valor: R$ " . number_format($pag['valor'], 2, ',', '.') . "\n";
            echo "  Data Pagamento: {$pag['data_pagamento'] ?? 'NÃO PAGO'}\n";
            echo "  Criado: {$pag['created_at']}\n";
            echo "  Atualizado: {$pag['updated_at']}\n";
            if (strpos($pag['observacoes'] ?? '', 'MP') !== false) {
                echo "  ✅ Observação contém referência ao MP\n";
            }
            echo "\n";
        }
    }
    
    // 3. Assinaturas com pagamentos
    echo "\n3️⃣ ASSINATURAS ATIVAS vs SEUS PAGAMENTOS\n";
    echo "───────────────────────────────────────────────────────────────────────\n\n";
    
    $sqlAssinaturas = "
        SELECT 
            a.id,
            a.matricula_id,
            a.external_reference,
            a.gateway_assinatura_id,
            a.status_gateway,
            aus.codigo as status,
            COUNT(pp.id) as total_pagamentos_linkados,
            MAX(pp.data_pagamento) as ultimo_pagamento
        FROM assinaturas a
        INNER JOIN assinatura_status aus ON aus.id = a.status_id
        LEFT JOIN pagamentos_plano pp ON pp.matricula_id = a.matricula_id
        GROUP BY a.id, a.matricula_id, a.external_reference
        ORDER BY a.updated_at DESC
        LIMIT 10
    ";
    
    $stmtAssinaturas = $db->query($sqlAssinaturas);
    $assinaturas = $stmtAssinaturas->fetchAll(\PDO::FETCH_ASSOC);
    
    if (empty($assinaturas)) {
        echo "❌ Nenhuma assinatura encontrada\n\n";
    } else {
        foreach ($assinaturas as $ass) {
            echo "Assinatura ID: #{$ass['id']} | Matrícula: #{$ass['matricula_id']}\n";
            echo "  Status: {$ass['status']} (Gateway: {$ass['status_gateway']})\n";
            echo "  External Ref: {$ass['external_reference']}\n";
            echo "  Gateway Preapproval: {$ass['gateway_assinatura_id']}\n";
            echo "  Pagamentos linkados: {$ass['total_pagamentos_linkados']}\n";
            echo "  Último pagamento: {$ass['ultimo_pagamento'] ?? 'NUNCA'}\n";
            echo "\n";
        }
    }
    
    // 4. Checklist de diagnóstico
    echo "\n4️⃣ CHECKLIST DE DIAGNÓSTICO\n";
    echo "───────────────────────────────────────────────────────────────────────\n\n";
    
    $checkWebhook = count($webhooks) > 0;
    $checkPagamentos = count($pagamentos) > 0;
    $checkAssinaturas = count($assinaturas) > 0;
    
    echo ($checkWebhook ? "✅" : "❌") . " Webhooks sendo recebidos\n";
    echo ($checkPagamentos ? "✅" : "❌") . " Pagamentos sendo criados/atualizados\n";
    echo ($checkAssinaturas ? "✅" : "❌") . " Assinaturas existem\n";
    
    if ($checkWebhook && !$checkPagamentos) {
        echo "\n⚠️  PROBLEMA DETECTADO:\n";
        echo "   Webhooks estão sendo recebidos MAS pagamentos_plano não está sendo atualizado!\n";
        echo "   Verifique:\n";
        echo "   1. O método baixarPagamentoPlanoAssinatura() em MercadoPagoWebhookController.php\n";
        echo "   2. Se há erros na coluna 'erro_processamento' dos webhooks\n";
        echo "   3. O erro log em public/php-error.log\n";
    }
    
    echo "\n";
    
} catch (\Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    exit(1);
}
?>
