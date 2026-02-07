<?php
/**
 * =========================================================
 * SEED 1: Matrícula - Deve GERAR COBRANÇA mas NÃO MIGRAR
 * Data: 06/02/2026
 * =========================================================
 * 
 * Cenário:
 * - Cobrança iniciou em 01/02 (5 dias atrás)
 * - Mas o aluno AINDA TEM 5 DIAS de acesso (até 11/02)
 * - Sistema deve: GERAR COBRANÇA mas NÃO MIGRAR o plano
 * 
 * Timeline:
 * 12/01 - Matrícula criada
 * 01/02 - Cobrança iniciada (data_inicio_cobranca)
 * 06/02 - HOJE - Ainda tem acesso ✅
 * 11/02 - Acesso vence (proxima_data_vencimento)
 */

require_once __DIR__ . '/../../config/database.php';

$conn = $pdo;

try {
    $conn->beginTransaction();
    
    echo "🔄 SEED 1: Matrícula com cobrança iniciada, mas acesso válido\n\n";
    
    // ===========================================
    // Buscar dados necessários
    // ===========================================
    $tenant = $conn->query("SELECT id, nome FROM tenants WHERE id = 2")->fetch(PDO::FETCH_ASSOC);
    
    $plano = $conn->query("
        SELECT id, nome, valor, duracao_dias, checkins_semanais
        FROM planos 
        WHERE tenant_id = 2 AND valor = 0.00 AND checkins_semanais = 3 AND ativo = 1
        LIMIT 1
    ")->fetch(PDO::FETCH_ASSOC);
    
    $aluno = $conn->query("
        SELECT a.id, a.nome, u.email 
        FROM alunos a
        INNER JOIN usuarios u ON a.usuario_id = u.id
        INNER JOIN tenant_usuario_papel tup ON a.usuario_id = tup.usuario_id
        WHERE tup.tenant_id = 2 AND tup.papel_id = 1 AND a.ativo = 1
        AND a.id NOT IN (4, 5, 24)
        LIMIT 1
    ")->fetch(PDO::FETCH_ASSOC);
    
    $status = $conn->query("SELECT id FROM status_matricula WHERE nome = 'Ativa'")->fetch(PDO::FETCH_ASSOC);
    
    if (!$tenant || !$plano || !$aluno || !$status) {
        throw new Exception("❌ Dados necessários não encontrados!");
    }
    
    echo "✅ Tenant: {$tenant['nome']}\n";
    echo "✅ Plano: {$plano['nome']}\n";
    echo "✅ Aluno: {$aluno['nome']}\n\n";
    
    // ===========================================
    // Calcular Datas
    // ===========================================
    $dataInicio = new DateTime('2026-01-12'); // 12/01
    $dataMatricula = clone $dataInicio;
    $dataVencimento = clone $dataInicio;
    $dataVencimento->modify("+{$plano['duracao_dias']} days");
    
    $proximaDataVencimento = new DateTime('2026-02-11'); // 11/02 (ainda tem 5 dias)
    $dataInicioCobranca = new DateTime('2026-02-01'); // 01/02 (já passou)
    $diaVencimento = 1;
    
    echo "📅 Datas:\n";
    echo "   Início: " . $dataInicio->format('d/m/Y') . "\n";
    echo "   Início Cobrança: " . $dataInicioCobranca->format('d/m/Y') . " ✅ (já iniciou)\n";
    echo "   Próximo Vencimento: " . $proximaDataVencimento->format('d/m/Y') . " ⏰ (ainda tem 5 dias)\n";
    echo "   Dia Vencimento: $diaVencimento\n\n";
    
    // Verificar se já existe
    $check = $conn->prepare("SELECT id FROM matriculas WHERE aluno_id = :aluno_id AND data_inicio = :data_inicio");
    $check->execute([':aluno_id' => $aluno['id'], ':data_inicio' => $dataInicio->format('Y-m-d')]);
    if ($check->fetch()) {
        echo "⚠️  Matrícula já existe\n";
        $conn->rollBack();
        exit(0);
    }
    
    // ===========================================
    // Inserir Matrícula
    // ===========================================
    $stmt = $conn->prepare("
        INSERT INTO matriculas (
            tenant_id, aluno_id, plano_id, data_matricula, data_inicio,
            data_vencimento, valor, status_id, motivo_id, dia_vencimento,
            periodo_teste, data_inicio_cobranca, proxima_data_vencimento, created_at
        ) VALUES (
            :tenant_id, :aluno_id, :plano_id, :data_matricula, :data_inicio,
            :data_vencimento, :valor, :status_id, 1, :dia_vencimento,
            1, :data_inicio_cobranca, :proxima_data_vencimento, NOW()
        )
    ");
    
    $stmt->execute([
        ':tenant_id' => $tenant['id'],
        ':aluno_id' => $aluno['id'],
        ':plano_id' => $plano['id'],
        ':data_matricula' => $dataMatricula->format('Y-m-d'),
        ':data_inicio' => $dataInicio->format('Y-m-d'),
        ':data_vencimento' => $dataVencimento->format('Y-m-d'),
        ':valor' => $plano['valor'],
        ':status_id' => $status['id'],
        ':dia_vencimento' => $diaVencimento,
        ':data_inicio_cobranca' => $dataInicioCobranca->format('Y-m-d'),
        ':proxima_data_vencimento' => $proximaDataVencimento->format('Y-m-d')
    ]);
    
    $matriculaId = $conn->lastInsertId();
    $conn->commit();
    
    echo "✅ SEED 1 CRIADO - ID: $matriculaId\n";
    echo "   Aluno: {$aluno['nome']}\n";
    echo "   Plano: {$plano['nome']} (R$ 0,00)\n";
    echo "   Status: Cobrança iniciada, mas ACESSO VÁLIDO\n\n";
    
    echo "🎯 COMPORTAMENTO ESPERADO:\n";
    echo "   ✅ Aparece em /proximas-cobrancas\n";
    echo "   ✅ /processar-cobranca GERA cobrança\n";
    echo "   ❌ /processar-cobranca NÃO MIGRA plano (ainda tem 5 dias)\n";
    echo "   ✅ Check-in funciona normalmente até 11/02\n\n";
    
} catch (Exception $e) {
    $conn->rollBack();
    echo "\n❌ ERRO: " . $e->getMessage() . "\n";
    exit(1);
}
