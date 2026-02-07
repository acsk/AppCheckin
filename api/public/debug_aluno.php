<?php
header('Content-Type: application/json');

try {
    require_once __DIR__ . '/../config/database.php';
    
    $results = [];
    $userId = 10;
    $tenantId = 2;
    $planoId = 10;
    $planoCicloId = 11;
    
    // 1. Buscar aluno pelo usuario_id
    $stmt = $pdo->prepare("SELECT id FROM alunos WHERE usuario_id = ?");
    $stmt->execute([$userId]);
    $aluno = $stmt->fetch();
    $results['aluno'] = $aluno;
    
    if (!$aluno) {
        throw new Exception('Aluno não encontrado para usuario_id=' . $userId);
    }
    
    $alunoId = $aluno['id'];
    $results['aluno_id'] = $alunoId;
    
    // 2. Buscar plano
    $stmt = $pdo->prepare("
        SELECT p.*, m.nome as modalidade_nome 
        FROM planos p 
        LEFT JOIN modalidades m ON p.modalidade_id = m.id
        WHERE p.id = ? AND p.tenant_id = ? AND p.ativo = 1
    ");
    $stmt->execute([$planoId, $tenantId]);
    $plano = $stmt->fetch();
    $results['plano'] = $plano;
    
    if (!$plano) {
        throw new Exception('Plano não encontrado ou inativo');
    }
    
    // 3. Buscar ciclo
    $stmt = $pdo->prepare("
        SELECT pc.*, tc.nome as ciclo_nome, tc.codigo as ciclo_codigo
        FROM plano_ciclos pc
        INNER JOIN tipos_ciclo tc ON tc.id = pc.tipo_ciclo_id
        WHERE pc.id = ? AND pc.plano_id = ? AND pc.tenant_id = ? AND pc.ativo = 1
    ");
    $stmt->execute([$planoCicloId, $planoId, $tenantId]);
    $ciclo = $stmt->fetch();
    $results['ciclo'] = $ciclo;
    
    if (!$ciclo) {
        throw new Exception('Ciclo não encontrado');
    }
    
    // 4. Verificar tabela matriculas - listar colunas
    $stmt = $pdo->query("SHOW COLUMNS FROM matriculas");
    $results['matriculas_columns'] = array_column($stmt->fetchAll(), 'Field');
    
    // 5. Simular insert
    $valorCompra = (float) $ciclo['valor'];
    $duracaoMeses = (int) $ciclo['meses'];
    
    $results['teste_insert'] = [
        'aluno_id' => $alunoId,
        'plano_id' => $planoId,
        'plano_ciclo_id' => $planoCicloId,
        'valor' => $valorCompra,
        'duracao_meses' => $duracaoMeses,
        'tenant_id' => $tenantId
    ];
    
    echo json_encode(['success' => true, 'debug' => $results], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()], JSON_PRETTY_PRINT);
}
