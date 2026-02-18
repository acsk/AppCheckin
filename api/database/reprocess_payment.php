<?php
/**
 * Script para reprocessar webhook de pagamento
 * 
 * Uso: php database/reprocess_payment.php 146749614928
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Services/MercadoPagoService.php';

use App\Services\MercadoPagoService;

try {
    $paymentId = $argv[1] ?? null;
    
    if (!$paymentId) {
        echo "❌ Uso: php database/reprocess_payment.php <payment_id>\n";
        echo "Exemplo: php database/reprocess_payment.php 146749614928\n";
        exit(1);
    }
    
    echo "\n🔄 Reprocessando pagamento #{$paymentId}...\n";
    
    $db = require __DIR__ . '/../config/database.php';
    
    // Buscar webhook salvo
    $stmt = $db->prepare("SELECT payload FROM webhook_payloads_mercadopago WHERE payment_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$paymentId]);
    $resultado = $stmt->fetch(\PDO::FETCH_ASSOC);
    
    if (!$resultado) {
        echo "❌ Webhook não encontrado para payment_id: {$paymentId}\n";
        echo "\nTentando buscar via API do Mercado Pago...\n";
        
        // Buscar direto da API
        $mercadoPago = new MercadoPagoService();
        $pagamento = $mercadoPago->buscarPagamento($paymentId);
        
        echo "✅ Pagamento encontrado na API:\n";
        echo "   Status: {$pagamento['status']}\n";
        echo "   External Reference: {$pagamento['external_reference']}\n";
        echo "   Metadata: " . json_encode($pagamento['metadata']) . "\n";
        
        exit(0);
    }
    
    $payload = json_decode($resultado['payload'], true);
    
    echo "✅ Webhook encontrado:\n";
    echo "   Type: " . ($payload['type'] ?? 'N/A') . "\n";
    echo "   Data ID: " . ($payload['data']['id'] ?? 'N/A') . "\n";
    
    echo "\n📋 Reprocessando...\n";
    
    // Reprocessar
    $mercadoPago = new MercadoPagoService();
    if ($payload['type'] === 'payment') {
        $pagamento = $mercadoPago->buscarPagamento($payload['data']['id']);
        echo "✅ Pagamento buscado da API\n";
        echo "   Status: {$pagamento['status']}\n";
        echo "   External Ref: {$pagamento['external_reference']}\n";
        echo "   Tipo: " . ($pagamento['metadata']['tipo'] ?? 'N/A') . "\n";
        
        if ($pagamento['metadata']['tipo'] === 'pacote') {
            echo "🎁 É um pagamento de PACOTE\n";
            echo "   Contrato ID: " . ($pagamento['metadata']['pacote_contrato_id'] ?? 'N/A') . "\n";
            echo "   Tenant ID: " . ($pagamento['metadata']['tenant_id'] ?? 'N/A') . "\n";
        }
    }
    
    echo "\n✅ Reprocessamento concluído\n";
    echo "Verifique os logs para mais detalhes\n\n";
    
} catch (\Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    echo "Stack: " . $e->getTraceAsString() . "\n";
    exit(1);
}
