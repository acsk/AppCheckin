<?php
// Teste: Simular matrículas sem pagamento e ver se o job as cancelaria

$db = require __DIR__ . '/config/database.php';

echo "=== TESTE: MATRÍCULAS SEM PAGAMENTO ===\n\n";

// Inserir matrículas de teste sem pagamento para testar o job
// (Vamos fazer isso em uma transação, depois reverter)

$db->beginTransaction();

try {
    // Inserir 2 matrículas de teste para Carolina (Yoga, sem pagamento)
    $sqlInsert = "
        INSERT INTO matriculas 
        (tenant_id, usuario_id, plano_id, data_matricula, data_inicio, data_vencimento, valor, status, criado_por, created_at, updated_at)
        VALUES 
        (5, 11, 23, '2026-01-10', '2026-01-10', '2026-02-10', '100.00', 'pendente', 9, NOW(), NOW()),
        (5, 11, 23, '2026-01-11', '2026-01-11', '2026-02-11', '100.00', 'pendente', 9, NOW(), NOW())
    ";
    
    $db->exec($sqlInsert);
    
    echo "✅ Matrículas de teste inseridas\n";
    echo "   (Yoga - sem pagamento - dias 10 e 11)\n\n";
    
    // Agora simular o job
    echo "=== SIMULANDO O JOB ===\n\n";
    
    $sqlMatriculas = "
        SELECT m.id, m.data_matricula, m.status,
               p.nome as plano_nome, mo.nome as modalidade_nome, mo.id as modalidade_id,
               COUNT(DISTINCT pp.id) as total_pagamentos
        FROM matriculas m
        INNER JOIN planos p ON m.plano_id = p.id
        INNER JOIN modalidades mo ON p.modalidade_id = mo.id
        LEFT JOIN pagamentos_plano pp ON m.id = pp.matricula_id
        WHERE m.usuario_id = 11
        AND m.tenant_id = 5
        AND m.status IN ('ativa', 'pendente')
        GROUP BY m.id
        ORDER BY mo.id, m.data_matricula DESC
    ";
    
    $stmt = $db->prepare($sqlMatriculas);
    $stmt->execute();
    $matriculas = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    
    // Agrupar por modalidade
    $porModalidade = [];
    foreach ($matriculas as $m) {
        $mod = $m['modalidade_id'];
        if (!isset($porModalidade[$mod])) {
            $porModalidade[$mod] = ['nome' => $m['modalidade_nome'], 'list' => []];
        }
        $porModalidade[$mod]['list'][] = $m;
    }
    
    foreach ($porModalidade as $mod) {
        echo "📚 {$mod['nome']}:\n";
        
        // Ordenar
        usort($mod['list'], function($a, $b) {
            $temPagtoA = (int)$a['total_pagamentos'] > 0 ? 1 : 0;
            $temPagtoB = (int)$b['total_pagamentos'] > 0 ? 1 : 0;
            
            if ($temPagtoA !== $temPagtoB) {
                return $temPagtoB - $temPagtoA;
            }
            
            $dataA = strtotime($a['data_matricula']);
            $dataB = strtotime($b['data_matricula']);
            return $dataB - $dataA;
        });
        
        foreach ($mod['list'] as $idx => $m) {
            $pagto = (int)$m['total_pagamentos'] > 0 ? "✅ {$m['total_pagamentos']} pagto(s)" : "❌ SEM pagamento";
            if ($idx === 0) {
                echo "  ✓ MANTER: [{$m['id']}] {$m['plano_nome']} ({$m['data_matricula']}) $pagto\n";
            } else {
                echo "  ✗ CANCELAR: [{$m['id']}] {$m['plano_nome']} ({$m['data_matricula']}) $pagto\n";
            }
        }
        echo "\n";
    }
    
    // Rollback para não salvar os dados de teste
    $db->rollBack();
    echo "✅ Teste concluído (dados de teste removidos)\n";
    
} catch (Exception $e) {
    $db->rollBack();
    echo "❌ Erro: " . $e->getMessage() . "\n";
}
