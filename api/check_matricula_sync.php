<?php
$db = require __DIR__ . '/config/database.php';

// Buscar parcelas recentes com datas divergentes da matrícula
$stmt = $db->query("
    SELECT pp.id, pp.matricula_id, pp.data_vencimento, pp.status_pagamento_id,
           m.data_inicio as mat_inicio, m.data_vencimento as mat_venc, m.proxima_data_vencimento as mat_prox
    FROM pagamentos_plano pp
    INNER JOIN matriculas m ON m.id = pp.matricula_id
    ORDER BY pp.id DESC
    LIMIT 10
");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "=== ÚLTIMAS 10 PARCELAS ===\n";
foreach ($rows as $r) {
    echo "Parcela #{$r['id']} | mat={$r['matricula_id']} | venc_parcela={$r['data_vencimento']} | status={$r['status_pagamento_id']} | mat_inicio={$r['mat_inicio']} | mat_acesso_ate={$r['mat_venc']} | mat_prox={$r['mat_prox']}\n";
}
