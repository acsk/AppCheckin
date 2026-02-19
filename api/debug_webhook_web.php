<?php
/**
 * Script para diagnosticar WEBHOOK → PAGAMENTOS_PLANO - VIA HTTP (web browser)
 * 
 * Acesso:
 *   GET /api/debug_webhook_web.php
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

// ============================================
// HTML COM CSS
// ============================================
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug - Webhook vs Pagamentos</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: #f5f5f5;
            padding: 20px;
            color: #333;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .header p {
            opacity: 0.9;
            font-size: 14px;
        }
        
        .content {
            padding: 30px;
        }
        
        .section {
            margin-bottom: 40px;
        }
        
        .section-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
            color: #667eea;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }
        
        .webhook-card,
        .payment-card {
            background: #f9f9f9;
            border-left: 4px solid #667eea;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 4px;
        }
        
        .webhook-card:hover,
        .payment-card:hover {
            background: #f0f0f0;
        }
        
        .webhook-card {
            border-left-color: #ff9800;
        }
        
        .assinatura-card {
            border-left-color: #4caf50;
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            font-weight: 600;
        }
        
        .card-header .id {
            color: #667eea;
        }
        
        .badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .badge.success {
            background: #d4edda;
            color: #155724;
        }
        
        .badge.error {
            background: #f8d7da;
            color: #721c24;
        }
        
        .badge.warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            font-size: 13px;
        }
        
        .detail-item {
            display: flex;
            flex-direction: column;
        }
        
        .detail-item strong {
            color: #667eea;
            margin-bottom: 4px;
        }
        
        .detail-item span {
            color: #555;
            word-break: break-all;
        }
        
        .diagnostic-box {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .error-box {
            background: #ffebee;
            border-left: 4px solid #f44336;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .success-box {
            background: #e8f5e9;
            border-left: 4px solid #4caf50;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .checklist {
            list-style: none;
        }
        
        .checklist li {
            padding: 8px 0;
            font-size: 14px;
        }
        
        .checklist li:before {
            content: '';
            display: inline-block;
            width: 20px;
            font-weight: bold;
            margin-right: 8px;
        }
        
        .checklist li.ok:before {
            content: '✅';
        }
        
        .checklist li.fail:before {
            content: '❌';
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔍 Debug - Webhook vs Pagamentos</h1>
            <p>Diagnosticar se webhooks estão atualizando pagamentos_plano corretamente</p>
        </div>
        
        <div class="content">
            <?php
            // ============================================
            // 1. WEBHOOKS RECEBIDOS (últimas 24h)
            // ============================================
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
                }
            }
            
            echo '<div class="section">';
            echo '<h2 class="section-title">1️⃣ Webhooks Recebidos (últimas 24h)</h2>';
            
            if (!$hasWebhooks) {
                echo '<div class="error-box">❌ Nenhum webhook recebido nas últimas 24 horas</div>';
            } else {
                echo '<div class="diagnostic-box">✅ ' . count($webhooks) . ' webhook(s) recebido(s)</div>';
                foreach ($webhooks as $wh) {
                    $hasError = !empty($wh['erro_processamento']);
                    echo '<div class="webhook-card">';
                    echo '<div class="card-header">';
                    echo '<span class="id">Webhook #' . $wh['id'] . ' | ' . $wh['tipo'] . '</span>';
                    echo '<span class="badge ' . ($hasError ? 'error' : 'success') . '">' . ($hasError ? 'ERRO' : 'OK') . '</span>';
                    echo '</div>';
                    echo '<div class="details">';
                    echo '<div class="detail-item"><strong>Status</strong><span>' . $wh['status'] . '</span></div>';
                    echo '<div class="detail-item"><strong>External Ref</strong><span>' . htmlspecialchars($wh['external_reference'] ?? 'NULL') . '</span></div>';
                    echo '<div class="detail-item"><strong>Payment ID</strong><span>' . htmlspecialchars($wh['payment_id'] ?? 'NULL') . '</span></div>';
                    echo '<div class="detail-item"><strong>Preapproval ID</strong><span>' . htmlspecialchars($wh['preapproval_id'] ?? 'NULL') . '</span></div>';
                    echo '<div class="detail-item"><strong>Recebido</strong><span>' . $wh['created_at'] . '</span></div>';
                    if ($hasError) {
                        echo '<div class="detail-item"><strong>⚠️ ERRO</strong><span>' . htmlspecialchars($wh['erro_processamento']) . '</span></div>';
                    }
                    echo '</div>';
                    echo '</div>';
                }
            }
            echo '</div>';
            
            // ============================================
            // 2. PAGAMENTOS ATUALIZADOS (últimas 24h)
            // ============================================
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
                }
            }
            
            echo '<div class="section">';
            echo '<h2 class="section-title">2️⃣ Pagamentos Criados/Atualizados (últimas 24h)</h2>';
            
            if (!$hasPagamentos) {
                echo '<div class="error-box">❌ Nenhum pagamento criado/atualizado nas últimas 24 horas</div>';
            } else {
                echo '<div class="success-box">✅ ' . count($pagamentos) . ' pagamento(s) atualizado(s)</div>';
                foreach ($pagamentos as $pag) {
                    echo '<div class="payment-card">';
                    echo '<div class="card-header">';
                    echo '<span class="id">Pagamento #' . $pag['id'] . ' | Matrícula #' . $pag['matricula_id'] . '</span>';
                    echo '<span class="badge success">' . strtoupper($pag['status']) . '</span>';
                    echo '</div>';
                    echo '<div class="details">';
                    echo '<div class="detail-item"><strong>Valor</strong><span>R$ ' . number_format($pag['valor'], 2, ',', '.') . '</span></div>';
                    echo '<div class="detail-item"><strong>Pagamento</strong><span>' . ($pag['data_pagamento'] ?? '❌ Não pago') . '</span></div>';
                    echo '<div class="detail-item"><strong>Criado</strong><span>' . $pag['created_at'] . '</span></div>';
                    echo '<div class="detail-item"><strong>Atualizado</strong><span>' . $pag['updated_at'] . '</span></div>';
                    echo '</div>';
                    echo '</div>';
                }
            }
            echo '</div>';
            
            // ============================================
            // 3. ASSINATURAS COM PAGAMENTOS
            // ============================================
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
            
            echo '<div class="section">';
            echo '<h2 class="section-title">3️⃣ Assinaturas Ativas e Seus Pagamentos</h2>';
            
            if ($resultAssinaturas && $resultAssinaturas->num_rows > 0) {
                while ($row = $resultAssinaturas->fetch_assoc()) {
                    echo '<div class="assinatura-card">';
                    echo '<div class="card-header">';
                    echo '<span class="id">Assinatura #' . $row['assinatura_id'] . ' | Matrícula #' . $row['matricula_id'] . '</span>';
                    echo '<span class="badge success">' . strtoupper($row['status']) . '</span>';
                    echo '</div>';
                    echo '<div class="details">';
                    echo '<div class="detail-item"><strong>External Ref</strong><span>' . htmlspecialchars($row['external_reference'] ?? 'NULL') . '</span></div>';
                    echo '<div class="detail-item"><strong>Preapproval</strong><span>' . htmlspecialchars($row['gateway_assinatura_id'] ?? 'NULL') . '</span></div>';
                    echo '<div class="detail-item"><strong>Gateway Status</strong><span>' . $row['status_gateway'] . '</span></div>';
                    echo '<div class="detail-item"><strong>Total Pagamentos</strong><span>' . $row['total_pagamentos'] . '</span></div>';
                    echo '<div class="detail-item"><strong>Último Pagamento</strong><span>' . ($row['ultimo_pagamento'] ?? '❌ NUNCA') . '</span></div>';
                    echo '</div>';
                    echo '</div>';
                }
            } else {
                echo '<div class="error-box">❌ Nenhuma assinatura encontrada</div>';
            }
            echo '</div>';
            
            // ============================================
            // 4. DIAGNÓSTICO FINAL
            // ============================================
            echo '<div class="section">';
            echo '<h2 class="section-title">4️⃣ Diagnóstico Final</h2>';
            echo '<ul class="checklist">';
            echo '<li class="' . ($hasWebhooks ? 'ok' : 'fail') . '">Webhooks sendo recebidos</li>';
            echo '<li class="' . ($hasPagamentos ? 'ok' : 'fail') . '">Pagamentos sendo criados/atualizados</li>';
            
            if ($hasWebhooks && !$hasPagamentos) {
                echo '<li class="fail">⚠️ PROBLEMA: Webhooks chegando mas pagamentos NÃO sendo atualizados!</li>';
                echo '<li class="fail">Verifique o método baixarPagamentoPlanoAssinatura()</li>';
                echo '<li class="fail">Confira os erros da coluna erro_processamento acima</li>';
            } elseif ($hasWebhooks && $hasPagamentos) {
                echo '<li class="ok">TUDO FUNCIONANDO: Webhooks sendo recebidos e pagamentos sendo atualizados</li>';
            }
            echo '</ul>';
            echo '</div>';
            
            $conn->close();
            ?>
        </div>
    </div>
</body>
</html>
