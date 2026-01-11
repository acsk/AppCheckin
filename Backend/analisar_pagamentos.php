<?php
// Analisar matrículas e seus pagamentos

$db = require __DIR__ . '/config/database.php';

echo "=== ANÁLISE DE MATRÍCULAS E PAGAMENTOS ===\n\n";

// Buscar matrículas com info de pagamentos
$sqlMatriculas = "
    SELECT m.id, m.usuario_id, u.nome as usuario_nome, m.data_matricula, m.status,
           p.nome as plano_nome, mo.nome as modalidade_nome, mo.id as modalidade_id,
           COUNT(pp.id) as total_pagamentos,
           m.tenant_id
    FROM matriculas m
    INNER JOIN usuarios u ON m.usuario_id = u.id
    INNER JOIN planos p ON m.plano_id = p.id
    INNER JOIN modalidades mo ON p.modalidade_id = mo.id
    LEFT JOIN pagamentos_plano pp ON m.id = pp.matricula_id
    WHERE m.status IN ('ativa', 'pendente')
    AND m.tenant_id = 5
    GROUP BY m.id
    ORDER BY m.usuario_id, mo.id, m.data_matricula DESC
";

$stmt = $db->prepare($sqlMatriculas);
$stmt->execute();
$matriculas = $stmt->fetchAll(\PDO::FETCH_ASSOC);

$usuarioAtual = null;
foreach ($matriculas as $m) {
    if ($usuarioAtual !== $m['usuario_id']) {
        if ($usuarioAtual !== null) {
            echo "\n";
        }
        $usuarioAtual = $m['usuario_id'];
        echo "👤 {$m['usuario_nome']}\n";
    }
    
    $pagtoStatus = (int)$m['total_pagamentos'] > 0 ? "✅ {$m['total_pagamentos']} pagamento(s)" : "❌ SEM PAGAMENTO";
    echo "  [{$m['id']}] {$m['plano_nome']} - {$m['modalidade_nome']} ({$m['data_matricula']}) Status: {$m['status']} $pagtoStatus\n";
}

echo "\n\n=== RESUMO ===\n";
echo "Total de matrículas: " . count($matriculas) . "\n";

$comPagamento = array_filter($matriculas, function($m) {
    return (int)$m['total_pagamentos'] > 0;
});
$semPagamento = array_filter($matriculas, function($m) {
    return (int)$m['total_pagamentos'] === 0;
});

echo "Com pagamento: " . count($comPagamento) . "\n";
echo "Sem pagamento: " . count($semPagamento) . "\n";
