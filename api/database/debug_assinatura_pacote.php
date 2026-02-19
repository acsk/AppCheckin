<?php
/**
 * Debug: Por que assinatura_id está NULL em pacote_contratos?
 * 
 * Testa:
 * 1. Se assinatura foi criada com sucesso
 * 2. Se o lastInsertId() retorna o ID correto
 * 3. Se o UPDATE está salvando o assinatura_id
 */

require_once __DIR__ . '/../bootstrap.php';

try {
    $db = new \PDO(
        'mysql:host=' . $_ENV['DB_HOST'] . ';dbname=' . $_ENV['DB_NAME'],
        $_ENV['DB_USER'],
        $_ENV['DB_PASS'],
        [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
    );

    // Teste 1: Verificar assinaturas recentes para pacotes
    echo "=== TESTE 1: Assinaturas recentes para pacotes ===\n";
    $stmt = $db->prepare("
        SELECT a.id, a.tenant_id, a.pacote_contrato_id, a.external_reference, 
               a.gateway_preference_id, a.payment_url, a.data_inicio,
               pc.id as contrato_id, pc.assinatura_id, pc.status
        FROM assinaturas a
        LEFT JOIN pacote_contratos pc ON pc.id = a.pacote_contrato_id
        WHERE a.pacote_contrato_id IS NOT NULL
        ORDER BY a.criado_em DESC
        LIMIT 10
    ");
    $stmt->execute();
    $assinaturasComPacote = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    
    echo "✓ Encontradas " . count($assinaturasComPacote) . " assinaturas com pacote_contrato_id:\n";
    foreach ($assinaturasComPacote as $a) {
        echo "  • Assinatura #{$a['id']}: pcid={$a['pacote_contrato_id']}, contrato.assinatura_id={$a['assinatura_id']}, status={$a['status']}\n";
        echo "    - payment_url: " . ($a['payment_url'] ? '✓' : '✗') . "\n";
        echo "    - external_ref: {$a['external_reference']}\n";
    }

    // Teste 2: Verificar contratos sem assinatura_id
    echo "\n=== TESTE 2: Contatos SEM assinatura_id (PROBLEMA!) ===\n";
    $stmt = $db->prepare("
        SELECT id, tenant_id, pacote_id, assinatura_id, pagante_usuario_id,
               payment_url, payment_preference_id, status, created_at
        FROM pacote_contratos
        WHERE assinatura_id IS NULL
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $stmt->execute();
    $contratosComProblema = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    
    echo "✗ Encontrados " . count($contratosComProblema) . " contratos SEM assinatura_id:\n";
    foreach ($contratosComProblema as $c) {
        echo "  • Contrato #{$c['id']}: status={$c['status']}, payment_url=" . ($c['payment_url'] ? '✓' : '✗') . "\n";
        echo "    - Procurando assinatura com pacote_contrato_id={$c['id']}...\n";
        
        // Buscar assinatura correspondente
        $stmt2 = $db->prepare("
            SELECT id, pacote_contrato_id, external_reference
            FROM assinaturas
            WHERE pacote_contrato_id = ? AND tenant_id = ?
        ");
        $stmt2->execute([$c['id'], $c['tenant_id']]);
        $asinEncontrada = $stmt2->fetch(\PDO::FETCH_ASSOC);
        
        if ($asinEncontrada) {
            echo "      ✓ Assinatura #{$asinEncontrada['id']} ENCONTRADA, mas contrato não referencia!\n";
            echo "      - external_reference: {$asinEncontrada['external_reference']}\n";
            echo "      - PROBLEMA: Deveria ter sido salvo com UPDATE mas não foi!\n";
        } else {
            echo "      ✗ Assinatura NÃO encontrada\n";
            echo "      - PROBLEMA: Assinatura não foi criada?\n";
        }
    }

    // Teste 3: Comparar payment_preference_id com gateway_preference_id nas assinaturas
    echo "\n=== TESTE 3: Validar relação Contrato ↔ Assinatura ===\n";
    $stmt = $db->prepare("
        SELECT pc.id as contrato_id, pc.payment_preference_id, pc.assinatura_id,
               a.id as assinatura_id_achada, a.gateway_preference_id
        FROM pacote_contratos pc
        LEFT JOIN assinaturas a ON a.id = pc.assinatura_id
        WHERE pc.status = 'pendente'
        LIMIT 5
    ");
    $stmt->execute();
    $relacoes = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    
    foreach ($relacoes as $r) {
        echo "  • Contrato #{$r['contrato_id']}:\n";
        echo "    - assinatura_id: " . ($r['assinatura_id'] ?: 'NULL') . "\n";
        echo "    - payment_preference_id: " . ($r['payment_preference_id'] ?: 'NULL') . "\n";
        echo "    - gateway_preference_id: " . ($r['gateway_preference_id'] ?: 'NULL') . "\n";
        
        if ($r['assinatura_id'] && $r['assinatura_id_achada']) {
            if ($r['payment_preference_id'] === $r['gateway_preference_id']) {
                echo "    ✓ IDs coincidem (consistente)\n";
            } else {
                echo "    ✗ IDs não coincidem! (inconsistente)\n";
            }
        } elseif (!$r['assinatura_id']) {
            echo "    ✗ Assinatura não referenciada no contrato\n";
        }
    }

    // Teste 4: Última compra testada
    echo "\n=== TESTE 4: Última compra executada (debug) ===\n";
    $stmt = $db->prepare("
        SELECT id FROM pacote_contratos
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->execute();
    $ultimoContrato = $stmt->fetchColumn();
    
    if ($ultimoContrato) {
        $stmt = $db->prepare("
            SELECT * FROM pacote_contratos WHERE id = ?
        ");
        $stmt->execute([$ultimoContrato]);
        $contrato = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        echo "✓ Último contrato criado: #{$ultimoContrato}\n";
        foreach ($contrato as $k => $v) {
            echo "  - {$k}: " . ($v ?? 'NULL') . "\n";
        }

        if ($contrato['assinatura_id']) {
            echo "\n✓ Assinatura_id: #{$contrato['assinatura_id']}\n";
            $stmt = $db->prepare("
                SELECT id, external_reference, payment_url, gateway_preference_id
                FROM assinaturas WHERE id = ?
            ");
            $stmt->execute([$contrato['assinatura_id']]);
            $asin = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($asin) {
                echo "  Detalhes da assinatura:\n";
                foreach ($asin as $k => $v) {
                    echo "    - {$k}: {$v}\n";
                }
            }
        } else {
            echo "\n✗ Assinatura_id é NULL!\n";
        }
    }

    echo "\n✅ Debug concluído.\n";

} catch (\Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
