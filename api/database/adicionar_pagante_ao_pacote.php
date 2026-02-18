<?php
/**
 * Adicionar matrícula e assinatura do PAGANTE ao pacote
 */

$dbHost = 'localhost';
$dbName = 'u304177849_api';
$dbUser = 'u304177849_api';
$dbPass = '+DEEJ&7t';

try {
    $db = new \PDO(
        "mysql:host={$dbHost};dbname={$dbName}",
        $dbUser,
        $dbPass,
        [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
    );
} catch (\Exception $e) {
    die("Erro: " . $e->getMessage());
}

$contratoId = 3;
$tenantId = 3;
$alunoIdPagante = 72; // ANDRÉ CABRAL SILVA

echo "═══════════════════════════════════════════════════════════\n";
echo "ADICIONANDO PAGANTE AO PACOTE\n";
echo "═══════════════════════════════════════════════════════════\n\n";

$db->beginTransaction();

try {
    // 1. Buscar dados do contrato
    $stmt = $db->prepare("
        SELECT pc.*, p.plano_id, p.valor_total
        FROM pacote_contratos pc
        INNER JOIN pacotes p ON p.id = pc.pacote_id
        WHERE pc.id = ? AND pc.tenant_id = ?
    ");
    $stmt->execute([$contratoId, $tenantId]);
    $contrato = $stmt->fetch(\PDO::FETCH_ASSOC);
    
    if (!$contrato) {
        throw new Exception("Contrato não encontrado");
    }
    
    echo "✓ Contrato encontrado ID: {$contrato['id']}\n";
    
    // 2. Calcular valor rateado (agora são 4 pessoas: pagante + 3 beneficiários)
    $quantidadePessoas = 4; // pagante + 3 beneficiários
    $valorTotal = (float) $contrato['valor_total'];
    $valorRateado = $valorTotal / $quantidadePessoas;
    
    echo "✓ Valor total: R$ {$valorTotal}\n";
    echo "✓ Quantidade de pessoas: {$quantidadePessoas}\n";
    echo "✓ Valor rateado: R$ {$valorRateado}\n";
    
    // 3. Buscar status IDs
    $stmt = $db->prepare("SELECT id FROM status_matricula WHERE codigo = 'ativa' LIMIT 1");
    $stmt->execute();
    $statusAtivaId = (int) ($stmt->fetchColumn() ?: 1);
    
    $stmt = $db->prepare("SELECT id FROM motivo_matricula WHERE codigo = 'nova' LIMIT 1");
    $stmt->execute();
    $motivoId = (int) ($stmt->fetchColumn() ?: 1);
    
    // 4. Calcular datas
    $dataInicio = $contrato['data_inicio'] ?? date('Y-m-d');
    $dataFim = $contrato['data_fim'] ?? date('Y-m-d', strtotime('+30 days'));
    
    echo "✓ Data início: {$dataInicio}\n";
    echo "✓ Data fim: {$dataFim}\n";
    
    // 5. Criar matrícula do PAGANTE
    echo "\n▶ Criando matrícula do pagante (aluno {$alunoIdPagante})...\n";
    
    $stmt = $db->prepare("
        INSERT INTO matriculas
        (tenant_id, aluno_id, plano_id, tipo_cobranca,
         data_matricula, data_inicio, data_vencimento, valor, valor_rateado,
         status_id, motivo_id, proxima_data_vencimento, pacote_contrato_id, created_at, updated_at)
        VALUES (?, ?, ?, 'recorrente', ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
    ");
    $stmt->execute([
        $tenantId,
        $alunoIdPagante,
        (int) $contrato['plano_id'],
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
    
    $matriculaIdPagante = (int) $db->lastInsertId();
    echo "✅ Matrícula criada: ID {$matriculaIdPagante}\n";
    
    // 6. Buscar frequência para assinatura
    $stmt = $db->prepare("
        SELECT af.id
        FROM plano_ciclos pc
        INNER JOIN assinatura_frequencias af ON af.id = pc.assinatura_frequencia_id
        WHERE pc.id = ? AND pc.tenant_id = ?
        LIMIT 1
    ");
    $stmt->execute([$contrato['plano_ciclo_id'] ?? null, $tenantId]);
    $frequenciaId = (int) ($stmt->fetchColumn() ?: 4); // 4 = mensal padrão
    
    // 7. Buscar status de assinatura
    $stmt = $db->prepare("SELECT id FROM assinatura_status WHERE codigo = 'ativa' LIMIT 1");
    $stmt->execute();
    $statusAssinaturaId = (int) ($stmt->fetchColumn() ?: 1);
    
    // 8. Criar ASSINATURA recorrente (APENAS para pagante)
    echo "\n▶ Criando assinatura recorrente do pagante...\n";
    
    $stmt = $db->prepare("
        INSERT INTO assinaturas
        (tenant_id, matricula_id, aluno_id, plano_id,
         gateway_id, gateway_preference_id, external_reference, payment_url,
         status_id, status_gateway, valor, frequencia_id, dia_cobranca,
         data_inicio, data_fim, proxima_cobranca, tipo_cobranca, criado_em, atualizado_em)
        VALUES (?, ?, ?, ?, 1, ?, 'pacote_' || ? || '_' || ?, NULL,
                ?, 'approved', ?, ?, ?, ?, ?, ?, 'recorrente', NOW(), NOW())
    ");
    $stmt->execute([
        $tenantId,
        $matriculaIdPagante,
        $alunoIdPagante,
        (int) $contrato['plano_id'],
        $contrato['payment_preference_id'] ?? null,
        $contratoId,
        $matriculaIdPagante,
        $statusAssinaturaId,
        $valorRateado,
        $frequenciaId,
        (int) date('d'),
        $dataInicio,
        $dataFim,
        $dataFim
    ]);
    
    $assinaturaId = (int) $db->lastInsertId();
    echo "✅ Assinatura criada: ID {$assinaturaId}\n";
    
    // 9. Atualizar valor_rateado de TODAS as matrículas (para ser consistente)
    echo "\n▶ Atualizando valor_rateado de todas as matrículas...\n";
    
    $stmt = $db->prepare("
        UPDATE matriculas
        SET valor_rateado = ?, valor = ?
        WHERE pacote_contrato_id = ? AND tenant_id = ?
    ");
    $stmt->execute([$valorRateado, $valorRateado, $contratoId, $tenantId]);
    
    $rowsAffected = $stmt->rowCount();
    echo "✅ {$rowsAffected} matrículas atualizadas com novo valor rateado: R$ {$valorRateado}\n";
    
    $db->commit();
    
    echo "\n═══════════════════════════════════════════════════════════\n";
    echo "✅✅✅ PAGANTE ADICIONADO COM SUCESSO!\n";
    echo "═══════════════════════════════════════════════════════════\n";
    echo "\nRESUMO FINAL:\n";
    echo "  - 4 matrículas criadas (1 pagante + 3 beneficiários)\n";
    echo "  - 1 assinatura recorrente (APENAS pagante)\n";
    echo "  - Valor rateado: R$ {$valorRateado} cada\n";
    
} catch (\Exception $e) {
    $db->rollBack();
    echo "❌ ERRO: " . $e->getMessage() . "\n";
    exit(1);
}
?>
