#!/home/u304177849/.local/bin/php
<?php
/**
 * CRON JOB: Reprocessar webhooks de pacotes que falharam
 * Execute a cada 5 minutos: */5 * * * * /home/u304177849/domains/appcheckin.com.br/public_html/api/scripts/webhook_reprocessor_cron.php
 * 
 * Este script busca webhooks com status='sucesso' mas matricula_id=null
 * e reprocessa automaticamente
 */

// Configuração
$dbHost = 'localhost';
$dbName = 'u304177849_api';
$dbUser = 'u304177849_api';
$dbPass = '+DEEJ&7t';

$accessToken = $_ENV['MP_ACCESS_TOKEN_PROD'] ?? $_ENV['MP_ACCESS_TOKEN_TEST'] ?? 'APP_USR-5463428115477491-020510-9307ab7d667f2330239a33d35886e52f-195078879';
$apiBaseUrl = 'https://api.mercadopago.com';

// Conectar ao banco
try {
    $db = new \PDO(
        "mysql:host={$dbHost};dbname={$dbName}",
        $dbUser,
        $dbPass,
        [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4'
        ]
    );
} catch (\Exception $e) {
    file_put_contents('/tmp/webhook_cron.log', "[" . date('Y-m-d H:i:s') . "] ❌ Erro ao conectar DB: " . $e->getMessage() . "\n", FILE_APPEND);
    exit(1);
}

$logFile = '/tmp/webhook_cron.log';

function log_msg($msg) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $fullMsg = "[{$timestamp}] {$msg}\n";
    file_put_contents($logFile, $fullMsg, FILE_APPEND);
    echo $fullMsg;
}

log_msg("═══════════════════════════════════════════════════════════");
log_msg("INICIANDO CRON DE REPROCESSAMENTO DE WEBHOOKS");
log_msg("═══════════════════════════════════════════════════════════");

// Buscar webhooks com status=sucesso mas matricula_id=null
$stmt = $db->prepare("
    SELECT id, payment_id, payload
    FROM webhook_payloads_mercadopago
    WHERE status = 'sucesso' 
    AND tipo = 'payment'
    AND resultado_processamento LIKE '%matricula_id\":null%'
    ORDER BY created_at DESC
    LIMIT 50
");
$stmt->execute();
$webhooks = $stmt->fetchAll();

log_msg("Encontrados: " . count($webhooks) . " webhooks para reprocessar");

if (empty($webhooks)) {
    log_msg("✅ Nenhum webhook pendente");
    exit(0);
}

$processados = 0;
$sucesso = 0;
$erro = 0;

foreach ($webhooks as $webhook) {
    $paymentId = $webhook['payment_id'];
    $webhookId = $webhook['id'];
    
    log_msg("──────────────────────────────────────────────────────────");
    log_msg("Processando Webhook ID: {$webhookId}, Payment ID: {$paymentId}");
    
    // Buscar detalhes do payment na API MP
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "{$apiBaseUrl}/v1/payments/{$paymentId}",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json'
        ],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        log_msg("❌ Erro ao buscar payment na API MP (HTTP {$httpCode})");
        $erro++;
        $processados++;
        continue;
    }
    
    $payment = json_decode($response, true);
    if (!$payment) {
        log_msg("❌ Erro ao fazer parse do payment");
        $erro++;
        $processados++;
        continue;
    }
    
    $externalRef = $payment['external_reference'] ?? null;
    log_msg("✓ External Reference: {$externalRef}");
    
    // Verificar se é pacote
    if (!$externalRef || strpos($externalRef, 'PAC-') !== 0) {
        log_msg("⚠️  Não é um pagamento de pacote");
        $processados++;
        continue;
    }
    
    // Extrair pacote_contrato_id
    if (!preg_match('/PAC-(\d+)-/', $externalRef, $matches)) {
        log_msg("❌ Não conseguiu extrair pacote_contrato_id");
        $erro++;
        $processados++;
        continue;
    }
    
    $contratoId = (int) $matches[1];
    log_msg("✓ Contrato ID: {$contratoId}");
    
    // Buscar contrato
    $stmtContrato = $db->prepare("
        SELECT id, tenant_id, pacote_id, pagante_usuario_id, status
        FROM pacote_contratos
        WHERE id = ?
        LIMIT 1
    ");
    $stmtContrato->execute([$contratoId]);
    $contrato = $stmtContrato->fetch();
    
    if (!$contrato) {
        log_msg("❌ Contrato não encontrado: {$contratoId}");
        $erro++;
        $processados++;
        continue;
    }
    
    $tenantId = (int) $contrato['tenant_id'];
    log_msg("✓ Tenant ID: {$tenantId}");
    
    // Verificar status
    if ($contrato['status'] !== 'pendente') {
        log_msg("⚠️  Contrato não está em status 'pendente' (status atual: {$contrato['status']})");
        $processados++;
        continue;
    }
    
    // Buscar beneficiários
    $stmtBenef = $db->prepare("
        SELECT id, aluno_id
        FROM pacote_beneficiarios
        WHERE pacote_contrato_id = ? AND tenant_id = ?
    ");
    $stmtBenef->execute([$contratoId, $tenantId]);
    $beneficiarios = $stmtBenef->fetchAll();
    
    log_msg("✓ Beneficiários encontrados: " . count($beneficiarios));
    
    if (empty($beneficiarios)) {
        log_msg("⚠️  Nenhum beneficiário para este contrato");
        $processados++;
        continue;
    }
    
    log_msg("▶ Reprocessando pagamento...");
    
    try {
        $db->beginTransaction();
        
        // Buscar dados completos do contrato
        $stmtContratoFull = $db->prepare("
            SELECT pc.*, p.plano_id, p.plano_ciclo_id, p.valor_total,
                   COALESCE(pc2.permite_recorrencia, 0) as permite_recorrencia
            FROM pacote_contratos pc
            INNER JOIN pacotes p ON p.id = pc.pacote_id
            LEFT JOIN plano_ciclos pc2 ON pc2.id = p.plano_ciclo_id
            WHERE pc.id = ? AND pc.tenant_id = ?
            LIMIT 1
        ");
        $stmtContratoFull->execute([$contratoId, $tenantId]);
        $contratoFull = $stmtContratoFull->fetch();
        
        // Buscar status IDs
        $stmtStatusAtiva = $db->prepare("SELECT id FROM status_matricula WHERE codigo = 'ativa' LIMIT 1");
        $stmtStatusAtiva->execute();
        $statusAtivaId = (int) ($stmtStatusAtiva->fetchColumn() ?: 1);
        
        $stmtMotivo = $db->prepare("SELECT id FROM motivo_matricula WHERE codigo = 'nova' LIMIT 1");
        $stmtMotivo->execute();
        $motivoId = (int) ($stmtMotivo->fetchColumn() ?: 1);
        
        // Calcular datas
        $dataInicio = date('Y-m-d');
        $dataFim = date('Y-m-d', strtotime('+30 days'));
        
        $valorTotal = (float) $contratoFull['valor_total'];
        $valorRateado = $valorTotal / max(1, count($beneficiarios));
        
        // Criar matrículas
        foreach ($beneficiarios as $ben) {
            $stmtMat = $db->prepare("
                INSERT INTO matriculas
                (tenant_id, aluno_id, plano_id, tipo_cobranca,
                 data_matricula, data_inicio, data_vencimento, valor, valor_rateado,
                 status_id, motivo_id, proxima_data_vencimento, pacote_contrato_id, created_at, updated_at)
                VALUES (?, ?, ?, 'recorrente', ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $stmtMat->execute([
                $tenantId,
                (int) $ben['aluno_id'],
                (int) $contratoFull['plano_id'],
                $dataInicio,
                $dataInicio,
                $dataFim,
                $valorRateado,
                $valorRateado,
                $statusAtivaId,
                $motivoId,
                $dataFim,
                $contratoId
            ]);
            
            $matriculaId = (int) $db->lastInsertId();
            log_msg("  ✓ Matrícula criada: ID {$matriculaId} para aluno {$ben['aluno_id']}");
            
            // Atualizar pacote_beneficiarios
            $stmtBenUpdate = $db->prepare("
                UPDATE pacote_beneficiarios
                SET matricula_id = ?, valor_rateado = ?, status = 'ativo', updated_at = NOW()
                WHERE id = ? AND tenant_id = ?
            ");
            $stmtBenUpdate->execute([
                $matriculaId,
                $valorRateado,
                (int) $ben['id'],
                $tenantId
            ]);
        }
        
        // Atualizar contrato
        $stmtUpdContrato = $db->prepare("
            UPDATE pacote_contratos
            SET status = 'ativo', pagamento_id = ?, updated_at = NOW()
            WHERE id = ? AND tenant_id = ?
        ");
        $stmtUpdContrato->execute([
            $paymentId,
            $contratoId,
            $tenantId
        ]);
        
        log_msg("  ✓ Contrato atualizado para status 'ativo'");
        
        $db->commit();
        
        log_msg("✅ Webhook REPROCESSADO COM SUCESSO");
        
        // Atualizar webhook na tabela
        $stmtUpdateWebhook = $db->prepare("
            UPDATE webhook_payloads_mercadopago
            SET resultado_processamento = ?
            WHERE id = ?
        ");
        $resultado = json_encode([
            'success' => true,
            'message' => 'Reprocessado via CRON',
            'matriculas_criadas' => count($beneficiarios)
        ]);
        $stmtUpdateWebhook->execute([$resultado, $webhookId]);
        
        $sucesso++;
    } catch (\Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        log_msg("❌ Erro ao reprocessar: " . $e->getMessage());
        $erro++;
    }
    
    $processados++;
}

log_msg("═══════════════════════════════════════════════════════════");
log_msg("RESUMO DO REPROCESSAMENTO");
log_msg("═══════════════════════════════════════════════════════════");
log_msg("Total processado: {$processados}");
log_msg("✅ Sucesso: {$sucesso}");
log_msg("❌ Erros: {$erro}");
log_msg("═══════════════════════════════════════════════════════════");
?>
