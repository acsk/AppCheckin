<?php
/**
 * =========================================================
 * SEED: Matrícula de Teste que VENCE HOJE
 * Data: 06/02/2026
 * =========================================================
 * 
 * Cenário de Teste:
 * - Matrícula criada em 07/01/2026
 * - Plano: 2x Semana - Teste Gratuito (30 dias)
 * - Dia vencimento escolhido: 6
 * - Período teste: SIM (valor 0)
 * - Data início cobrança: 06/02/2026 (HOJE!) ⏰
 * - Próxima data vencimento: 06/02/2026 (HOJE!)
 * 
 * Objetivo:
 * Testar o endpoint /processar-cobranca que deve migrar
 * esta matrícula de teste gratuito para plano pago hoje.
 */

require_once __DIR__ . '/../../config/database.php';

$conn = $pdo;

try {
    $conn->beginTransaction();
    
    // ===========================================
    // 1. Verificar Tenant 2
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
    // 2. Buscar Plano de Teste: 2x Semana
    // ===========================================
    $stmtPlano = $conn->prepare("
        SELECT id, nome, valor, duracao_dias, checkins_semanais
        FROM planos 
        WHERE tenant_id = 2 
          AND valor = 0.00 
          AND checkins_semanais = 2
          AND ativo = 1
        LIMIT 1
    ");
    $stmtPlano->execute();
    $plano = $stmtPlano->fetch(PDO::FETCH_ASSOC);
    
    if (!$plano) {
        throw new Exception("❌ Plano teste 2x semana não encontrado!");
    }
    
    echo "✅ Plano selecionado: {$plano['nome']} - {$plano['duracao_dias']} dias\n";
    
    // ===========================================
    // 3. Buscar Aluno Diferente
    // ===========================================
    $stmtAluno = $conn->prepare("
        SELECT a.id, a.nome, u.email 
        FROM alunos a
        INNER JOIN usuarios u ON a.usuario_id = u.id
        INNER JOIN tenant_usuario_papel tup ON a.usuario_id = tup.usuario_id
        WHERE tup.tenant_id = 2
          AND tup.papel_id = 1
          AND a.ativo = 1
          AND a.id NOT IN (4, 24)
        ORDER BY a.id ASC
        LIMIT 1
    ");
    $stmtAluno->execute();
    $aluno = $stmtAluno->fetch(PDO::FETCH_ASSOC);
    
    if (!$aluno) {
        throw new Exception("❌ Nenhum aluno disponível!");
    }
    
    echo "✅ Aluno selecionado: {$aluno['nome']} (ID: {$aluno['id']})\n";
    
    // ===========================================
    // 4. Buscar Status Ativo
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
    // 5. Calcular Datas - VENCE HOJE!
    // ===========================================
    $dataInicio = new DateTime('2026-01-07'); // 07/01/2026
    $dataMatricula = clone $dataInicio;
    $dataVencimento = clone $dataInicio;
    $dataVencimento->modify("+{$plano['duracao_dias']} days"); // 06/02/2026
    
    // Próxima data vencimento = HOJE
    $proximaDataVencimento = new DateTime('2026-02-06'); // HOJE!
    
    // Dia vencimento: 6
    $diaVencimento = 6;
    
    // Data início cobrança: HOJE
    $dataInicioCobranca = new DateTime('2026-02-06'); // HOJE!
    
    echo "\n📅 Datas Calculadas:\n";
    echo "   - Data Matrícula: " . $dataMatricula->format('d/m/Y') . "\n";
    echo "   - Data Início: " . $dataInicio->format('d/m/Y') . "\n";
    echo "   - Data Vencimento: " . $dataVencimento->format('d/m/Y') . "\n";
    echo "   - Dia Vencimento: $diaVencimento\n";
    echo "   - Próxima Data Vencimento: " . $proximaDataVencimento->format('d/m/Y') . " ⏰ HOJE!\n";
    echo "   - Data Início Cobrança: " . $dataInicioCobranca->format('d/m/Y') . " ⏰ HOJE!\n";
    echo "   - Período Teste: SIM\n";
    
    // ===========================================
    // 6. Verificar se já existe
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
    // 7. Inserir Matrícula que Vence HOJE
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
            1,
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
        ':dia_vencimento' => $diaVencimento,
        ':periodo_teste' => 1,
        ':data_inicio_cobranca' => $dataInicioCobranca->format('Y-m-d'),
        ':proxima_data_vencimento' => $proximaDataVencimento->format('Y-m-d')
    ]);
    
    $matriculaId = $conn->lastInsertId();
    
    $conn->commit();
    
    echo "\n✅ MATRÍCULA DE TESTE CRIADA - VENCE HOJE!\n";
    echo "   ID: $matriculaId\n";
    echo "   Aluno: {$aluno['nome']}\n";
    echo "   Plano Atual: {$plano['nome']}\n";
    echo "   Valor Atual: R$ 0,00\n";
    
    echo "\n⏰ ATENÇÃO - VENCE HOJE (06/02/2026)!\n";
    echo "   - Data Início Cobrança: HOJE\n";
    echo "   - Período Teste: Acabou\n";
    echo "   - Check-in: Último dia de acesso\n";
    
    echo "\n🧪 COMO TESTAR:\n";
    echo "\n1️⃣ Ver na lista de próximas cobranças:\n";
    echo "   GET /admin/matriculas/proximas-cobrancas?dias=1\n";
    
    echo "\n2️⃣ Processar migração para plano pago:\n";
    echo "   POST /admin/matriculas/processar-cobranca\n";
    
    echo "\n3️⃣ Verificar que migrou:\n";
    echo "   SELECT * FROM matriculas WHERE id = $matriculaId;\n";
    echo "   (Deve mostrar plano_id = 2, periodo_teste = 0, valor = 100.00)\n";
    
    echo "\n💰 PLANO QUE SERÁ ATRIBUÍDO:\n";
    echo "   - Nome: 2x por Semana\n";
    echo "   - Valor: R$ 100,00\n";
    echo "   - Checkins: 2x por semana\n";
    
    echo "\n🔍 CONSULTA RÁPIDA:\n";
    echo "   docker exec appcheckin_mysql mysql -uroot -proot appcheckin \\\n";
    echo "     -e \"SELECT id, aluno_id, plano_id, periodo_teste, data_inicio_cobranca FROM matriculas WHERE id = $matriculaId;\"\n";
    
} catch (Exception $e) {
    $conn->rollBack();
    echo "\n❌ ERRO: " . $e->getMessage() . "\n";
    exit(1);
}
