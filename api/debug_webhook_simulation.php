<?php
/**
 * Simula o processamento do webhook de pagamento para PAC-14
 */
require_once __DIR__ . '/vendor/autoload.php';

use App\Services\MercadoPagoService;

$db = require __DIR__ . '/config/database.php';

echo "=== SIMULANDO PROCESSAMENTO DO WEBHOOK 391 ===\n\n";

// Dados do webhook
$paymentId = '495152819593';
$type = 'payment';

echo "1️⃣ Buscando pagamento {$paymentId} via MercadoPagoService...\n";

try {
    // Criar o mesmo service que o controller usa
    $tenantId = 3; // Tenant do contrato 14
    
    // Verificar credenciais
    echo "   - Verificando APP_ENV: " . (getenv('APP_ENV') ?: 'não definido') . "\n";
    echo "   - Verificando MP_FAKE_API_URL: " . (getenv('MP_FAKE_API_URL') ?: 'não definido') . "\n";
    
    $mpService = new MercadoPagoService($tenantId);
    
    echo "   - Service criado com sucesso\n";
    
    $pagamento = $mpService->buscarPagamento($paymentId);
    
    echo "\n2️⃣ RESULTADO DA BUSCA:\n";
    echo "   - ID: " . ($pagamento['id'] ?? 'N/A') . "\n";
    echo "   - Status: " . ($pagamento['status'] ?? 'N/A') . "\n";
    echo "   - External Reference: " . ($pagamento['external_reference'] ?? 'N/A') . "\n";
    echo "   - Metadata: " . json_encode($pagamento['metadata'] ?? []) . "\n";
    
    // Simular a lógica de atualizarPagamento
    echo "\n3️⃣ SIMULANDO LÓGICA DE ATUALIZARPAGAMENTO:\n";
    
    $externalReference = $pagamento['external_reference'] ?? '';
    $metadata = $pagamento['metadata'] ?? [];
    $tipo = $metadata['tipo'] ?? null;
    
    echo "   - External Reference: {$externalReference}\n";
    echo "   - Tipo do metadata: " . ($tipo ?? 'null') . "\n";
    
    // FALLBACK: Se tipo não veio no metadata, tentar extrair do external_reference
    if (!$tipo && $externalReference) {
        if (strpos($externalReference, 'PAC-') === 0) {
            $tipo = 'pacote';
            echo "   ✅ Tipo detectado como PACOTE pelo external_reference\n";
        } elseif (strpos($externalReference, 'MAT-') === 0) {
            $tipo = 'matricula';
            echo "   - Tipo detectado como MATRÍCULA pelo external_reference\n";
        }
    }
    
    if ($tipo === 'pacote') {
        echo "   ✅ PACOTE DETECTED!\n";
        
        if ($pagamento['status'] === 'approved') {
            echo "   ✅ Status = approved\n";
            
            $pacoteContratoId = $metadata['pacote_contrato_id'] ?? null;
            
            if (!$pacoteContratoId && $externalReference && preg_match('/PAC-(\d+)-/', $externalReference, $matches)) {
                $pacoteContratoId = (int) $matches[1];
                echo "   ✅ pacote_contrato_id extraído do external_reference: {$pacoteContratoId}\n";
            }
            
            if ($pacoteContratoId) {
                echo "\n   🎯 DEVERIA CHAMAR: ativarPacoteContrato({$pacoteContratoId}, \$pagamento)\n";
                echo "\n   💡 CONCLUSÃO: O fluxo está correto, mas atualizarPagamento() não está sendo chamado!\n";
            }
        } else {
            echo "   ⚠️ Status não é approved: {$pagamento['status']}\n";
        }
    } else {
        echo "   - Não é pacote, tipo detectado: " . ($tipo ?? 'null') . "\n";
    }
    
} catch (Exception $e) {
    echo "\n❌ ERRO: " . $e->getMessage() . "\n";
    echo "   Stack: " . $e->getTraceAsString() . "\n";
}

echo "\n=== FIM SIMULAÇÃO ===\n";
