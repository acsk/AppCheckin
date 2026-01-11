<?php
// Teste: Criar matrículas sem pagamento e executar o job para cancelá-las

require __DIR__ . '/config/database.php';
$db = require __DIR__ . '/config/database.php';

echo "=== TESTE COMPLETO DO JOB ===\n";
echo "1. Criando matrículas de teste sem pagamento...\n\n";

$db->beginTransaction();

try {
    // Inserir 2 matrículas de teste sem pagamento para Carolina em CrossFit
    $sqlInsert = "
        INSERT INTO matriculas 
        (tenant_id, usuario_id, plano_id, data_matricula, data_inicio, data_vencimento, valor, status, criado_por, created_at, updated_at)
        VALUES 
        (5, 11, 23, '2026-01-10', '2026-01-10', '2026-02-10', '100.00', 'pendente', 9, NOW(), NOW()),
        (5, 11, 23, '2026-01-11', '2026-01-11', '2026-02-11', '100.00', 'pendente', 9, NOW(), NOW())
    ";
    
    $db->exec($sqlInsert);
    echo "✅ Matrículas inseridas (IDs serão 19-20)\n";
    echo "   └─ CarolinaFerreira: CrossFit 1x (sem pagamento)\n\n";
    
    // Commit para salvar os dados
    $db->commit();
    
    // Agora executar o job
    echo "2. Executando o job de limpeza...\n\n";
    
    // Simular o job aqui
    $sqlMatriculas = "
        SELECT m.id, m.data_matricula, m.status, 
               u.nome as usuario_nome,
               p.nome as plano_nome, mo.nome as modalidade_nome, mo.id as modalidade_id,
               COUNT(DISTINCT pp.id) as total_pagamentos
        FROM matriculas m
        INNER JOIN usuarios u ON m.usuario_id = u.id
        INNER JOIN planos p ON m.plano_id = p.id
        INNER JOIN modalidades mo ON p.modalidade_id = mo.id
        LEFT JOIN pagamentos_plano pp ON m.id = pp.matricula_id
        WHERE m.tenant_id = 5
        AND m.status IN ('ativa', 'pendente')
        GROUP BY m.id
        ORDER BY u.id, mo.id, m.data_matricula DESC
    ";
    
    $stmt = $db->prepare($sqlMatriculas);
    $stmt->execute();
    $matriculas = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    
    // Agrupar por usuário e modalidade
    $porUsuarioMod = [];
    foreach ($matriculas as $m) {
        $userKey = $m['usuario_id'];
        $modKey = $m['modalidade_id'];
        if (!isset($porUsuarioMod[$userKey])) {
            $porUsuarioMod[$userKey] = ['nome' => $m['usuario_nome'], 'modalidades' => []];
        }
        if (!isset($porUsuarioMod[$userKey]['modalidades'][$modKey])) {
            $porUsuarioMod[$userKey]['modalidades'][$modKey] = ['nome' => $m['modalidade_nome'], 'list' => []];
        }
        $porUsuarioMod[$userKey]['modalidades'][$modKey]['list'][] = $m;
    }
    
    $canceladas = 0;
    
    foreach ($porUsuarioMod as $userKey => $userData) {
        if (count($userData['modalidades']) > 1 || 
            (count($userData['modalidades']) === 1 && count(reset($userData['modalidades'])['list']) > 1)) {
            
            echo "👤 {$userData['nome']}\n";
            
            foreach ($userData['modalidades'] as $modData) {
                echo "  📚 {$modData['nome']}:\n";
                
                // Ordenar por: 1) tem pagamento, 2) status, 3) data
                usort($modData['list'], function($a, $b) {
                    $temPagtoA = (int)$a['total_pagamentos'] > 0 ? 1 : 0;
                    $temPagtoB = (int)$b['total_pagamentos'] > 0 ? 1 : 0;
                    
                    if ($temPagtoA !== $temPagtoB) {
                        return $temPagtoB - $temPagtoA;
                    }
                    
                    $statusPriority = ['ativa' => 2, 'pendente' => 1];
                    $priorityA = $statusPriority[$a['status']] ?? 0;
                    $priorityB = $statusPriority[$b['status']] ?? 0;
                    
                    if ($priorityA !== $priorityB) {
                        return $priorityB - $priorityA;
                    }
                    
                    $dataA = strtotime($a['data_matricula']);
                    $dataB = strtotime($b['data_matricula']);
                    return $dataB - $dataA;
                });
                
                foreach ($modData['list'] as $idx => $m) {
                    $pagto = (int)$m['total_pagamentos'] > 0 ? "✅ {$m['total_pagamentos']} pagto(s)" : "❌ SEM pagamento";
                    
                    if ($idx === 0) {
                        echo "    ✓ MANTER: [{$m['id']}] {$m['plano_nome']} ({$m['data_matricula']}) {$pagto}\n";
                    } else {
                        echo "    ✗ CANCELAR: [{$m['id']}] {$m['plano_nome']} ({$m['data_matricula']}) {$pagto}\n";
                        
                        // Cancelar a matrícula
                        $sqlCancel = "UPDATE matriculas SET status = 'cancelada' WHERE id = ?";
                        $stmtCancel = $db->prepare($sqlCancel);
                        $stmtCancel->execute([$m['id']]);
                        $canceladas++;
                    }
                }
                echo "\n";
            }
        }
    }
    
    echo "=== RESULTADO ===\n";
    echo "Matrículas canceladas: $canceladas\n\n";
    
    // Mostrar estado final
    echo "=== ESTADO FINAL ===\n";
    $sqlFinal = "
        SELECT m.id, m.status, p.nome as plano, mo.nome as modalidade,
               COUNT(DISTINCT pp.id) as total_pagamentos
        FROM matriculas m
        INNER JOIN planos p ON m.plano_id = p.id
        INNER JOIN modalidades mo ON p.modalidade_id = mo.id
        LEFT JOIN pagamentos_plano pp ON m.id = pp.matricula_id
        WHERE m.usuario_id = 11 AND m.tenant_id = 5
        GROUP BY m.id
        ORDER BY mo.nome, m.data_matricula DESC
    ";
    
    $stmt = $db->prepare($sqlFinal);
    $stmt->execute();
    $matriculasFinal = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    
    echo "Matrículas de Carolina agora:\n";
    foreach ($matriculasFinal as $m) {
        $status = $m['status'] === 'cancelada' ? '❌ CANCELADA' : '✅ ' . strtoupper($m['status']);
        echo "  [{$m['id']}] {$m['plano']} - {$m['modalidade']} ({$m['total_pagamentos']} pagto) $status\n";
    }
    
} catch (Exception $e) {
    $db->rollBack();
    echo "❌ Erro: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
?>
