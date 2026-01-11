<?php
// Script simples para verificar se os pagamentos existem

$host = getenv('DB_HOST') ?: 'appcheckin_mysql';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASSWORD') ?: 'root';
$db = getenv('DB_NAME') ?: 'appcheckin';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✅ Conectado ao banco de dados\n\n";
    
    // 1. Verificar matrícula #22
    echo "📋 Verificando matrícula #22:\n";
    $stmt = $pdo->prepare("SELECT id, usuario_id, plano_id, valor, status, data_inicio FROM matriculas WHERE id = 22");
    $stmt->execute();
    $mat = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($mat) {
        echo "✅ Matrícula encontrada:\n";
        foreach ($mat as $k => $v) {
            echo "   $k: $v\n";
        }
    } else {
        echo "❌ Matrícula #22 não encontrada!\n";
        exit(1);
    }
    
    // 2. Verificar pagamentos
    echo "\n💰 Verificando pagamentos para matrícula #22:\n";
    $stmt = $pdo->prepare("SELECT id, valor, data_vencimento, data_pagamento, status_pagamento_id FROM pagamentos_plano WHERE matricula_id = 22");
    $stmt->execute();
    $pagamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Total de pagamentos: " . count($pagamentos) . "\n";
    
    if (count($pagamentos) > 0) {
        echo "✅ Pagamentos encontrados:\n";
        foreach ($pagamentos as $pag) {
            echo "\n   ID: " . $pag['id'] . "\n";
            echo "   Valor: " . $pag['valor'] . "\n";
            echo "   Vencimento: " . $pag['data_vencimento'] . "\n";
            echo "   Pagamento: " . ($pag['data_pagamento'] ?? 'não pago') . "\n";
            echo "   Status ID: " . $pag['status_pagamento_id'] . "\n";
        }
    } else {
        echo "❌ NENHUM pagamento encontrado para matrícula #22!\n";
        echo "\nVerificando últimos pagamentos criados:\n";
        $stmt = $pdo->query("SELECT id, matricula_id, valor, created_at FROM pagamentos_plano ORDER BY created_at DESC LIMIT 5");
        $ultimos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($ultimos as $u) {
            echo "   ID: " . $u['id'] . " | Matrícula: " . $u['matricula_id'] . " | Valor: " . $u['valor'] . " | Criado: " . $u['created_at'] . "\n";
        }
    }
    
    // 3. Testar a query exata que o controller usa
    echo "\n🔍 Testando query exata do controller:\n";
    $stmt = $pdo->prepare("
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
    $stmt->execute([22]);
    $resultado = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Resultado: " . json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    exit(1);
}
?>
