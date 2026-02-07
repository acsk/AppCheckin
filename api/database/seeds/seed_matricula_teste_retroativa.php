<?php
/**
 * =========================================================
 * SEED: Matrícula Retroativa para Teste
 * Data: 06/02/2026
 * =========================================================
 * 
 * Cenário de Teste:
 * - Matrícula criada em 09/01/2026 (retroativa)
 * - Plano: 30 dias de duração
 * - Dia vencimento escolhido: 1 (todo dia 1º)
 * - Período teste: SIM (valor 0)
 * - Próxima data vencimento: 08/02/2026 (09/01 + 30 dias)
 * - Data início cobrança: 01/02/2026 (primeira ocorrência do dia 1)
 * 
 * Timeline:
 * 09/01 - Matrícula criada (retroativa)
 * 01/02 - Cobrança gerada (financeiro)
 * 06/02 - HOJE - Check-in deve funcionar ✅
 * 07/02 - Check-in deve funcionar ✅
 * 08/02 - Check-in deve funcionar ✅ (último dia)
 * 09/02 - Check-in bloqueado ❌ (30 dias completos)
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
    // 2. Verificar/Buscar Plano de Teste
    // ===========================================
    $stmtPlano = $conn->prepare("
        SELECT id, nome, valor, duracao_dias 
        FROM planos 
        WHERE tenant_id = 2 
          AND valor = 0.00 
          AND ativo = 1
        ORDER BY id ASC
        LIMIT 1
    ");
    $stmtPlano->execute();
    $plano = $stmtPlano->fetch(PDO::FETCH_ASSOC);
    
    if (!$plano) {
        throw new Exception("❌ Nenhum plano de teste (valor 0) encontrado para tenant 2!");
    }
    
    echo "✅ Plano selecionado: {$plano['nome']} - {$plano['duracao_dias']} dias\n";
    
    // ===========================================
    // 3. Verificar/Buscar Aluno do Tenant 2
    // ===========================================
    $stmtAluno = $conn->prepare("
        SELECT a.id, a.nome, u.email 
        FROM alunos a
        INNER JOIN usuarios u ON a.usuario_id = u.id
        INNER JOIN tenant_usuario_papel tup ON a.usuario_id = tup.usuario_id
        WHERE tup.tenant_id = 2
          AND a.ativo = 1
        ORDER BY a.id ASC
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
        // Usar motivo_id = 1 como fallback
        $motivo = ['id' => 1];
    }
    
    // ===========================================
    // 6. Calcular Datas
    // ===========================================
    $dataInicio = new DateTime('2026-01-09'); // 09/01/2026 (retroativa)
    $dataMatricula = clone $dataInicio;
    $dataVencimento = clone $dataInicio;
    $dataVencimento->modify("+{$plano['duracao_dias']} days"); // 08/02/2026
    
    // Próxima data de vencimento = data_inicio + duracao_dias
    $proximaDataVencimento = clone $dataInicio;
    $proximaDataVencimento->modify("+{$plano['duracao_dias']} days"); // 08/02/2026
    
    // Data início cobrança = primeiro dia 1 após a matrícula
    $diaVencimento = 1;
    $dataInicioCobranca = clone $dataInicio;
    
    // Se já passou o dia 1 de janeiro, vai para fevereiro
    if ($dataInicioCobranca->format('d') > $diaVencimento) {
        $dataInicioCobranca->modify('first day of next month');
    } else {
        $dataInicioCobranca->modify('first day of this month');
    }
    $dataInicioCobranca->setDate(
        (int)$dataInicioCobranca->format('Y'),
        (int)$dataInicioCobranca->format('m'),
        $diaVencimento
    );
    
    echo "\n📅 Datas Calculadas:\n";
    echo "   - Data Matrícula: " . $dataMatricula->format('d/m/Y') . "\n";
    echo "   - Data Início: " . $dataInicio->format('d/m/Y') . "\n";
    echo "   - Data Vencimento: " . $dataVencimento->format('d/m/Y') . "\n";
    echo "   - Dia Vencimento: $diaVencimento\n";
    echo "   - Próxima Data Vencimento: " . $proximaDataVencimento->format('d/m/Y') . " ← CONTROLA BLOQUEIO\n";
    echo "   - Data Início Cobrança: " . $dataInicioCobranca->format('d/m/Y') . "\n";
    echo "   - Período Teste: SIM (valor 0)\n";
    
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
    // 8. Inserir Matrícula Retroativa
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
        ':periodo_teste' => 1, // Período teste
        ':data_inicio_cobranca' => $dataInicioCobranca->format('Y-m-d'),
        ':proxima_data_vencimento' => $proximaDataVencimento->format('Y-m-d')
    ]);
    
    $matriculaId = $conn->lastInsertId();
    
    $conn->commit();
    
    echo "\n✅ MATRÍCULA CRIADA COM SUCESSO!\n";
    echo "   ID: $matriculaId\n";
    echo "   Aluno: {$aluno['nome']}\n";
    echo "   Plano: {$plano['nome']}\n";
    echo "   Valor: R$ " . number_format($plano['valor'], 2, ',', '.') . "\n";
    
    echo "\n📊 COMPORTAMENTO ESPERADO:\n";
    echo "   ✅ 06/02 (HOJE) - Check-in liberado\n";
    echo "   ✅ 07/02 - Check-in liberado\n";
    echo "   ✅ 08/02 - Check-in liberado (último dia)\n";
    echo "   ❌ 09/02 - Check-in BLOQUEADO (30 dias completos)\n";
    
    echo "\n💰 FINANCEIRO:\n";
    echo "   - Cobrança gerada em: 01/02/2026 (dia escolhido)\n";
    echo "   - Mas acesso garantido até: 08/02/2026 (30 dias completos)\n";
    
    echo "\n🔍 CONSULTAR MATRÍCULA:\n";
    echo "   SELECT * FROM matriculas WHERE id = $matriculaId;\n";
    
} catch (Exception $e) {
    $conn->rollBack();
    echo "\n❌ ERRO: " . $e->getMessage() . "\n";
    exit(1);
}
