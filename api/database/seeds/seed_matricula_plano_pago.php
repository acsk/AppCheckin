<?php
/**
 * =========================================================
 * SEED: Matrícula Retroativa com Plano PAGO
 * Data: 06/02/2026
 * =========================================================
 * 
 * Cenário de Teste:
 * - Matrícula criada em 12/01/2026 (retroativa)
 * - Plano: 30 dias de duração COM VALOR (pago)
 * - Dia vencimento escolhido: 5 (todo dia 5)
 * - Período teste: NÃO (plano pago desde o início)
 * - Próxima data vencimento: 11/02/2026 (12/01 + 30 dias)
 * - Data início cobrança: NULL (já é pago)
 * 
 * Timeline:
 * 12/01 - Matrícula criada (retroativa) - PAGO
 * 05/02 - Próxima mensalidade vence (dia escolhido)
 * 06/02 - HOJE - Check-in deve funcionar ✅
 * 10/02 - Check-in deve funcionar ✅
 * 11/02 - Check-in deve funcionar ✅ (último dia do período)
 * 12/02 - Check-in bloqueado ❌ (30 dias completos)
 */

require_once __DIR__ . '/../../config/database.php';

$conn = $pdo;

try {
    $conn->beginTransaction();
    
    // ===========================================
    // 1. Verificar/Buscar Tenant 2
    // ===========================================
    $stmtTenant = $conn->prepare("
        SELECT id, nome 
        FROM tenants 
        WHERE id = 2
        LIMIT 1
    ");
    $stmtTenant->execute();
    $tenant = $stmtTenant->fetch(PDO::FETCH_ASSOC);
    
    if (!$tenant) {
        throw new Exception("❌ Tenant ID 2 não encontrado!");
    }
    
    echo "✅ Tenant encontrado: {$tenant['nome']}\n";
    
    // ===========================================
    // 2. Verificar/Buscar Plano PAGO (valor > 0)
    // ===========================================
    $stmtPlano = $conn->prepare("
        SELECT id, nome, valor, duracao_dias 
        FROM planos 
        WHERE tenant_id = 2 
          AND valor > 0.00 
          AND ativo = 1
        ORDER BY valor ASC
        LIMIT 1
    ");
    $stmtPlano->execute();
    $plano = $stmtPlano->fetch(PDO::FETCH_ASSOC);
    
    if (!$plano) {
        throw new Exception("❌ Nenhum plano pago (valor > 0) encontrado para tenant 2!");
    }
    
    echo "✅ Plano selecionado: {$plano['nome']} - R$ {$plano['valor']} - {$plano['duracao_dias']} dias\n";
    
    // ===========================================
    // 3. Verificar/Buscar Outro Aluno do Tenant 2
    // ===========================================
    $stmtAluno = $conn->prepare("
        SELECT a.id, a.nome, u.email 
        FROM alunos a
        INNER JOIN usuarios u ON a.usuario_id = u.id
        INNER JOIN tenant_usuario_papel tup ON a.usuario_id = tup.usuario_id
        WHERE tup.tenant_id = 2
          AND tup.papel_id = 1
          AND a.ativo = 1
        ORDER BY a.id DESC
        LIMIT 1
    ");
    $stmtAluno->execute();
    $aluno = $stmtAluno->fetch(PDO::FETCH_ASSOC);
    
    if (!$aluno) {
        throw new Exception("❌ Nenhum aluno ativo encontrado para tenant 2!");
    }
    
    echo "✅ Aluno selecionado: {$aluno['nome']} (ID: {$aluno['id']})\n";
    
    // ===========================================
    // 4. Verificar Status Ativo
    // ===========================================
    $stmtStatus = $conn->prepare("
        SELECT id, nome 
        FROM status_matricula 
        WHERE nome = 'Ativa'
        LIMIT 1
    ");
    $stmtStatus->execute();
    $status = $stmtStatus->fetch(PDO::FETCH_ASSOC);
    
    if (!$status) {
        throw new Exception("❌ Status 'Ativa' não encontrado!");
    }
    
    // ===========================================
    // 5. Verificar Motivo Inicial
    // ===========================================
    $stmtMotivo = $conn->prepare("
        SELECT id 
        FROM motivo_matricula 
        WHERE nome = 'Primeira matrícula'
        LIMIT 1
    ");
    $stmtMotivo->execute();
    $motivo = $stmtMotivo->fetch(PDO::FETCH_ASSOC);
    
    if (!$motivo) {
        $motivo = ['id' => 1];
    }
    
    // ===========================================
    // 6. Calcular Datas
    // ===========================================
    $dataInicio = new DateTime('2026-01-12'); // 12/01/2026 (retroativa)
    $dataMatricula = clone $dataInicio;
    $dataVencimento = clone $dataInicio;
    $dataVencimento->modify("+{$plano['duracao_dias']} days"); // 11/02/2026
    
    // Próxima data de vencimento = data_inicio + duracao_dias
    $proximaDataVencimento = clone $dataInicio;
    $proximaDataVencimento->modify("+{$plano['duracao_dias']} days"); // 11/02/2026
    
    // Dia vencimento: 5
    $diaVencimento = 5;
    
    // Data início cobrança: NULL (plano pago não tem período teste)
    $dataInicioCobranca = null;
    
    echo "\n📅 Datas Calculadas:\n";
    echo "   - Data Matrícula: " . $dataMatricula->format('d/m/Y') . "\n";
    echo "   - Data Início: " . $dataInicio->format('d/m/Y') . "\n";
    echo "   - Data Vencimento: " . $dataVencimento->format('d/m/Y') . "\n";
    echo "   - Dia Vencimento: $diaVencimento\n";
    echo "   - Próxima Data Vencimento: " . $proximaDataVencimento->format('d/m/Y') . " ← CONTROLA BLOQUEIO\n";
    echo "   - Data Início Cobrança: NULL (plano pago)\n";
    echo "   - Período Teste: NÃO (plano pago)\n";
    
    // ===========================================
    // 7. Verificar se já existe matrícula para este aluno
    // ===========================================
    $stmtCheck = $conn->prepare("
        SELECT id 
        FROM matriculas 
        WHERE aluno_id = :aluno_id 
          AND data_inicio = :data_inicio
        LIMIT 1
    ");
    $stmtCheck->execute([
        ':aluno_id' => $aluno['id'],
        ':data_inicio' => $dataInicio->format('Y-m-d')
    ]);
    
    if ($stmtCheck->fetch()) {
        echo "\n⚠️  Matrícula já existe para este aluno nesta data. Pulando...\n";
        $conn->rollBack();
        exit(0);
    }
    
    // ===========================================
    // 8. Inserir Matrícula Retroativa PAGA
    // ===========================================
    $stmtInsert = $conn->prepare("
        INSERT INTO matriculas (
            tenant_id,
            aluno_id,
            plano_id,
            data_matricula,
            data_inicio,
            data_vencimento,
            valor,
            status_id,
            motivo_id,
            dia_vencimento,
            periodo_teste,
            data_inicio_cobranca,
            proxima_data_vencimento,
            created_at
        ) VALUES (
            :tenant_id,
            :aluno_id,
            :plano_id,
            :data_matricula,
            :data_inicio,
            :data_vencimento,
            :valor,
            :status_id,
            :motivo_id,
            :dia_vencimento,
            :periodo_teste,
            :data_inicio_cobranca,
            :proxima_data_vencimento,
            NOW()
        )
    ");
    
    $stmtInsert->execute([
        ':tenant_id' => $tenant['id'],
        ':aluno_id' => $aluno['id'],
        ':plano_id' => $plano['id'],
        ':data_matricula' => $dataMatricula->format('Y-m-d'),
        ':data_inicio' => $dataInicio->format('Y-m-d'),
        ':data_vencimento' => $dataVencimento->format('Y-m-d'),
        ':valor' => $plano['valor'],
        ':status_id' => $status['id'],
        ':motivo_id' => $motivo['id'],
        ':dia_vencimento' => $diaVencimento,
        ':periodo_teste' => 0, // Plano PAGO
        ':data_inicio_cobranca' => $dataInicioCobranca,
        ':proxima_data_vencimento' => $proximaDataVencimento->format('Y-m-d')
    ]);
    
    $matriculaId = $conn->lastInsertId();
    
    $conn->commit();
    
    echo "\n✅ MATRÍCULA PAGA CRIADA COM SUCESSO!\n";
    echo "   ID: $matriculaId\n";
    echo "   Aluno: {$aluno['nome']}\n";
    echo "   Plano: {$plano['nome']}\n";
    echo "   Valor: R$ " . number_format($plano['valor'], 2, ',', '.') . "\n";
    
    echo "\n📊 COMPORTAMENTO ESPERADO:\n";
    echo "   ✅ 06/02 (HOJE) - Check-in liberado\n";
    echo "   ✅ 10/02 - Check-in liberado\n";
    echo "   ✅ 11/02 - Check-in liberado (último dia)\n";
    echo "   ❌ 12/02 - Check-in BLOQUEADO (30 dias completos)\n";
    
    echo "\n💰 FINANCEIRO:\n";
    echo "   - Plano PAGO desde o início (sem período teste)\n";
    echo "   - Próxima mensalidade vence: 05/02/2026 (dia $diaVencimento)\n";
    echo "   - Acesso garantido até: 11/02/2026 (30 dias completos)\n";
    
    echo "\n🔍 CONSULTAR MATRÍCULA:\n";
    echo "   SELECT * FROM matriculas WHERE id = $matriculaId;\n";
    
} catch (Exception $e) {
    $conn->rollBack();
    echo "\n❌ ERRO: " . $e->getMessage() . "\n";
    exit(1);
}
