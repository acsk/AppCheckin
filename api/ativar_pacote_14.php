<?php
/**
 * Script para ativar manualmente o pacote/contrato 14
 * Simula exatamente o que ativarPacoteContrato() faz
 */
require_once __DIR__ . '/vendor/autoload.php';

$db = require __DIR__ . '/config/database.php';

$contratoId = 14;

echo "=== ATIVANDO PACOTE/CONTRATO #{$contratoId} ===\n\n";

try {
    // 1. Buscar contrato com dados do pacote
    echo "1️⃣ Buscando contrato...\n";
    $stmtContrato = $db->prepare("
        SELECT pc.*, p.plano_id, p.plano_ciclo_id, p.valor_total, p.qtd_beneficiarios, p.nome as pacote_nome
        FROM pacote_contratos pc
        INNER JOIN pacotes p ON p.id = pc.pacote_id
        WHERE pc.id = ?
        LIMIT 1
    ");
    $stmtContrato->execute([$contratoId]);
    $contrato = $stmtContrato->fetch(PDO::FETCH_ASSOC);

    if (!$contrato) {
        throw new Exception("Contrato #{$contratoId} não encontrado");
    }

    $tenantId = (int) $contrato['tenant_id'];
    echo "   - Pacote: {$contrato['pacote_nome']}\n";
    echo "   - Tenant: {$tenantId}\n";
    echo "   - Status atual: {$contrato['status']}\n";

    // 2. Buscar beneficiários
    echo "\n2️⃣ Buscando beneficiários...\n";
    $stmtBenef = $db->prepare("
        SELECT pb.id, pb.aluno_id, pb.matricula_id
        FROM pacote_beneficiarios pb
        WHERE pb.pacote_contrato_id = ? AND pb.tenant_id = ?
    ");
    $stmtBenef->execute([$contratoId, $tenantId]);
    $beneficiarios = $stmtBenef->fetchAll(PDO::FETCH_ASSOC);

    echo "   - Total beneficiários: " . count($beneficiarios) . "\n";
    foreach ($beneficiarios as $b) {
        echo "     - Beneficiário {$b['id']}: aluno_id={$b['aluno_id']}, matricula_id=" . ($b['matricula_id'] ?? 'NULL') . "\n";
    }

    if (empty($beneficiarios)) {
        throw new Exception("Nenhum beneficiário encontrado");
    }

    $valorTotal = (float) $contrato['valor_total'];
    $valorRateado = $valorTotal / max(1, count($beneficiarios));
    echo "   - Valor total: R$ {$valorTotal}\n";
    echo "   - Valor rateado: R$ {$valorRateado}\n";

    // 3. Calcular datas
    $dataInicio = date('Y-m-d');
    $dataFim = date('Y-m-d', strtotime("+30 days"));

    if (!empty($contrato['plano_ciclo_id'])) {
        $stmtCiclo = $db->prepare("
            SELECT pc2.meses, af.meses as frequencia_meses
            FROM plano_ciclos pc2
            LEFT JOIN assinatura_frequencias af ON af.id = pc2.assinatura_frequencia_id
            WHERE pc2.id = ? AND pc2.tenant_id = ?
            LIMIT 1
        ");
        $stmtCiclo->execute([(int) $contrato['plano_ciclo_id'], $tenantId]);
        $ciclo = $stmtCiclo->fetch(PDO::FETCH_ASSOC);
        if ($ciclo) {
            $mesesCiclo = (int) ($ciclo['meses'] ?: $ciclo['frequencia_meses'] ?: 1);
            $dataFim = date('Y-m-d', strtotime("+{$mesesCiclo} months"));
        }
    }
    echo "\n3️⃣ Datas calculadas:\n";
    echo "   - Data início: {$dataInicio}\n";
    echo "   - Data fim: {$dataFim}\n";

    // 4. Buscar IDs de status - usar ID direto pois a tabela pode não ter coluna 'codigo'
    // Status matrícula ativa geralmente é ID 6 ou buscar pelo nome
    $stmtStatusAtiva = $db->prepare("SELECT id FROM status_matricula WHERE nome LIKE '%ativ%' OR nome LIKE '%Ativ%' LIMIT 1");
    $stmtStatusAtiva->execute();
    $statusAtivaId = (int) ($stmtStatusAtiva->fetchColumn() ?: 6);

    $stmtStatusPago = $db->prepare("SELECT id FROM status_pagamento WHERE nome LIKE '%ago%' OR nome LIKE '%provad%' LIMIT 1");
    $stmtStatusPago->execute();
    $statusPagoId = (int) ($stmtStatusPago->fetchColumn() ?: 2);

    echo "\n4️⃣ IDs de status:\n";
    echo "   - Status matrícula ativa: {$statusAtivaId}\n";
    echo "   - Status pagamento pago: {$statusPagoId}\n";

    // 5. Iniciar transação
    $db->beginTransaction();

    $matriculasProcessadas = 0;

    foreach ($beneficiarios as $ben) {
        $alunoId = (int) $ben['aluno_id'];
        $matriculaId = !empty($ben['matricula_id']) ? (int) $ben['matricula_id'] : null;

        echo "\n5️⃣ Processando beneficiário {$ben['id']} (aluno #{$alunoId}, matrícula: " . ($matriculaId ?? 'NULL') . ")...\n";

        // Verificar se matrícula existe
        if ($matriculaId) {
            $stmtCheckMat = $db->prepare("SELECT id, status_id FROM matriculas WHERE id = ? LIMIT 1");
            $stmtCheckMat->execute([$matriculaId]);
            $matCheck = $stmtCheckMat->fetch(PDO::FETCH_ASSOC);
            if ($matCheck) {
                echo "   - Matrícula #{$matriculaId} existe, status_id atual: {$matCheck['status_id']}\n";
                
                // Ativar a matrícula se não estiver ativa
                $db->prepare("
                    UPDATE matriculas
                    SET status_id = ?, proxima_data_vencimento = ?, updated_at = NOW()
                    WHERE id = ?
                ")->execute([$statusAtivaId, $dataFim, $matriculaId]);
                echo "   ✅ Matrícula #{$matriculaId} atualizada para status_id={$statusAtivaId}\n";
            } else {
                echo "   ⚠️ Matrícula #{$matriculaId} não encontrada!\n";
                $matriculaId = null;
            }
        }

        // Atualizar beneficiário
        $db->prepare("
            UPDATE pacote_beneficiarios SET status = 'ativo', updated_at = NOW() WHERE id = ? AND tenant_id = ?
        ")->execute([(int) $ben['id'], $tenantId]);
        echo "   ✅ Beneficiário {$ben['id']} atualizado para status='ativo'\n";

        // Se a matrícula existe, atualizar pagamento pendente
        if ($matriculaId) {
            // Buscar pagamento pendente
            $stmtPag = $db->prepare("
                SELECT id FROM pagamentos_plano
                WHERE matricula_id = ? AND status_pagamento_id IN (1, 3)
                ORDER BY id DESC LIMIT 1
            ");
            $stmtPag->execute([$matriculaId]);
            $pagPendente = $stmtPag->fetch(PDO::FETCH_ASSOC);
            
            if ($pagPendente) {
                $db->prepare("
                    UPDATE pagamentos_plano
                    SET status_pagamento_id = ?, data_pagamento = NOW(), tipo_baixa_id = 4, 
                        observacoes = 'Pago via Pacote (script manual)', updated_at = NOW()
                    WHERE id = ?
                ")->execute([$statusPagoId, $pagPendente['id']]);
                echo "   ✅ Pagamento #{$pagPendente['id']} baixado!\n";
            } else {
                echo "   - Nenhum pagamento pendente encontrado para baixar\n";
            }
        }

        $matriculasProcessadas++;
    }

    // 6. Atualizar contrato
    echo "\n6️⃣ Atualizando contrato para 'ativo'...\n";
    $db->prepare("
        UPDATE pacote_contratos SET status = 'ativo', updated_at = NOW() WHERE id = ?
    ")->execute([$contratoId]);
    echo "   ✅ Contrato #{$contratoId} atualizado para status='ativo'\n";

    // 7. Atualizar assinatura 63
    echo "\n7️⃣ Atualizando assinatura associada...\n";
    $stmtAss = $db->prepare("SELECT id FROM assinaturas WHERE pacote_contrato_id = ? LIMIT 1");
    $stmtAss->execute([$contratoId]);
    $assId = $stmtAss->fetchColumn();
    if ($assId) {
        // Buscar ID de status ativa
        $stmtStatusAssAtiva = $db->prepare("SELECT id FROM assinatura_status WHERE codigo = 'ativa' LIMIT 1");
        $stmtStatusAssAtiva->execute();
        $statusAssAtivaId = (int) ($stmtStatusAssAtiva->fetchColumn() ?: 6);
        
        $db->prepare("
            UPDATE assinaturas 
            SET status_id = ?, status_gateway = 'approved', atualizado_em = NOW() 
            WHERE id = ?
        ")->execute([$statusAssAtivaId, $assId]);
        echo "   ✅ Assinatura #{$assId} atualizada para status_id={$statusAssAtivaId}, status_gateway='approved'\n";
    }

    $db->commit();
    echo "\n✅✅✅ PACOTE #{$contratoId} ATIVADO COM SUCESSO! ✅✅✅\n";
    echo "   - Matrículas processadas: {$matriculasProcessadas}\n";

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo "\n❌ ERRO: " . $e->getMessage() . "\n";
}
