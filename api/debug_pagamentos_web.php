<?php
/**
 * Script para visualizar pagamentos_plano - VIA HTTP (web browser)
 * 
 * Acesso:
 *   GET /api/debug_pagamentos_web.php
 *   GET /api/debug_pagamentos_web.php?matricula=5
 *   GET /api/debug_pagamentos_web.php?status=pago
 */

// ============================================
// CONFIGURAÇÕES
// ============================================
$DB_HOST = 'srv1314.hstgr.io';
$DB_USER = 'u304177849_api';
$DB_PASS = '+DEEJ&7t';
$DB_NAME = 'u304177849_api';
$DB_PORT = 3306;

// ============================================
// CONEXÃO
// ============================================
try {
    $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, $DB_PORT);
    
    if ($conn->connect_error) {
        die("❌ Erro de conexão: " . $conn->connect_error);
    }
    
    $conn->set_charset("utf8mb4");
    
} catch (Exception $e) {
    die("❌ Erro: " . $e->getMessage());
}

// ============================================
// PROCESSAMENTO DOS PARÂMETROS
// ============================================
$limit = 30;
$whereClause = "1=1";
$titulo = "ÚLTIMOS PAGAMENTOS";

if (!empty($_GET['matricula'])) {
    $matriculaId = (int)$_GET['matricula'];
    $whereClause = "pp.matricula_id = {$matriculaId}";
    $titulo = "PAGAMENTOS DA MATRÍCULA #{$matriculaId}";
} elseif (!empty($_GET['status'])) {
    $status = strtolower(trim($_GET['status']));
    $whereClause = "sp.codigo = '{$status}'";
    $titulo = "PAGAMENTOS COM STATUS: {$status}";
}

if (!empty($_GET['limit'])) {
    $limit = (int)$_GET['limit'];
}

// ============================================
// HTML COM CSS
// ============================================
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug - Pagamentos Plano</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: #f5f5f5;
            padding: 20px;
            color: #333;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .header p {
            opacity: 0.9;
            font-size: 14px;
        }
        
        .filters {
            background: #f9f9f9;
            padding: 20px 30px;
            border-bottom: 1px solid #eee;
        }
        
        .filters form {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .filters input,
        .filters select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .filters button {
            padding: 8px 16px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
        }
        
        .filters button:hover {
            background: #5568d3;
        }
        
        .content {
            padding: 30px;
        }
        
        .section {
            margin-bottom: 40px;
        }
        
        .section-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
            color: #667eea;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }
        
        .payment-card {
            background: #f9f9f9;
            border-left: 4px solid #667eea;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 4px;
        }
        
        .payment-card:hover {
            background: #f0f0f0;
        }
        
        .payment-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            font-weight: 600;
        }
        
        .payment-header .id {
            color: #667eea;
        }
        
        .payment-header .status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .payment-header .status.pago {
            background: #d4edda;
            color: #155724;
        }
        
        .payment-header .status.pendente {
            background: #fff3cd;
            color: #856404;
        }
        
        .payment-header .status.cancelado {
            background: #f8d7da;
            color: #721c24;
        }
        
        .payment-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            font-size: 13px;
        }
        
        .detail-item {
            display: flex;
            flex-direction: column;
        }
        
        .detail-item strong {
            color: #667eea;
            margin-bottom: 4px;
        }
        
        .detail-item span {
            color: #555;
        }
        
        .summary-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .summary-table th {
            background: #667eea;
            color: white;
            padding: 12px;
            text-align: left;
            font-weight: 600;
        }
        
        .summary-table td {
            padding: 12px;
            border-bottom: 1px solid #eee;
        }
        
        .summary-table tr:hover {
            background: #f5f5f5;
        }
        
        .info-box {
            background: #e7f3ff;
            border-left: 4px solid #2196F3;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .error-box {
            background: #ffebee;
            border-left: 4px solid #f44336;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔍 Debug - Pagamentos Plano</h1>
            <p>Visualizar registros da tabela pagamentos_plano em tempo real</p>
        </div>
        
        <div class="filters">
            <form method="GET">
                <input type="text" name="matricula" placeholder="Matrícula ID" value="<?= isset($_GET['matricula']) ? htmlspecialchars($_GET['matricula']) : '' ?>">
                <select name="status">
                    <option value="">-- Todos os status --</option>
                    <option value="pago" <?= ($_GET['status'] ?? '') === 'pago' ? 'selected' : '' ?>>Pago</option>
                    <option value="pendente" <?= ($_GET['status'] ?? '') === 'pendente' ? 'selected' : '' ?>>Pendente</option>
                    <option value="cancelado" <?= ($_GET['status'] ?? '') === 'cancelado' ? 'selected' : '' ?>>Cancelado</option>
                </select>
                <input type="number" name="limit" placeholder="Limite" value="<?= $_GET['limit'] ?? '30' ?>" min="1" max="500">
                <button type="submit">🔍 Filtrar</button>
                <a href="?"><button type="button">🔄 Resetar</button></a>
            </form>
        </div>
        
        <div class="content">
            <?php
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
                echo '<div class="error-box">❌ Erro na query: ' . htmlspecialchars($conn->error) . '</div>';
            } else {
                $pagamentos = [];
                while ($row = $result->fetch_assoc()) {
                    $pagamentos[] = $row;
                }
                
                if (empty($pagamentos)) {
                    echo '<div class="error-box">❌ Nenhum registro encontrado</div>';
                } else {
                    echo '<div class="info-box">✅ Total de registros: ' . count($pagamentos) . '</div>';
                    echo '<div class="section">';
                    echo '<h2 class="section-title">' . htmlspecialchars($titulo) . '</h2>';
                    
                    foreach ($pagamentos as $pag) {
                        $statusClass = strtolower($pag['status']);
                        echo '<div class="payment-card">';
                        echo '<div class="payment-header">';
                        echo '<span class="id">#' . $pag['id'] . ' | Matrícula: #' . $pag['matricula_id'] . '</span>';
                        echo '<span class="status ' . $statusClass . '">' . $pag['status'] . '</span>';
                        echo '</div>';
                        echo '<div class="payment-details">';
                        echo '<div class="detail-item"><strong>Valor</strong><span>R$ ' . number_format($pag['valor'], 2, ',', '.') . '</span></div>';
                        echo '<div class="detail-item"><strong>Vencimento</strong><span>' . $pag['data_vencimento'] . '</span></div>';
                        echo '<div class="detail-item"><strong>Pagamento</strong><span>' . ($pag['data_pagamento'] ?? '❌ Não pago') . '</span></div>';
                        echo '<div class="detail-item"><strong>Criado</strong><span>' . $pag['created_at'] . '</span></div>';
                        echo '<div class="detail-item"><strong>Atualizado</strong><span>' . $pag['updated_at'] . '</span></div>';
                        if (!empty($pag['observacoes'])) {
                            echo '<div class="detail-item"><strong>Observações</strong><span>' . htmlspecialchars(substr($pag['observacoes'], 0, 100)) . '...</span></div>';
                        }
                        echo '</div>';
                        echo '</div>';
                    }
                    echo '</div>';
                }
            }
            
            // ============================================
            // SUMÁRIO POR STATUS
            // ============================================
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
                echo '<div class="section">';
                echo '<h2 class="section-title">📊 Sumário por Status</h2>';
                echo '<table class="summary-table">';
                echo '<thead><tr><th>Status</th><th>Total</th><th>Valor Total</th></tr></thead>';
                echo '<tbody>';
                
                while ($row = $resultSumario->fetch_assoc()) {
                    $valorTotal = number_format($row['valor_total'], 2, ',', '.');
                    echo '<tr>';
                    echo '<td><span class="status ' . strtolower($row['status']) . '">' . $row['status'] . '</span></td>';
                    echo '<td>' . $row['total'] . '</td>';
                    echo '<td>R$ ' . $valorTotal . '</td>';
                    echo '</tr>';
                }
                
                echo '</tbody>';
                echo '</table>';
                echo '</div>';
            }
            
            $conn->close();
            ?>
        </div>
    </div>
</body>
</html>
