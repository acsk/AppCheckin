<?php
$db = require __DIR__ . '/config/database.php';

// Buscar matrícula recente
$stmt = $db->prepare("
    SELECT m.id, m.data_matricula, m.data_inicio, m.valor,
           u.nome as usuario_nome, p.nome as plano_nome
    FROM matriculas m
    INNER JOIN usuarios u ON m.usuario_id = u.id
    INNER JOIN planos p ON m.plano_id = p.id
    WHERE m.tenant_id = 5
    ORDER BY m.id DESC
    LIMIT 1
");
$stmt->execute();
$matricula = $stmt->fetch();

if (!$matricula) {
    echo "❌ Nenhuma matrícula encontrada\n";
    exit;
}

echo "=== MATRÍCULA ===\n";
printf("[%d] %s - %s\n", $matricula['id'], $matricula['usuario_nome'], $matricula['plano_nome']);
printf("Data: %s | Valor: R$ %s\n\n", $matricula['data_matricula'], $matricula['valor']);

// Buscar pagamentos
echo "=== PAGAMENTOS ===\n";
$stmtPagamentos = $db->prepare("
    SELECT 
        id,
        CAST(valor AS DECIMAL(10,2)) as valor,
        data_vencimento,
        data_pagamento,
        status_pagamento_id,
        (SELECT nome FROM status_pagamento WHERE id = status_pagamento_id) as status,
        observacoes
    FROM pagamentos_plano
    WHERE matricula_id = ?
    ORDER BY data_vencimento ASC
");
$stmtPagamentos->execute([$matricula['id']]);
$pagamentos = $stmtPagamentos->fetchAll();

if (empty($pagamentos)) {
    echo "❌ NENHUM PAGAMENTO ENCONTRADO!\n";
} else {
    foreach ($pagamentos as $p) {
        printf("[ID %d] R$ %s | Vencimento: %s | Status: %s\n",
            $p['id'], 
            number_format($p['valor'], 2, ',', '.'),
            $p['data_vencimento'],
            $p['status']
        );
    }
    $total = array_sum(array_column($pagamentos, 'valor'));
    printf("\n✅ Total: R$ %s\n", number_format($total, 2, ',', '.'));
    printf("✅ Quantidade: %d pagamento(s)\n", count($pagamentos));
}

echo "\n=== RESPOSTA DO API ===\n";
echo json_encode([
    'message' => 'Matrícula realizada com sucesso',
    'matricula' => $matricula,
    'pagamentos' => $pagamentos ?? [],
    'total' => (float) array_sum(array_column($pagamentos ?? [], 'valor'))
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>
