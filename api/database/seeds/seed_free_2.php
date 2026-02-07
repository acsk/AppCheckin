<?php
/**
 * =========================================================
 * SEED 2: Matrícula Gratuita com Acesso Vencido
 * Data: 06/02/2026
 * =========================================================
 * 
 * Matrícula gratuita que já venceu (para testar bloqueio).
 */

require_once __DIR__ . '/../../config/database.php';

$conn = $pdo;

try {
    $conn->beginTransaction();
    
    echo "📝 Criando matrícula gratuita com acesso vencido...\n\n";
    
    // Buscar dados
    $tenant = $conn->query("SELECT id, nome FROM tenants WHERE id = 2")->fetch(PDO::FETCH_ASSOC);
    
    $plano = $conn->query("
        SELECT id, nome, valor, duracao_dias
        FROM planos 
        WHERE tenant_id = 2 AND valor = 0.00 AND checkins_semanais = 2 AND ativo = 1
        LIMIT 1
    ")->fetch(PDO::FETCH_ASSOC);
    
    $aluno = $conn->query("
        SELECT a.id, a.nome
        FROM alunos a
        INNER JOIN tenant_usuario_papel tup ON a.usuario_id = tup.usuario_id
        WHERE tup.tenant_id = 2 AND tup.papel_id = 1 AND a.ativo = 1
        ORDER BY a.id DESC
        LIMIT 1
    ")->fetch(PDO::FETCH_ASSOC);
    
    $status = $conn->query("SELECT id FROM status_matricula WHERE nome = 'Ativa'")->fetch(PDO::FETCH_ASSOC);
    
    if (!$tenant || !$plano || !$aluno || !$status) {
        throw new Exception("❌ Dados necessários não encontrados!");
    }
    
    // Datas - VENCIDO
    $dataInicio = new DateTime('2026-01-05');
    $proximaDataVencimento = new DateTime('2026-02-04'); // Venceu 04/02 (2 dias atrás)
    
    echo "Tenant: {$tenant['nome']}\n";
    echo "Plano: {$plano['nome']}\n";
    echo "Aluno: {$aluno['nome']}\n";
    echo "Período: 05/01 a 04/02/2026\n";
    echo "Status: ❌ Acesso vencido (2 dias atrás)\n\n";
    
    // Verificar duplicidade
    $check = $conn->prepare("SELECT id FROM matriculas WHERE aluno_id = ? AND data_inicio = ?");
    $check->execute([$aluno['id'], $dataInicio->format('Y-m-d')]);
    if ($check->fetch()) {
        echo "⚠️  Matrícula já existe\n";
        $conn->rollBack();
        exit(0);
    }
    
    // Inserir
    $stmt = $conn->prepare("
        INSERT INTO matriculas (
            tenant_id, aluno_id, plano_id, data_matricula, data_inicio,
            data_vencimento, valor, status_id, motivo_id, dia_vencimento,
            periodo_teste, data_inicio_cobranca, proxima_data_vencimento, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NULL, 1, NULL, ?, NOW())
    ");
    
    $stmt->execute([
        $tenant['id'],
        $aluno['id'],
        $plano['id'],
        $dataInicio->format('Y-m-d'),
        $dataInicio->format('Y-m-d'),
        $proximaDataVencimento->format('Y-m-d'),
        $plano['valor'],
        $status['id'],
        $proximaDataVencimento->format('Y-m-d')
    ]);
    
    $id = $conn->lastInsertId();
    $conn->commit();
    
    echo "✅ Matrícula criada - ID: $id\n";
    echo "   Check-in: Bloqueado (vencido)\n\n";
    
} catch (Exception $e) {
    $conn->rollBack();
    echo "❌ ERRO: " . $e->getMessage() . "\n";
    exit(1);
}
