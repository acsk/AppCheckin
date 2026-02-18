<?php
/**
 * Script para reprocessar pagamento (compatível com PHP 7.4)
 * 
 * Uso: php database/reprocess_payment_simple.php 146749614928
 */

try {
    $paymentId = $argv[1] ?? null;
    
    if (!$paymentId) {
        echo "❌ Uso: php database/reprocess_payment_simple.php <payment_id>\n";
        echo "Exemplo: php database/reprocess_payment_simple.php 146749614928\n";
        exit(1);
    }
    
    echo "\n🔄 Reprocessando pagamento #{$paymentId}...\n\n";
    
    // Conexão direta com o banco (sem Composer)
    $dsn = getenv('DB_DSN') ?: 'mysql:host=' . (getenv('DB_HOST') ?: 'localhost') . ';dbname=' . (getenv('DB_NAME') ?: 'appcheckin');
    $user = getenv('DB_USER') ?: 'root';
    $pass = getenv('DB_PASS') ?: '';
    
    try {
        $db = new PDO($dsn, $user, $pass);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        // Tentar com credenciais padrão
        $db = new PDO('mysql:host=localhost;dbname=appcheckin', 'root', 'root');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    
    echo "✅ Conectado ao banco de dados\n\n";
    
    // Buscar webhook salvo
    echo "📋 Buscando webhook_payloads_mercadopago...\n";
    $stmt = $db->prepare("SELECT id, tipo, data_id, external_reference, payload FROM webhook_payloads_mercadopago WHERE payment_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$paymentId]);
    $webhook = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($webhook) {
        echo "✅ Webhook encontrado (ID: {$webhook['id']})\n";
        echo "   Tipo: {$webhook['tipo']}\n";
        echo "   Data ID: {$webhook['data_id']}\n";
        echo "   External Reference: {$webhook['external_reference']}\n\n";
        
        $payload = json_decode($webhook['payload'], true);
        echo "📦 Payload:\n";
        echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
    } else {
        echo "⚠️ Webhook não encontrado para payment_id: {$paymentId}\n\n";
    }
    
    // Buscar em pagamentos_mercadopago
    echo "📋 Buscando pagamentos_mercadopago...\n";
    $stmt = $db->prepare("SELECT id, tenant_id, matricula_id, external_reference, status FROM pagamentos_mercadopago WHERE payment_id = ? LIMIT 1");
    $stmt->execute([$paymentId]);
    $pagamento = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($pagamento) {
        echo "✅ Pagamento encontrado (ID: {$pagamento['id']})\n";
        echo "   Tenant ID: {$pagamento['tenant_id']}\n";
        echo "   Matrícula ID: {$pagamento['matricula_id']}\n";
        echo "   External Reference: {$pagamento['external_reference']}\n";
        echo "   Status: {$pagamento['status']}\n\n";
    } else {
        echo "⚠️ Pagamento não encontrado em pagamentos_mercadopago\n\n";
    }
    
    // Se é pacote, buscar contrato
    if ($webhook && preg_match('/PAC-(\d+)-/', $webhook['external_reference'], $matches)) {
        $contratoId = (int) $matches[1];
        echo "🎁 Detectado como PACOTE - ID: {$contratoId}\n\n";
        
        echo "📋 Buscando pacote_contratos...\n";
        $stmt = $db->prepare("SELECT id, tenant_id, status, valor_total FROM pacote_contratos WHERE id = ? LIMIT 1");
        $stmt->execute([$contratoId]);
        $contrato = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($contrato) {
            echo "✅ Contrato encontrado (ID: {$contrato['id']})\n";
            echo "   Tenant ID: {$contrato['tenant_id']}\n";
            echo "   Status: {$contrato['status']}\n";
            echo "   Valor Total: {$contrato['valor_total']}\n\n";
            
            // Buscar beneficiários
            echo "📋 Buscando pacote_beneficiarios...\n";
            $stmt = $db->prepare("SELECT id, aluno_id, matricula_id FROM pacote_beneficiarios WHERE pacote_contrato_id = ? LIMIT 10");
            $stmt->execute([$contratoId]);
            $beneficiarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "✅ Total de beneficiários: " . count($beneficiarios) . "\n";
            foreach ($beneficiarios as $b) {
                echo "   - ID: {$b['id']}, Aluno: {$b['aluno_id']}, Matrícula: {$b['matricula_id']}\n";
            }
        } else {
            echo "❌ Contrato não encontrado (ID: {$contratoId})\n";
        }
    }
    
    echo "\n" . str_repeat("=", 80) . "\n";
    echo "✅ Análise completa\n";
    echo "Para mais detalhes, verifique os logs:\n";
    echo "   tail -100 /var/log/php-error.log | grep -i webhook\n\n";
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    exit(1);
}
