<?php
/**
 * Debug do endpoint comprar-plano
 * Acesse diretamente via: http://localhost:8080/debug_comprar_plano.php
 */

header('Content-Type: application/json');
ini_set('display_errors', 1);
error_reporting(E_ALL);

try {
    // Usar a conexão configurada do sistema
    require_once __DIR__ . '/../config/database.php';
    
    $results = [];
    $results['step'] = 'start';
    
    // Simular dados do comprarPlano
    $tenantId = 1;
    $userId = 23;
    $planoId = 10;
    $planoCicloId = 11;
    $alunoId = 1; // Vamos buscar o real
    
    $results['step'] = 'buscar_aluno';
    
    // Buscar aluno_id do usuário logado
    $stmtAluno = $pdo->prepare("SELECT id FROM alunos WHERE usuario_id = ?");
    $stmtAluno->execute([$userId]);
    $aluno = $stmtAluno->fetch(PDO::FETCH_ASSOC);
    
    if (!$aluno) {
        echo json_encode(['success' => false, 'error' => 'Aluno não encontrado para userId=' . $userId]);
        exit;
    }
    
    $alunoId = $aluno['id'];
    $results['aluno_id'] = $alunoId;
    $results['step'] = 'buscar_plano';
    
    // Buscar plano
    $stmtPlano = $pdo->prepare("
        SELECT p.*, m.nome as modalidade_nome 
        FROM planos p 
        LEFT JOIN modalidades m ON p.modalidade_id = m.id
        WHERE p.id = ? AND p.tenant_id = ? AND p.ativo = 1
    ");
    $stmtPlano->execute([$planoId, $tenantId]);
    $plano = $stmtPlano->fetch(PDO::FETCH_ASSOC);
    
    if (!$plano) {
        echo json_encode(['success' => false, 'error' => 'Plano não encontrado']);
        exit;
    }
    
    $results['plano'] = ['id' => $plano['id'], 'nome' => $plano['nome'], 'valor' => $plano['valor']];
    $results['step'] = 'buscar_ciclo';
    
    // Buscar ciclo
    $stmtCiclo = $pdo->prepare("
        SELECT pc.*, tc.nome as ciclo_nome, tc.codigo as ciclo_codigo
        FROM plano_ciclos pc
        INNER JOIN tipos_ciclo tc ON tc.id = pc.tipo_ciclo_id
        WHERE pc.id = ? AND pc.plano_id = ? AND pc.tenant_id = ? AND pc.ativo = 1
    ");
    $stmtCiclo->execute([$planoCicloId, $planoId, $tenantId]);
    $ciclo = $stmtCiclo->fetch(PDO::FETCH_ASSOC);
    
    if (!$ciclo) {
        echo json_encode(['success' => false, 'error' => 'Ciclo não encontrado', 'params' => [$planoCicloId, $planoId, $tenantId]]);
        exit;
    }
    
    $results['ciclo'] = $ciclo;
    $results['step'] = 'verificar_matricula_ativa';
    
    // Verificar se já existe matrícula ativa na mesma modalidade
    $stmtAtiva = $pdo->prepare("
        SELECT m.id, m.status_id, m.proxima_data_vencimento, p.modalidade_id, sm.codigo
        FROM matriculas m
        INNER JOIN planos p ON p.id = m.plano_id
        INNER JOIN status_matricula sm ON sm.id = m.status_id
        WHERE m.aluno_id = ? 
        AND m.tenant_id = ? 
        AND p.modalidade_id = ?
        AND sm.codigo = 'ativa' 
        AND m.proxima_data_vencimento >= CURDATE()
        ORDER BY m.created_at DESC
        LIMIT 1
    ");
    $stmtAtiva->execute([$alunoId, $tenantId, $plano['modalidade_id']]);
    $matriculaAtiva = $stmtAtiva->fetch(PDO::FETCH_ASSOC);
    
    $results['matricula_ativa_existente'] = $matriculaAtiva ? $matriculaAtiva : null;
    
    if ($matriculaAtiva) {
        echo json_encode(['success' => false, 'error' => 'Já existe matrícula ativa', 'matricula' => $matriculaAtiva]);
        exit;
    }
    
    $results['step'] = 'preparar_insert';
    
    // Preparar dados para INSERT
    $valorCompra = (float) $ciclo['valor'];
    $duracaoMeses = (int) $ciclo['meses'];
    $cicloNome = $ciclo['ciclo_nome'];
    $diaVencimento = 5;
    
    $dataInicio = date('Y-m-d');
    $dataMatricula = $dataInicio;
    $dataInicioObj = new DateTime($dataInicio);
    
    $dataVencimento = clone $dataInicioObj;
    if ($duracaoMeses > 1) {
        $dataVencimento->modify("+{$duracaoMeses} months");
    } else {
        $duracaoDias = (int) $plano['duracao_dias'];
        $dataVencimento->modify("+{$duracaoDias} days");
    }
    
    $proximaDataVencimento = clone $dataVencimento;
    
    // Buscar status "pendente"
    $stmtStatus = $pdo->prepare("SELECT id FROM status_matricula WHERE codigo = 'pendente'");
    $stmtStatus->execute();
    $statusRow = $stmtStatus->fetch(PDO::FETCH_ASSOC);
    $statusId = $statusRow['id'] ?? 5;
    
    // Buscar motivo "nova"
    $stmtMotivo = $pdo->prepare("SELECT id FROM motivo_matricula WHERE codigo = 'nova'");
    $stmtMotivo->execute();
    $motivoRow = $stmtMotivo->fetch(PDO::FETCH_ASSOC);
    $motivoId = $motivoRow['id'] ?? 1;
    
    $insertParams = [
        $tenantId,
        $alunoId,
        $planoId,
        $planoCicloId,
        $dataMatricula,
        $dataInicio,
        $dataVencimento->format('Y-m-d'),
        $valorCompra,
        $statusId,
        $motivoId,
        $diaVencimento,
        $proximaDataVencimento->format('Y-m-d'),
        $userId
    ];
    
    $results['insert_params'] = $insertParams;
    $results['step'] = 'testar_insert';
    
    // Testar INSERT (com rollback)
    $pdo->beginTransaction();
    
    $stmtInsert = $pdo->prepare("
        INSERT INTO matriculas 
        (tenant_id, aluno_id, plano_id, plano_ciclo_id, data_matricula, data_inicio, data_vencimento, 
         valor, status_id, motivo_id, dia_vencimento, periodo_teste, proxima_data_vencimento, criado_por)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?)
    ");
    
    $stmtInsert->execute($insertParams);
    $matriculaId = (int) $pdo->lastInsertId();
    
    $results['matricula_id_teste'] = $matriculaId;
    $results['insert_success'] = true;
    
    $pdo->rollBack();
    $results['step'] = 'completed';
    
    echo json_encode(['success' => true, 'debug' => $results], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false, 
        'error' => $e->getMessage(),
        'code' => $e->getCode(),
        'step' => $results['step'] ?? 'unknown'
    ], JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'step' => $results['step'] ?? 'unknown'
    ], JSON_PRETTY_PRINT);
}
exit;
