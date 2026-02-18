<?php
/**
 * Simular o processamento completo do webhook
 * com os dados CORRETOS da API do Mercado Pago
 * 
 * Uso: php database/debug_webhook_simulation.php 146749614928
 */

$paymentId = $argv[1] ?? null;

if (!$paymentId) {
    echo "❌ Uso: php database/debug_webhook_simulation.php <payment_id>\n";
    exit(1);
}

try {
    // Ler credenciais
    echo "\n📋 Carregando credenciais...\n";
    
    // Database
    $dbConfig = [
        'host' => 'localhost',
        'user' => 'u304177849_api',
        'pass' => '+DEEJ&7t',
        'dbname' => 'u304177849_api'
    ];
    
    $db = new \PDO(
        'mysql:host=' . $dbConfig['host'] . ';dbname=' . $dbConfig['dbname'],
        $dbConfig['user'],
        $dbConfig['pass']
    );
    echo "✅ Conectado ao banco de dados\n";
    
    // Ler MP credentials do .env
    $envFile = __DIR__ . '/../.env';
    $env = [];
    if (file_exists($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
                list($key, $value) = explode('=', $line, 2);
                $env[trim($key)] = trim($value);
            }
        }
    }
    
    $mpEnv = $env['MP_ENVIRONMENT'] ?? 'sandbox';
    $accessToken = $mpEnv === 'production' 
        ? ($env['MP_ACCESS_TOKEN_PROD'] ?? null)
        : ($env['MP_ACCESS_TOKEN_TEST'] ?? null);
    
    if (!$accessToken) {
        echo "❌ Access token não encontrado\n";
        exit(1);
    }
    
    echo "✅ MP Token encontrado (Modo: " . ($mpEnv === 'production' ? 'PRODUÇÃO' : 'SANDBOX') . ")\n\n";
    
    // Buscar pagamento na API
    echo "🔍 Buscando pagamento {$paymentId} na API...\n";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.mercadopago.com/v1/payments/{$paymentId}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer {$accessToken}",
        "Content-Type: application/json"
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        echo "❌ Erro na API do MP: HTTP {$httpCode}\n";
        echo "Response: {$response}\n";
        exit(1);
    }
    
    $pagamento = json_decode($response, true);
    
    echo "✅ Pagamento carregado\n";
    echo "   ID: {$pagamento['id']}\n";
    echo "   Status: {$pagamento['status']}\n";
    echo "   External Reference: " . ($pagamento['external_reference'] ?? '❌ NULL') . "\n";
    echo "   Valor: R$ {$pagamento['transaction_amount']}\n\n";
    
    // Simular o que atualizarPagamento() faz
    echo "📊 SIMULANDO atualizarPagamento()...\n\n";
    
    $externalReference = $pagamento['external_reference'];
    $metadata = $pagamento['metadata'] ?? [];
    $tipo = $metadata['tipo'] ?? null;
    
    echo "Step 1: Detectar tipo\n";
    echo "  - metadata['tipo']: " . ($metadata['tipo'] ?? 'NULL') . "\n";
    echo "  - external_reference: {$externalReference}\n";
    
    if (!$tipo && $externalReference) {
        if (strpos($externalReference, 'PAC-') === 0) {
            $tipo = 'pacote';
            echo "  ✅ Detectado como PACOTE\n";
        } elseif (strpos($externalReference, 'MAT-') === 0) {
            $tipo = 'matricula';
            echo "  ✅ Detectado como MATRÍCULA\n";
        }
    }
    
    if ($tipo !== 'pacote') {
        echo "❌ Type não é PACOTE! type = {$tipo}\n";
        exit(1);
    }
    
    echo "\nStep 2: Extrair pacote_contrato_id\n";
    $pacoteContratoId = $metadata['pacote_contrato_id'] ?? null;
    
    if (!$pacoteContratoId && $externalReference && preg_match('/PAC-(\d+)-/', $externalReference, $matches)) {
        $pacoteContratoId = (int) $matches[1];
        echo "  ✅ Extraído do external_reference: {$pacoteContratoId}\n";
    } else {
        echo "  ID do metadata: " . ($pacoteContratoId ?? 'NULL') . "\n";
    }
    
    if (!$pacoteContratoId) {
        echo "❌ pacote_contrato_id não encontrado!\n";
        exit(1);
    }
    
    echo "\nStep 3: Verificar status de aprovação\n";
    if ($pagamento['status'] !== 'approved') {
        echo "  ❌ Status não é APPROVED: {$pagamento['status']}\n";
        exit(1);
    }
    echo "  ✅ Status é APPROVED\n";
    
    echo "\n🎯 INICIANDO ativarPacoteContrato()...\n";
    echo "   pacoteContratoId = {$pacoteContratoId}\n";
    echo "   tenant_id = " . ($metadata['tenant_id'] ?? 'NULL') . "\n\n";
    
    $tenantId = $metadata['tenant_id'] ?? null;
    
    // Buscar contrato
    echo "Step 1: Buscando contrato {$pacoteContratoId}...\n";
    
    $stmt = $db->prepare("
        SELECT pc.*, p.plano_id, p.plano_ciclo_id, p.valor_total,
               COALESCE(pc2.permite_recorrencia, 0) as permite_recorrencia
        FROM pacote_contratos pc
        INNER JOIN pacotes p ON p.id = pc.pacote_id
        LEFT JOIN plano_ciclos pc2 ON pc2.id = p.plano_ciclo_id
        WHERE pc.id = ? AND pc.tenant_id = ?
        LIMIT 1
    ");
    $stmt->execute([$pacoteContratoId, $tenantId]);
    $contrato = $stmt->fetch(\PDO::FETCH_ASSOC);
    
    if (!$contrato) {
        echo "  ❌ CONTRATO NÃO ENCONTRADO!\n";
        echo "  Query: WHERE pc.id = {$pacoteContratoId} AND pc.tenant_id = {$tenantId}\n\n";
        
        // Diagnóstico
        echo "🔍 DIAGNOSTICANDO PROBLEMA:\n\n";
        
        echo "A. Existe algum contrato com ID {$pacoteContratoId}?\n";
        $stmt2 = $db->prepare("SELECT id, tenant_id, status, pacote_id FROM pacote_contratos WHERE id = ?");
        $stmt2->execute([$pacoteContratoId]);
        $todas = $stmt2->fetchAll(\PDO::FETCH_ASSOC);
        if (!empty($todas)) {
            foreach ($todas as $row) {
                echo "   ✅ SIM - tenant_id: {$row['tenant_id']}, status: {$row['status']}, pacote_id: {$row['pacote_id']}\n";
            }
            echo "   ❌ PROBLEMA: tenant_id no contrato ({$todas[0]['tenant_id']}) não bate com metadata ({$tenantId})\n";
        } else {
            echo "   ❌ NÃO - Contrato {$pacoteContratoId} não existe no banco\n";
        }
        
        echo "\nB. Qual é o tenant_id correto?\n";
        $stmtTenant = $db->prepare("SELECT DISTINCT tenant_id FROM pacote_contratos WHERE id = ? LIMIT 1");
        $stmtTenant->execute([$pacoteContratoId]);
        $tenantCorreto = $stmtTenant->fetchColumn();
        if ($tenantCorreto) {
            echo "   ✅ tenant_id correto deveria ser: {$tenantCorreto}\n";
            echo "   ❌ metadata['tenant_id'] está como: {$tenantId}\n";
        }
        
        exit(1);
    }
    
    echo "  ✅ Contrato encontrado:\n";
    echo "     ID: {$contrato['id']}\n";
    echo "     Status: {$contrato['status']}\n";
    echo "     Pacote ID: {$contrato['pacote_id']}\n";
    echo "     Plano ID: {$contrato['plano_id']}\n";
    echo "     Valor Total: {$contrato['valor_total']}\n";
    echo "     Permite Recorrência: {$contrato['permite_recorrencia']}\n";
    echo "     Tenant ID: {$contrato['tenant_id']}\n\n";
    
    // Buscar beneficiários
    echo "Step 2: Buscando beneficiários do contrato {$pacoteContratoId}...\n";
    
    $stmtBenef = $db->prepare("
        SELECT pb.id, pb.aluno_id
        FROM pacote_beneficiarios pb
        WHERE pb.pacote_contrato_id = ? AND pb.tenant_id = ?
    ");
    $stmtBenef->execute([$pacoteContratoId, $contrato['tenant_id']]);
    $beneficiarios = $stmtBenef->fetchAll(\PDO::FETCH_ASSOC);
    
    if (empty($beneficiarios)) {
        echo "  ❌ NENHUM BENEFICIÁRIO ENCONTRADO!\n";
        echo "  Query: WHERE pb.pacote_contrato_id = {$pacoteContratoId} AND pb.tenant_id = {$contrato['tenant_id']}\n\n";
        
        echo "🔍 DIAGNOSTICANDO PROBLEMA:\n\n";
        
        echo "A. Existem beneficiários deste contrato de pacote?\n";
        $stmt3 = $db->prepare("
            SELECT pb.id, pb.aluno_id, pb.status, pb.matricula_id
            FROM pacote_beneficiarios pb
            WHERE pb.pacote_contrato_id = ?
        ");
        $stmt3->execute([$pacoteContratoId]);
        $todas2 = $stmt3->fetchAll(\PDO::FETCH_ASSOC);
        if (!empty($todas2)) {
            echo "   ✅ SIM, existem:\n";
            foreach ($todas2 as $row) {
                echo "      - id: {$row['id']}, aluno_id: {$row['aluno_id']}, status: {$row['status']}, matricula_id: {$row['matricula_id']}\n";
            }
        } else {
            echo "   ❌ NÃO - Nenhum beneficiário cadastrado para contrato {$pacoteContratoId}\n";
        }
        
        exit(1);
    }
    
    echo "  ✅ {" . count($beneficiarios) . "} beneficiário(s) encontrado(s):\n";
    foreach ($beneficiarios as $b) {
        echo "     - ID: {$b['id']}, Aluno ID: {$b['aluno_id']}\n";
    }
    
    echo "\n✅ SIMULAÇÃO CONCLUÍDA COM SUCESSO!\n";
    echo "   O webhook DEVERIA ter processado:\n";
    echo "   - {" . count($beneficiarios) . "} matrícula(s) a criar/atualizar\n";
    echo "   - Contrato status = 'ativo'\n";
    echo "   - Assinatura(s) recorrente(s) se aplicável\n\n";
    
} catch (\Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
    exit(1);
}
