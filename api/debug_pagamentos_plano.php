<?php
/**
 * Script para visualizar registros da tabela pagamentos_plano
 * 
 * Uso:
 *   php debug_pagamentos_plano.php              # Últimos 20 registros
 *   php debug_pagamentos_plano.php 10           # Últimos 10 registros
 *   php debug_pagamentos_plano.php matricula 5  # Pagamentos da matrícula ID 5
 *   php debug_pagamentos_plano.php status pago  # Apenas pagamentos "pago"
 */

require_once __DIR__ . '/config/database.php';

try {
    $db = require __DIR__ . '/config/database.php';
    
    $limit = 20;
    $whereClause = "1=1";
    
    if (!empty($argv[1])) {
        if ($argv[1] === 'matricula' && !empty($argv[2])) {
            $matriculaId = (int)$argv[2];
            $whereClause = "pp.matricula_id = {$matriculaId}";
            echo "🔍 Pagamentos da matrícula ID: {$matriculaId}\n";
        } elseif ($argv[1] === 'status' && !empty($argv[2])) {
            $status = strtolower(trim($argv[2]));
            $whereClause = "sp.codigo = '{$status}'";
            echo "🔍 Pagamentos com status: {$status}\n";
        } elseif (is_numeric($argv[1])) {
            $limit = (int)$argv[1];
        }
    }
    
    echo "\n═══════════════════════════════════════════════════════════════════════\n";
    echo "TABELA: pagamentos_plano\n";
    echo "═══════════════════════════════════════════════════════════════════════\n\n";
    
    $sql = "
        SELECT 
            pp.id,
            pp.tenant_id,
            pp.matricula_id,
            pp.aluno_id,
            pp.valor,
            pp.data_vencimento,
            pp.data_pagamento,
            sp.codigo as status,
            fp.nome as forma_pagamento,
            pp.observacoes,
            pp.created_at,
            pp.updated_at
        FROM pagamentos_plano pp
        INNER JOIN status_pagamento sp ON sp.id = pp.status_pagamento_id
        LEFT JOIN formas_pagamento fp ON fp.id = pp.forma_pagamento_id
        WHERE {$whereClause}
        ORDER BY pp.created_at DESC
        LIMIT {$limit}
    ";
    
    $stmt = $db->query($sql);
    $pagamentos = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    
    if (empty($pagamentos)) {
        echo "❌ Nenhum registro encontrado\n\n";
        exit(0);
    }
    
    echo "Total de registros: " . count($pagamentos) . "\n\n";
    
    // Exibir em formato tabular
    foreach ($pagamentos as $idx => $pag) {
        echo "─────────────────────────────────────────────────────────────────────\n";
        echo "ID: #{$pag['id']} | Matrícula: #{$pag['matricula_id']} | Aluno: #{$pag['aluno_id']}\n";
        echo "─────────────────────────────────────────────────────────────────────\n";
        echo "  Status: {$pag['status']} (ID: {$pag['id']})\n";
        echo "  Valor: R$ " . number_format($pag['valor'], 2, ',', '.') . "\n";
        echo "  Vencimento: {$pag['data_vencimento']}\n";
        echo "  Pagamento: " . ($pag['data_pagamento'] ? $pag['data_pagamento'] : 'NÃO PAGO') . "\n";
        echo "  Forma: " . ($pag['forma_pagamento'] ?? 'N/A') . "\n";
        echo "  Criado: {$pag['created_at']}\n";
        echo "  Atualizado: {$pag['updated_at']}\n";
        if ($pag['observacoes']) {
            echo "  Obs: {$pag['observacoes']}\n";
        }
        echo "\n";
    }
    
    // Sumário por status
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
    
    $stmtSumario = $db->query($sqlSumario);
    $sumarios = $stmtSumario->fetchAll(\PDO::FETCH_ASSOC);
    
    foreach ($sumarios as $sum) {
        $valorTotal = number_format($sum['valor_total'], 2, ',', '.');
        echo "  {$sum['status']}: {$sum['total']} registros | Total: R$ {$valorTotal}\n";
    }
    
    echo "\n";
    
} catch (\Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    exit(1);
}
