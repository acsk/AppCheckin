<?php
/**
 * Script para visualizar pagamentos_plano - SEM DEPENDÊNCIA DE COMPOSER
 * 
 * Funciona com PHP 7.4+
 * 
 * Uso:
 *   php debug_pagamentos_simples.php              # Últimos pagamentos
 *   php debug_pagamentos_simples.php matricula 5  # Pagamentos da matrícula 5
 *   php debug_pagamentos_simples.php status pago  # Apenas "pago"
 */

// ============================================
// CONFIGURAÇÕES - EDITE COM SEUS DADOS
// ============================================
$DB_HOST = 'srv1314.hstgr.io';
$DB_USER = 'u304177849_api';
$DB_PASS = '+DEEJ&7t';
$DB_NAME = 'u304177849_api';
$DB_PORT = 3306;

// ============================================
// CONEXÃO AO BANCO
// ============================================
try {
    $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, $DB_PORT);
    
    if ($conn->connect_error) {
        die("❌ Erro de conexão: " . $conn->connect_error);
    }
    
    $conn->set_charset("utf8mb4");
    echo "✅ Conectado ao banco de dados\n\n";
    
} catch (Exception $e) {
    die("❌ Erro: " . $e->getMessage());
}

// ============================================
// PROCESSAMENTO DOS ARGUMENTOS
// ============================================
$limit = 30;
$whereClause = "1=1";
$titulo = "ÚLTIMOS PAGAMENTOS";

if (!empty($GLOBALS['argv'][1])) {
    if ($GLOBALS['argv'][1] === 'matricula' && !empty($GLOBALS['argv'][2])) {
        $matriculaId = (int)$GLOBALS['argv'][2];
        $whereClause = "pp.matricula_id = {$matriculaId}";
        $titulo = "PAGAMENTOS DA MATRÍCULA #{$matriculaId}";
    } elseif ($GLOBALS['argv'][1] === 'status' && !empty($GLOBALS['argv'][2])) {
        $status = strtolower(trim($GLOBALS['argv'][2]));
        $whereClause = "sp.codigo = '{$status}'";
        $titulo = "PAGAMENTOS COM STATUS: {$status}";
    } elseif (is_numeric($GLOBALS['argv'][1])) {
        $limit = (int)$GLOBALS['argv'][1];
    }
}

// ============================================
// CABEÇALHO
// ============================================
echo "\n═══════════════════════════════════════════════════════════════════════\n";
echo "TABELA: pagamentos_plano\n";
echo "{$titulo}\n";
echo "═══════════════════════════════════════════════════════════════════════\n\n";

// ============================================
// QUERY DOS PAGAMENTOS
// ============================================
$sql = "
    SELECT 
        pp.id,
        pp.tenant_id,
        pp.matricula_id,
        pp.aluno_id,
        pp.plano_id,
        pp.valor,
        pp.data_vencimento,
        pp.data_pagamento,
        sp.codigo as status,
        pp.observacoes,
        pp.created_at,
        pp.updated_at
    FROM pagamentos_plano pp
    INNER JOIN status_pagamento sp ON sp.id = pp.status_pagamento_id
    WHERE {$whereClause}
    ORDER BY pp.created_at DESC
    LIMIT {$limit}
";

$result = $conn->query($sql);

if (!$result) {
    die("❌ Erro na query: " . $conn->error);
}

$pagamentos = [];
while ($row = $result->fetch_assoc()) {
    $pagamentos[] = $row;
}

if (empty($pagamentos)) {
    echo "❌ Nenhum registro encontrado\n\n";
} else {
    echo "Total de registros: " . count($pagamentos) . "\n\n";
    
    // Exibir tabela
    foreach ($pagamentos as $pag) {
        echo "─────────────────────────────────────────────────────────────────────\n";
        echo "ID: #{$pag['id']} | Matrícula: #{$pag['matricula_id']} | Aluno: #{$pag['aluno_id']}\n";
        echo "─────────────────────────────────────────────────────────────────────\n";
        echo "  Status: {$pag['status']}\n";
        echo "  Valor: R$ " . number_format($pag['valor'], 2, ',', '.') . "\n";
        echo "  Vencimento: {$pag['data_vencimento']}\n";
        echo "  Pagamento: " . ($pag['data_pagamento'] ? $pag['data_pagamento'] : 'NÃO PAGO') . "\n";
        echo "  Criado: {$pag['created_at']}\n";
        echo "  Atualizado: {$pag['updated_at']}\n";
        if (!empty($pag['observacoes'])) {
            echo "  Obs: {$pag['observacoes']}\n";
        }
        echo "\n";
    }
}

// ============================================
// SUMÁRIO POR STATUS
// ============================================
echo "═══════════════════════════════════════════════════════════════════════\n";
echo "SUMÁRIO POR STATUS\n";
echo "═══════════════════════════════════════════════════════════════════════\n\n";

$sqlSumario = "
    SELECT 
        sp.codigo as status,
        COUNT(*) as total,
        SUM(pp.valor) as valor_total
    FROM pagamentos_plano pp
    INNER JOIN status_pagamento sp ON sp.id = pp.status_pagamento_id
    GROUP BY sp.codigo
    ORDER BY sp.codigo
";

$resultSumario = $conn->query($sqlSumario);

if ($resultSumario) {
    while ($row = $resultSumario->fetch_assoc()) {
        $valorTotal = number_format($row['valor_total'], 2, ',', '.');
        echo "  {$row['status']}: {$row['total']} registros | Total: R$ {$valorTotal}\n";
    }
}

echo "\n";

// ============================================
// ÚLTIMOS PAGAMENTOS ATUALIZADOS (webhook)
// ============================================
echo "═══════════════════════════════════════════════════════════════════════\n";
echo "ÚLTIMOS PAGAMENTOS ATUALIZADOS (últimas 24h)\n";
echo "═══════════════════════════════════════════════════════════════════════\n\n";

$sqlRecentes = "
    SELECT 
        pp.id,
        pp.matricula_id,
        pp.valor,
        pp.data_pagamento,
        sp.codigo as status,
        pp.observacoes,
        pp.updated_at
    FROM pagamentos_plano pp
    INNER JOIN status_pagamento sp ON sp.id = pp.status_pagamento_id
    WHERE pp.updated_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ORDER BY pp.updated_at DESC
    LIMIT 10
";

$resultRecentes = $conn->query($sqlRecentes);

if ($resultRecentes && $resultRecentes->num_rows > 0) {
    while ($row = $resultRecentes->fetch_assoc()) {
        echo "  Pagamento #{$row['id']} | Matrícula: #{$row['matricula_id']} | Status: {$row['status']}\n";
        echo "    Atualizado: {$row['updated_at']}\n";
        if ($row['data_pagamento']) {
            echo "    Pago em: {$row['data_pagamento']}\n";
        }
        echo "\n";
    }
} else {
    echo "❌ Nenhuma atualização nas últimas 24 horas\n\n";
}

$conn->close();
?>
