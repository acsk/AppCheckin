<?php
require 'vendor/autoload.php';
require 'config/database.php';

echo "=== MATRÍCULA 208 e 283 ===\n";
$stmt = $pdo->query("SELECT m.*, sm.codigo as status_codigo, p.nome as plano_nome, p.modalidade_id, pc.meses
    FROM matriculas m
    INNER JOIN status_matricula sm ON sm.id = m.status_id
    INNER JOIN planos p ON p.id = m.plano_id
    LEFT JOIN plano_ciclos pc ON pc.id = m.plano_ciclo_id
    WHERE m.id IN (208, 283) ORDER BY m.id");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo "\n--- Matrícula #{$r['id']} ---\n";
    foreach (['aluno_id','plano_nome','plano_id','plano_ciclo_id','meses','tipo_cobranca','data_matricula','data_inicio','data_vencimento','proxima_data_vencimento','valor','status_codigo','criado_por','created_at','updated_at'] as $k) {
        echo "  $k: " . ($r[$k] ?? 'NULL') . "\n";
    }
}

echo "\n=== PAGAMENTOS PLANO (208 e 283) ===\n";
$stmt = $pdo->query("SELECT pp.*, sp.nome as status_nome
    FROM pagamentos_plano pp
    LEFT JOIN status_pagamento sp ON sp.id = pp.status_pagamento_id
    WHERE pp.matricula_id IN (208, 283) ORDER BY pp.matricula_id, pp.data_vencimento");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo "  Pag #{$r['id']} | mat={$r['matricula_id']} | venc={$r['data_vencimento']} | pag=" . ($r['data_pagamento'] ?? 'NULL') . " | status={$r['status_nome']} | forma=" . ($r['forma_pagamento_id'] ?? 'NULL') . " | tipo_baixa=" . ($r['tipo_baixa_id'] ?? 'NULL') . "\n";
    if (!empty($r['observacoes'])) echo "    obs: " . substr($r['observacoes'], 0, 120) . "\n";
}

echo "\n=== ASSINATURAS (mat 208 e 283) ===\n";
$stmt = $pdo->query("SELECT a.*, ast.codigo as status_codigo
    FROM assinaturas a LEFT JOIN assinatura_status ast ON ast.id = a.status_id
    WHERE a.matricula_id IN (208, 283) ORDER BY a.id");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo "  Ass #{$r['id']} | mat={$r['matricula_id']} | tipo={$r['tipo_cobranca']} | ext_ref={$r['external_reference']} | status={$r['status_codigo']} | gateway={$r['status_gateway']} | inicio={$r['data_inicio']} | fim=" . ($r['data_fim'] ?? 'NULL') . " | pref_id=" . ($r['gateway_preference_id'] ?? 'NULL') . "\n";
}

echo "\n=== ALUNO - TODAS MATRÍCULAS ===\n";
$stmt = $pdo->query("SELECT m.id, m.plano_id, p.nome as plano, m.data_inicio, m.data_vencimento, m.proxima_data_vencimento, sm.codigo as status, m.created_at
    FROM matriculas m
    INNER JOIN status_matricula sm ON sm.id = m.status_id
    INNER JOIN planos p ON p.id = m.plano_id
    WHERE m.aluno_id = (SELECT aluno_id FROM matriculas WHERE id = 208)
    ORDER BY m.id");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo "  Mat #{$r['id']} | {$r['plano']} | inicio={$r['data_inicio']} | venc={$r['data_vencimento']} | prox=" . ($r['proxima_data_vencimento'] ?? 'NULL') . " | status={$r['status']} | criada={$r['created_at']}\n";
}

echo "\n=== PAGAMENTOS MP ===\n";
$stmt = $pdo->query("SELECT pm.* FROM pagamentos_mercadopago pm WHERE pm.matricula_id IN (208, 283) ORDER BY pm.id");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo "  MP #{$r['id']} | mat={$r['matricula_id']} | payment_id={$r['payment_id']} | ext_ref=" . ($r['external_reference'] ?? 'NULL') . " | status={$r['status']} | amount={$r['transaction_amount']} | approved=" . ($r['date_approved'] ?? 'NULL') . "\n";
}
