<?php
// Teste: Simular exatamente os dados da imagem

$db = require __DIR__ . '/config/database.php';

echo "=== TESTE COM DADOS DA IMAGEM ===\n\n";

$db->beginTransaction();

try {
    // Limpar dados antigos de teste
    $db->exec("DELETE FROM matriculas WHERE usuario_id = 11 AND data_matricula >= '2026-01-09'");
    
    // Inserir matrículas exatamente como na imagem
    // Carolina Ferreira - CrossFit
    $db->exec("
        INSERT INTO matriculas 
        (tenant_id, usuario_id, plano_id, data_matricula, data_inicio, data_vencimento, valor, status, criado_por, created_at, updated_at)
        VALUES 
        (5, 11, 23, '2026-01-10', '2026-01-10', '2026-02-10', '100.00', 'pendente', 9, '2026-01-10 08:00:00', NOW()),
        (5, 11, 23, '2026-01-11', '2026-01-11', '2026-02-11', '100.00', 'pendente', 9, '2026-01-11 09:00:00', NOW()),
        (5, 11, 24, '2026-01-11', '2026-01-11', '2026-02-11', '130.00', 'pendente', 9, '2026-01-11 10:00:00', NOW()),
        (5, 11, 19, '2026-01-09', '2026-01-09', '2026-02-09', '150.00', 'pendente', 9, '2026-01-09 08:00:00', NOW()),
        (5, 11, 18, '2026-01-09', '2026-01-09', '2026-02-09', '120.00', 'pendente', 9, '2026-01-09 09:00:00', NOW())
    ");
    
    echo "✅ Dados inseridos (simulando a imagem)\n\n";
    
    // Mostrar dados
    $result = $db->query("
        SELECT m.id, m.data_matricula, m.created_at, m.status,
               p.nome as plano, mo.nome as modalidade,
               COUNT(DISTINCT pp.id) as pagamentos
        FROM matriculas m
        INNER JOIN planos p ON m.plano_id = p.id
        INNER JOIN modalidades mo ON p.modalidade_id = mo.id
        LEFT JOIN pagamentos_plano pp ON m.id = pp.matricula_id
        WHERE m.usuario_id = 11 AND m.tenant_id = 5 AND m.data_matricula >= '2026-01-09'
        GROUP BY m.id
        ORDER BY mo.id, m.data_matricula DESC, m.created_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    echo "=== DADOS ANTES ===\n";
    foreach ($result as $r) {
        $pagto = $r['pagamentos'] > 0 ? '✅ ' . $r['pagamentos'] . ' pgtos' : '❌ SEM pgto';
        echo "[{$r['id']}] {$r['plano']} - {$r['modalidade']} | Data: {$r['data_matricula']} | Criado: {$r['created_at']} | {$pagto}\n";
    }
    echo "\n";
    
    // LÓGICA CORRIGIDA
    echo "=== APLICANDO LÓGICA DE LIMPEZA ===\n\n";
    
    // Agrupar por modalidade
    $porModalidade = [];
    foreach ($result as $m) {
        $mod = $m['modalidade'];
        if (!isset($porModalidade[$mod])) {
            $porModalidade[$mod] = [];
        }
        $porModalidade[$mod][] = $m;
    }
    
    $canceladas = [];
    
    foreach ($porModalidade as $modalidade => $matriculas) {
        echo "📚 {$modalidade}:\n";
        
        // Ordenar por data mais recente, depois por created_at mais recente
        usort($matriculas, function($a, $b) {
            $dataA = strtotime($a['data_matricula']);
            $dataB = strtotime($b['data_matricula']);
            
            if ($dataA === $dataB) {
                // Mesmo dia, ordena por created_at
                $criadoA = strtotime($a['created_at']);
                $criadoB = strtotime($b['created_at']);
                return $criadoB - $criadoA;
            }
            
            return $dataB - $dataA;
        });
        
        foreach ($matriculas as $idx => $m) {
            if ($idx === 0) {
                echo "  ✓ MANTER [ID {$m['id']}]: {$m['plano']} | Data: {$m['data_matricula']} | Criado: {$m['created_at']}\n";
            } else {
                echo "  ✗ CANCELAR [ID {$m['id']}]: {$m['plano']} | Data: {$m['data_matricula']} | Criado: {$m['created_at']}\n";
                $canceladas[] = $m['id'];
            }
        }
        echo "\n";
    }
    
    // Executar cancelamentos
    foreach ($canceladas as $id) {
        $db->exec("UPDATE matriculas SET status = 'cancelada' WHERE id = $id");
    }
    
    $db->commit();
    
    echo "=== RESULTADO ===\n";
    echo "Total de matrículas canceladas: " . count($canceladas) . "\n\n";
    
    // Mostrar estado final
    echo "=== DADOS DEPOIS ===\n";
    $result = $db->query("
        SELECT m.id, m.data_matricula, m.created_at, m.status,
               p.nome as plano, mo.nome as modalidade
        FROM matriculas m
        INNER JOIN planos p ON m.plano_id = p.id
        INNER JOIN modalidades mo ON p.modalidade_id = mo.id
        WHERE m.usuario_id = 11 AND m.tenant_id = 5 AND m.data_matricula >= '2026-01-09'
        ORDER BY mo.id, m.data_matricula DESC, m.created_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($result as $r) {
        $status = $r['status'] === 'cancelada' ? '❌ CANCELADA' : '✅ ' . strtoupper($r['status']);
        echo "[{$r['id']}] {$r['plano']} - {$r['modalidade']} | Data: {$r['data_matricula']} | {$status}\n";
    }
    
} catch (Exception $e) {
    $db->rollBack();
    echo "❌ Erro: " . $e->getMessage() . "\n";
}
