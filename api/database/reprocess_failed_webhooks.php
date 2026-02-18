<?php
/**
 * Reprocessar webhooks de pagamento de pacotes que falharam silenciosamente
 * Busca webhooks com status='sucesso' mas matricula_id=null e reprocessa
 */

// Configuração
$dbHost = 'localhost';
$dbName = 'u304177849_api';
$dbUser = 'u304177849_api';
$dbPass = '+DEEJ&7t';

$accessToken = 'APP_USR-5463428115477491-020510-9307ab7d667f2330239a33d35886e52f-195078879';
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
    die("❌ Erro ao conectar DB: " . $e->getMessage() . "\n");
}

echo "=== REPROCESSADOR DE WEBHOOKS DE PACOTES ===\n\n";

// Buscar webhooks com status=sucesso mas matricula_id=null
$stmt = $db->prepare("
    SELECT id, payment_id, payload, tipo
    FROM webhook_payloads_mercadopago
    WHERE status = 'sucesso' 
    AND tipo = 'payment'
    AND (resultado_processamento LIKE '%matricula_id\":null%' OR resultado_processamento LIKE '%matricula_id\": null%')
    ORDER BY created_at DESC
    LIMIT 50
");
$stmt->execute();
$webhooks = $stmt->fetchAll();

echo "Found: " . count($webhooks) . " webhooks para reprocessar\n\n";

if (empty($webhooks)) {
    echo "✅ Nenhum webhook para reprocessar\n";
    exit(0);
}

$processados = 0;
$sucesso = 0;
$erro = 0;

foreach ($webhooks as $webhook) {
    $paymentId = $webhook['payment_id'];
    $webhookId = $webhook['id'];
    
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "Processing Webhook ID: {$webhookId}, Payment ID: {$paymentId}\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    
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
        echo "❌ Erro ao buscar payment na API MP (HTTP {$httpCode})\n";
        $erro++;
        continue;
    }
    
    $payment = json_decode($response, true);
    if (!$payment) {
        echo "❌ Erro ao fazer parse do payment\n";
        $erro++;
        continue;
    }
    
    $externalRef = $payment['external_reference'] ?? null;
    echo "✓ External Reference: {$externalRef}\n";
    
    // Extrair pacote_contrato_id do external_reference (formato: PAC-{id}-{timestamp})
    if (!$externalRef || strpos($externalRef, 'PAC-') !== 0) {
        echo "⚠️  Não é um pagamento de pacote (external_reference: {$externalRef})\n";
        $processados++;
        continue;
    }
    
    if (!preg_match('/PAC-(\d+)-/', $externalRef, $matches)) {
        echo "❌ Não conseguiu extrair pacote_contrato_id de: {$externalRef}\n";
        $erro++;
        continue;
    }
    
    $contratoId = (int) $matches[1];
    echo "✓ Contrato ID: {$contratoId}\n";
    
    // Buscar o contrato no banco
    $stmtContrato = $db->prepare("
        SELECT id, tenant_id, pacote_id, pagante_usuario_id, status
        FROM pacote_contratos
        WHERE id = ?
        LIMIT 1
    ");
    $stmtContrato->execute([$contratoId]);
    $contrato = $stmtContrato->fetch();
    
    if (!$contrato) {
        echo "❌ Contrato não encontrado: {$contratoId}\n";
        $erro++;
        continue;
    }
    
    $tenantId = (int) $contrato['tenant_id'];
    echo "✓ Tenant ID: {$tenantId}\n";
    
    // Verificar status
    if ($contrato['status'] !== 'pendente') {
        echo "⚠️  Contrato não está em status 'pendente' (status atual: {$contrato['status']})\n";
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
    
    echo "✓ Beneficiários encontrados: " . count($beneficiarios) . "\n";
    
    if (empty($beneficiarios)) {
        echo "⚠️  Nenhum beneficiário para este contrato\n";
        $processados++;
        continue;
    }
    
    // Reprocessar o pagamento (simular webhook novamente)
    echo "▶ Reprocessando pagamento...\n";
    
    try {
        // Montar array similar ao recebido pelo webhook
        $pagamentoReprocessado = [
            'id' => $paymentId,
            'status' => $payment['status'],
            'external_reference' => $externalRef,
            'metadata' => [
                'tenant_id' => $tenantId,
                'pacote_contrato_id' => $contratoId
            ]
        ];
        
        // Aqui você precisaria chamar a função ativarPacoteContrato
        // Como estamos em um script standalone, vou fazer um INSERT direto no banco
        // Para manter consistência
        
        $db->beginTransaction();
        
        // Buscar dados do contrato completos
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
        $dataFim = null;
        if (!empty($contratoFull['plano_ciclo_id'])) {
            $stmtCiclo = $db->prepare("SELECT meses FROM plano_ciclos WHERE id = ? AND tenant_id = ? LIMIT 1");
            $stmtCiclo->execute([(int)$contratoFull['plano_ciclo_id'], $tenantId]);
            $meses = (int) ($stmtCiclo->fetchColumn() ?: 0);
            if ($meses > 0) {
                $dataFim = date('Y-m-d', strtotime("+{$meses} months"));
            }
        }
        if (!$dataFim) {
            $stmtPlano = $db->prepare("SELECT duracao_dias FROM planos WHERE id = ? AND tenant_id = ? LIMIT 1");
            $stmtPlano->execute([(int)$contratoFull['plano_id'], $tenantId]);
            $duracaoDias = (int) ($stmtPlano->fetchColumn() ?: 30);
            $dataFim = date('Y-m-d', strtotime("+{$duracaoDias} days"));
        }
        
        $valorTotal = (float) $contratoFull['valor_total'];
        $valorRateado = $valorTotal / max(1, count($beneficiarios));
        
        echo "  Valor total: R$ {$valorTotal}, Rateado: R$ {$valorRateado}\n";
        
        // Criar matrículas para cada beneficiário
        $matriculasIds = [];
        foreach ($beneficiarios as $ben) {
            $stmtMat = $db->prepare("
                INSERT INTO matriculas
                (tenant_id, aluno_id, plano_id, plano_ciclo_id, tipo_cobranca,
                 data_matricula, data_inicio, data_vencimento, valor, valor_rateado,
                 status_id, motivo_id, proxima_data_vencimento, pacote_contrato_id, created_at, updated_at)
                VALUES (?, ?, ?, ?, 'recorrente', ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $stmtMat->execute([
                $tenantId,
                (int) $ben['aluno_id'],
                (int) $contratoFull['plano_id'],
                !empty($contratoFull['plano_ciclo_id']) ? (int) $contratoFull['plano_ciclo_id'] : null,
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
            $matriculasIds[] = $matriculaId;
            echo "  ✓ Matrícula criada: ID {$matriculaId} para aluno {$ben['aluno_id']}\n";
            
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
        
        // Atualizar status do contrato
        $stmtUpdContrato = $db->prepare("
            UPDATE pacote_contratos
            SET status = 'ativo', pagamento_id = ?, data_inicio = ?, data_fim = ?, updated_at = NOW()
            WHERE id = ? AND tenant_id = ?
        ");
        $stmtUpdContrato->execute([
            $paymentId,
            $dataInicio,
            $dataFim,
            $contratoId,
            $tenantId
        ]);
        
        echo "  ✓ Contrato atualizado para status 'ativo'\n";
        
        $db->commit();
        
        echo "✅ Webhook REPROCESSADO COM SUCESSO\n";
        
        // Atualizar status do webhook na tabela
        $stmtUpdateWebhook = $db->prepare("
            UPDATE webhook_payloads_mercadopago
            SET resultado_processamento = ?
            WHERE id = ?
        ");
        $resultado = json_encode([
            'success' => true,
            'message' => 'Reprocessado com sucesso',
            'matriculas_criadas' => count($matriculasIds),
            'matricula_ids' => $matriculasIds
        ]);
        $stmtUpdateWebhook->execute([$resultado, $webhookId]);
        
        $sucesso++;
    } catch (\Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        echo "❌ Erro ao reprocessar: " . $e->getMessage() . "\n";
        $erro++;
    }
    
    $processados++;
}

echo "\n" . str_repeat("=", 40) . "\n";
echo "RESUMO DO REPROCESSAMENTO\n";
echo str_repeat("=", 40) . "\n";
echo "Total processado: {$processados}\n";
echo "✅ Sucesso: {$sucesso}\n";
echo "❌ Erros: {$erro}\n";
echo str_repeat("=", 40) . "\n";
?>
