<?php
// Teste rápido sem docker
$matriculaId = 22;

// Simular resposta esperada
$pagamentos = [
    [
        'id' => 1,
        'valor' => '110.00',
        'data_vencimento' => '2026-01-11',
        'data_pagamento' => null,
        'status_pagamento_id' => 1,
        'status' => 'pendente',
        'observacoes' => 'Primeiro pagamento da matrícula'
    ]
];

$total = (float) array_sum(array_column($pagamentos, 'valor'));

echo "=== RESPOSTA ESPERADA ===\n\n";
$resposta = [
    'message' => 'Matrícula realizada com sucesso',
    'pagamentos' => $pagamentos,
    'total' => $total,
    'pagamento_criado' => true
];

echo json_encode($resposta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
echo "\n\n✅ Pagamentos devem vir preenchidos!\n";
echo "✅ Total deve ser " . $total . "\n";
?>
