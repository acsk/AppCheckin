<?php
require 'vendor/autoload.php';
require 'config/database.php';

echo "=== MATRÍCULA 116 ===\n";
$stmt = $pdo->query("
    SELECT m.*, sm.codigo as status_codigo, sm.nome as status_nome, 
           p.nome as plano_nome, p.modalidade_id, pc.meses
    FROM matriculas m
    INNER JOIN status_matricula sm ON sm.id = m.status_id
    INNER JOIN planos p ON p.id = m.plano_id
    LEFT JOIN plano_ciclos pc ON pc.id = m.plano_ciclo_id
    WHERE m.id = 116
");
$r = $stmt->fetch(PDO::FETCH_ASSOC);
if ($r) {
    foreach (['aluno_id','plano_nome','plano_id','plano_ciclo_id','meses','tipo_cobranca','data_matricula','data_inicio','data_vencimento','proxima_data_vencimento','valor','status_codigo','status_nome','created_at','updated_at'] as $k) {
        echo "  $k: " . ($r[$k] ?? 'NULL') . "\n";
    }
}

echo "\n=== PAGAMENTOS PLANO ===\n";
$stmt = $pdo->query("
    SELECT pp.id, pp.data_vencimento, pp.data_pagamento, sp.nome as status, 
           pp.valor, pp.tipo_baixa_id, pp.status_pagamento_id,
           SUBSTRING(pp.observacoes, 1, 80) as obs
    FROM pagamentos_plano pp
    LEFT JOIN status_pagamento sp ON sp.id = pp.status_pagamento_id
    WHERE pp.matricula_id = 116 ORDER BY pp.data_vencimento
");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo "  Pag #{$r['id']} | venc={$r['data_vencimento']} | pag=" . ($r['data_pagamento'] ?? 'NULL') . " | status={$r['status']} (id={$r['status_pagamento_id']}) | valor={$r['valor']}\n";
}

echo "\n=== ASSINATURAS ===\n";
$stmt = $pdo->query("
    SELECT a.id, a.tipo_cobranca, a.external_reference, ast.codigo as status, 
           a.status_gateway, a.data_inicio, a.data_fim, a.proxima_cobranca
    FROM assinaturas a 
    LEFT JOIN assinatura_status ast ON ast.id = a.status_id
    WHERE a.matricula_id = 116
");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo "  Ass #{$r['id']} | tipo={$r['tipo_cobranca']} | status={$r['status']} | gw={$r['status_gateway']} | inicio={$r['data_inicio']} | fim=" . ($r['data_fim'] ?? 'NULL') . " | prox_cob=" . ($r['proxima_cobranca'] ?? 'NULL') . "\n";
}

echo "\n=== DIAGNÓSTICO ===\n";
// Contar parcelas por status
$stmt = $pdo->query("
    SELECT sp.nome, COUNT(*) as total
    FROM pagamentos_plano pp
    LEFT JOIN status_pagamento sp ON sp.id = pp.status_pagamento_id
    WHERE pp.matricula_id = 116
    GROUP BY pp.status_pagamento_id, sp.nome
");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo "  {$r['nome']}: {$r['total']}\n";
}

$stmt = $pdo->query("
    SELECT COUNT(*) as pendentes_atraso 
    FROM pagamentos_plano 
    WHERE matricula_id = 116 
    AND status_pagamento_id IN (1,3) 
    AND data_vencimento < CURDATE()
");
$r = $stmt->fetch(PDO::FETCH_ASSOC);
echo "  Parcelas pendentes em atraso: {$r['pendentes_atraso']}\n";

$stmt = $pdo->query("
    SELECT COUNT(*) as pendentes_futuro
    FROM pagamentos_plano 
    WHERE matricula_id = 116 
    AND status_pagamento_id IN (1,3) 
    AND data_vencimento >= CURDATE()
");
$r = $stmt->fetch(PDO::FETCH_ASSOC);
echo "  Parcelas pendentes futuras: {$r['pendentes_futuro']}\n";

// Verificar o que o job atualizarStatusMatriculasVencidas faria
echo "\n=== O QUE CAUSOU O CANCELAMENTO ===\n";
if ($r) {
    $mat = $pdo->query("SELECT proxima_data_vencimento, data_vencimento, status_id FROM matriculas WHERE id = 116")->fetch(PDO::FETCH_ASSOC);
    $proxVenc = $mat['proxima_data_vencimento'] ?? $mat['data_vencimento'] ?? 'NULL';
    $hoje = date('Y-m-d');
    echo "  proxima_data_vencimento: " . ($mat['proxima_data_vencimento'] ?? 'NULL') . "\n";
    echo "  data_vencimento: {$mat['data_vencimento']}\n";
    echo "  Hoje: {$hoje}\n";
    if ($proxVenc !== 'NULL') {
        $diff = (strtotime($hoje) - strtotime($proxVenc)) / 86400;
        echo "  Dias desde vencimento: " . round($diff) . "\n";
        if ($diff >= 5) echo "  => Job marcaria como CANCELADA (>=5 dias)\n";
        elseif ($diff >= 1) echo "  => Job marcaria como VENCIDA (1-4 dias)\n";
        else echo "  => Ainda dentro do prazo\n";
    }
}

echo "\n=== TODAS MATRÍCULAS DO ALUNO ===\n";
$stmt = $pdo->query("
    SELECT m.id, p.nome as plano, m.data_inicio, m.data_vencimento, 
           m.proxima_data_vencimento, sm.codigo as status, m.created_at
    FROM matriculas m
    INNER JOIN status_matricula sm ON sm.id = m.status_id
    INNER JOIN planos p ON p.id = m.plano_id
    WHERE m.aluno_id = (SELECT aluno_id FROM matriculas WHERE id = 116)
    ORDER BY m.id
");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo "  Mat #{$r['id']} | {$r['plano']} | inicio={$r['data_inicio']} | venc={$r['data_vencimento']} | prox=" . ($r['proxima_data_vencimento'] ?? 'NULL') . " | status={$r['status']} | criada={$r['created_at']}\n";
}
