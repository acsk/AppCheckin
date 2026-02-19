<?php
// Simular webhook do Mercado Pago direto
// Acessar via: http://localhost/webhook_test.php?external_reference=MAT-158-1771524282&status=approved&payment_type=credit_card

require_once __DIR__ . '/config/database.php';

$db = $GLOBALS['db'] ?? require __DIR__ . '/config/database.php';

$externalReference = $_GET['external_reference'] ?? 'MAT-1-' . time();
$status = $_GET['status'] ?? 'approved';
$paymentType = $_GET['payment_type'] ?? 'credit_card';

echo "<pre>";
echo "=== TESTE DE WEBHOOK ===\n";
echo "External Reference: $externalReference\n";
echo "Status: $status\n";
echo "Payment Type: $paymentType\n\n";

try {
    // Query params simulados
    $query = [
        'external_reference' => $externalReference,
        'status' => $status,
        'payment_type' => $paymentType
    ];
    
    // Simular payment_id
    $paymentId = mt_rand(1000000000, 9999999999);
    
    // Dados do pagamento
    $dadosPagamento = [
        'id' => (string)$paymentId,
        'status' => $status,
        'external_reference' => $externalReference,
        'payment_type_id' => $paymentType === 'pix' ? 'prompt_payment' : 'visa',
        'transaction_amount' => 99.90,
        'currency_id' => 'BRL'
    ];
    
    echo "Payment ID gerado: $paymentId\n";
    echo "Processando pagamento...\n\n";
    
    // Buscar tenant_id pela matrícula
    $tenantId = 1;
    if (preg_match('/^MAT-(\d+)-/', $externalReference, $matches)) {
        $matriculaId = (int)$matches[1];
        
        $stmt = $db->prepare("SELECT tenant_id FROM matriculas WHERE id = ? LIMIT 1");
        $stmt->execute([$matriculaId]);
        $matData = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($matData) {
            $tenantId = (int)$matData['tenant_id'];
            echo "✓ Matrícula $matriculaId encontrada - Tenant: $tenantId\n";
        }
    }
    
    // Guardar webhook no banco
    $stmt = $db->prepare("
        INSERT INTO webhook_payloads_mercadopago
        (tenant_id, tipo, data_id, payment_id, payload, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $tenantId,
        'payment',
        (string)$paymentId,
        (string)$paymentId,
        json_encode($dadosPagamento),
        $status
    ]);
    echo "✓ Webhook registrado no banco\n\n";
    
    // Processar o pagamento (atualizar status)
    if (preg_match('/^MAT-(\d+)-/', $externalReference, $matches)) {
        $matriculaId = (int)$matches[1];
        
        // Buscar assinatura
        $stmt = $db->prepare("SELECT id FROM assinaturas WHERE matricula_id = ? AND tenant_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$matriculaId, $tenantId]);
        $assinatura = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($assinatura) {
            // Atualizar status
            $statusCode = $status === 'approved' ? 'ativa' : 'pendente';
            $stmt = $db->prepare("SELECT id FROM assinatura_status WHERE codigo = ?");
            $stmt->execute([$statusCode]);
            $statusId = $stmt->fetchColumn() ?: 1;
            
            $stmt = $db->prepare("
                UPDATE assinaturas 
                SET status_gateway = ?, status_id = ?, atualizado_em = NOW()
                WHERE id = ? AND tenant_id = ?
            ");
            $stmt->execute([$status, $statusId, $assinatura['id'], $tenantId]);
            echo "✓ Assinatura #{$assinatura['id']} atualizada para: $status\n";
        }
        
        // Se aprovado, ativar matrícula
        if ($status === 'approved') {
            $stmt = $db->prepare("SELECT id FROM status_matricula WHERE codigo = 'ativa'");
            $stmt->execute();
            $matStatusId = $stmt->fetchColumn() ?: 2;
            
            $stmt = $db->prepare("
                UPDATE matriculas 
                SET status_id = ?, updated_at = NOW()
                WHERE id = ? AND tenant_id = ?
            ");
            $stmt->execute([$matStatusId, $matriculaId, $tenantId]);
            echo "✓ Matrícula #$matriculaId ativada\n";
        }
    }
    
    echo "\n✅ ✅ ✅ WEBHOOK PROCESSADO COM SUCESSO! ✅ ✅ ✅\n";
    
} catch (Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
    echo "Stack: " . $e->getTraceAsString() . "\n";
}

echo "</pre>";
?>
