<?php
require_once __DIR__ . '/vendor/autoload.php';

// Conectar ao banco
$db = require __DIR__ . '/config/database.php';

echo "\n=== DIAGNÓSTICO DE PAGAMENTOS ===\n\n";

// 1. Verificar a matrícula #22
echo "📋 Matrícula #22:\n";
$stmt = $db->prepare("
    SELECT id, usuario_id, plano_id, valor, data_inicio, data_vencimento, status, created_at 
    FROM matriculas 
    WHERE id = 22
");
$stmt->execute();
$matricula = $stmt->fetch();

if ($matricula) {
    echo "   ✅ Encontrada\n";
    echo "   ID: " . $matricula['id'] . "\n";
    echo "   Usuário: " . $matricula['usuario_id'] . "\n";
    echo "   Plano: " . $matricula['plano_id'] . "\n";
    echo "   Valor: " . $matricula['valor'] . "\n";
    echo "   Data Início: " . $matricula['data_inicio'] . "\n";
    echo "   Status: " . $matricula['status'] . "\n";
    echo "   Criado em: " . $matricula['created_at'] . "\n";
} else {
    echo "   ❌ NÃO encontrada\n";
    exit;
}

// 2. Verificar pagamentos dessa matrícula
echo "\n💰 Pagamentos da matrícula #22:\n";
$stmt = $db->prepare("
    SELECT id, valor, data_vencimento, data_pagamento, status_pagamento_id, created_at
    FROM pagamentos_plano 
    WHERE matricula_id = 22
");
$stmt->execute();
$pagamentos = $stmt->fetchAll();

if (count($pagamentos) > 0) {
    echo "   ✅ Encontrados " . count($pagamentos) . " pagamento(s):\n";
    foreach ($pagamentos as $pag) {
        echo "   \n   ID: " . $pag['id'] . "\n";
        echo "   Valor: " . $pag['valor'] . " (tipo: " . gettype($pag['valor']) . ")\n";
        echo "   Data Vencimento: " . $pag['data_vencimento'] . "\n";
        echo "   Data Pagamento: " . ($pag['data_pagamento'] ?? 'null') . "\n";
        echo "   Status ID: " . $pag['status_pagamento_id'] . "\n";
        echo "   Criado em: " . $pag['created_at'] . "\n";
    }
} else {
    echo "   ❌ NENHUM pagamento encontrado!\n";
    echo "   Isso é o problema! O pagamento não foi criado.\n";
}

// 3. Verificar a query exata que o controller usa
echo "\n🔍 Testando query exata do controller:\n";
$stmt = $db->prepare("
    SELECT 
        id,
        CAST(valor AS DECIMAL(10,2)) as valor,
        data_vencimento,
        data_pagamento,
        status_pagamento_id,
        (SELECT nome FROM status_pagamento WHERE id = status_pagamento_id) as status,
        observacoes
    FROM pagamentos_plano
    WHERE matricula_id = 22
    ORDER BY data_vencimento ASC
");
$stmt->execute();
$pagamentosController = $stmt->fetchAll(\PDO::FETCH_ASSOC);

if (count($pagamentosController) > 0) {
    echo "   ✅ Query retornou " . count($pagamentosController) . " registro(s):\n";
    echo json_encode($pagamentosController, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
    $total = (float) array_sum(array_column($pagamentosController, 'valor'));
    echo "\n   Total calculado: " . $total . "\n";
} else {
    echo "   ❌ Query não retornou nada\n";
}

// 4. Verificar status_pagamento
echo "\n📊 Status de pagamento disponíveis:\n";
$stmt = $db->prepare("SELECT id, nome, ativo FROM status_pagamento");
$stmt->execute();
$status = $stmt->fetchAll();
foreach ($status as $s) {
    echo "   " . $s['id'] . ": " . $s['nome'] . " (ativo: " . $s['ativo'] . ")\n";
}

echo "\n";
?>
