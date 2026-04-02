<?php
$db = require __DIR__ . '/config/database.php';

// Ver relação assinaturas x matriculas
$rows = $db->query("
    SELECT a.id as assinatura_id, a.matricula_id, a.data_inicio as ass_inicio, 
           a.proxima_cobranca, a.dia_cobranca, a.status_id as ass_status,
           m.data_inicio as mat_inicio, m.data_vencimento as mat_venc, m.proxima_data_vencimento as mat_prox
    FROM assinaturas a
    INNER JOIN matriculas m ON m.id = a.matricula_id
    ORDER BY a.id DESC
    LIMIT 20
")->fetchAll(PDO::FETCH_ASSOC);

echo "=== ASSINATURAS x MATRÍCULAS ===\n";
foreach ($rows as $r) {
    $diverge = ($r['proxima_cobranca'] && $r['proxima_cobranca'] !== $r['mat_prox']) ? ' *** DIVERGE ***' : '';
    echo "Ass #{$r['assinatura_id']} mat={$r['matricula_id']} | ass_inicio={$r['ass_inicio']} prox_cob={$r['proxima_cobranca']} dia_cob={$r['dia_cobranca']} ass_status={$r['ass_status']} | mat_inicio={$r['mat_inicio']} mat_venc={$r['mat_venc']} mat_prox={$r['mat_prox']}{$diverge}\n";
}

// Ver tipos_baixa
echo "\n=== TIPOS BAIXA ===\n";
$tipos = $db->query("SELECT * FROM tipos_baixa")->fetchAll(PDO::FETCH_ASSOC);
foreach ($tipos as $t) {
    echo "id={$t['id']} | nome={$t['nome']}\n";
}

// Contar matrículas com baixa por integração
echo "\n=== MATRÍCULAS COM BAIXA POR INTEGRAÇÃO ===\n";
$integ = $db->query("
    SELECT DISTINCT pp.matricula_id, pp.tipo_baixa_id, tb.nome as tipo_baixa
    FROM pagamentos_plano pp
    INNER JOIN tipos_baixa tb ON tb.id = pp.tipo_baixa_id
    WHERE pp.tipo_baixa_id IS NOT NULL AND pp.tipo_baixa_id != 1
    ORDER BY pp.matricula_id
")->fetchAll(PDO::FETCH_ASSOC);
foreach ($integ as $i) {
    echo "mat={$i['matricula_id']} | tipo_baixa={$i['tipo_baixa']} (id={$i['tipo_baixa_id']})\n";
}
