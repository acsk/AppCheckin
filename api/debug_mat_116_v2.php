<?php
/**
 * Debug Matrícula 116 - Cancelada mas com parcela futura 17/04/2026
 * Uso: php debug_mat_116_v2.php [--fix]
 */
require 'vendor/autoload.php';
require 'config/database.php';

$fix = in_array('--fix', $argv);

echo "=== DEBUG MATRÍCULA 116 ===\n";
echo "Data atual: " . date('Y-m-d') . "\n";
echo "Modo: " . ($fix ? "FIX" : "SOMENTE LEITURA (use --fix para corrigir)") . "\n";

// 1. Estado da matrícula
echo "\n--- MATRÍCULA ---\n";
$stmt = $pdo->query("
    SELECT m.id, a.nome as aluno, p.nome as plano, m.tipo_cobranca,
           m.data_matricula, m.data_inicio, m.data_vencimento,
           m.proxima_data_vencimento, sm.codigo as status,
           m.updated_at
    FROM matriculas m
    INNER JOIN status_matricula sm ON sm.id = m.status_id
    INNER JOIN alunos a ON a.id = m.aluno_id
    INNER JOIN planos p ON p.id = m.plano_id
    WHERE m.id = 116
");
$mat = $stmt->fetch(PDO::FETCH_ASSOC);
foreach ($mat as $k => $v) echo "  $k: " . ($v ?? 'NULL') . "\n";

$vencEfetivo = $mat['proxima_data_vencimento'] ?? $mat['data_vencimento'];
$diasVenc = round((strtotime(date('Y-m-d')) - strtotime($vencEfetivo)) / 86400);
echo "\n  Vencimento efetivo: {$vencEfetivo} ({$diasVenc} dias " . ($diasVenc > 0 ? 'atrás' : 'restantes') . ")\n";

// 2. Assinaturas
echo "\n--- ASSINATURAS ---\n";
$stmt = $pdo->query("
    SELECT a.id, a.tipo_cobranca, ast.codigo as status, a.status_gateway,
           a.data_inicio, a.data_fim, a.proxima_cobranca
    FROM assinaturas a
    LEFT JOIN assinatura_status ast ON ast.id = a.status_id
    WHERE a.matricula_id = 116
");
$assinaturas = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (!$assinaturas) {
    echo "  Nenhuma\n";
} else {
    foreach ($assinaturas as $ass) {
        echo "  Ass #{$ass['id']} | tipo={$ass['tipo_cobranca']} | status={$ass['status']} | gw={$ass['status_gateway']}";
        echo " | inicio={$ass['data_inicio']} | fim=" . ($ass['data_fim'] ?? 'NULL') . "\n";
    }
}

// 3. Parcelas (todas)
echo "\n--- PARCELAS ---\n";
$stmt = $pdo->query("
    SELECT pp.id, pp.data_vencimento, pp.data_pagamento, sp.nome as status,
           pp.status_pagamento_id, pp.valor, pp.tipo_baixa_id
    FROM pagamentos_plano pp
    LEFT JOIN status_pagamento sp ON sp.id = pp.status_pagamento_id
    WHERE pp.matricula_id = 116
    ORDER BY pp.data_vencimento
");
$parcelas = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($parcelas as $p) {
    $marker = ($p['status_pagamento_id'] == 2) ? '✅' : (($p['data_vencimento'] >= date('Y-m-d')) ? '⏳' : '❌');
    echo "  {$marker} Pag #{$p['id']} | venc={$p['data_vencimento']} | pag=" . ($p['data_pagamento'] ?? '-') . " | {$p['status']} | R\${$p['valor']}\n";
}

// 4. Cálculos de referência
echo "\n--- CÁLCULOS ---\n";
$stmt = $pdo->query("SELECT MIN(data_vencimento) as v FROM pagamentos_plano WHERE matricula_id = 116 AND status_pagamento_id IN (1, 3)");
$proxPendente = $stmt->fetchColumn();
echo "  Próxima parcela pendente: " . ($proxPendente ?: 'nenhuma') . "\n";

$stmt = $pdo->query("SELECT MAX(data_vencimento) as v FROM pagamentos_plano WHERE matricula_id = 116 AND status_pagamento_id = 2");
$maxPago = $stmt->fetchColumn();
echo "  Último vencimento pago: " . ($maxPago ?: 'nenhum') . "\n";

$correto = $proxPendente ?: $maxPago;
echo "  proxima_data_vencimento correto: " . ($correto ?: '?') . "\n";
$diverge = ($correto && $correto !== $mat['proxima_data_vencimento']);
echo "  Diverge do atual? " . ($diverge ? "SIM ⚠️ ({$mat['proxima_data_vencimento']} → {$correto})" : "NÃO ✅") . "\n";

// 5. Simular o que o job faria
echo "\n--- SIMULAÇÃO DO JOB ---\n";

// Step 2: vencida por parcela (1-4 dias)
$stmt = $pdo->query("
    SELECT COUNT(*) FROM pagamentos_plano
    WHERE matricula_id = 116 AND status_pagamento_id IN (1, 3)
    AND data_vencimento < CURDATE() AND DATEDIFF(CURDATE(), data_vencimento) >= 1 AND DATEDIFF(CURDATE(), data_vencimento) < 5
");
echo "  Step 2 (vencida parcela 1-4d): " . ($stmt->fetchColumn() > 0 ? "SIM" : "não") . "\n";

// Step 3: cancelada por parcela (5+ dias)
$stmt = $pdo->query("
    SELECT COUNT(*) FROM pagamentos_plano
    WHERE matricula_id = 116 AND status_pagamento_id IN (1, 3)
    AND data_vencimento < CURDATE() AND DATEDIFF(CURDATE(), data_vencimento) >= 5
");
echo "  Step 3 (cancelada parcela 5+d): " . ($stmt->fetchColumn() > 0 ? "SIM ⚠️" : "não") . "\n";

// Step 3.1: vencida por data (1-4 dias)
$diasData = max(0, $diasVenc);
echo "  Step 3.1 (vencida data 1-4d): " . ($diasData >= 1 && $diasData < 5 ? "SIM" : "não") . " (dias={$diasData})\n";

// Step 3.2: cancelada por data (5+ dias)
echo "  Step 3.2 (cancelada data 5+d): " . ($diasData >= 5 ? "SIM ⚠️" : "não") . " (dias={$diasData})\n";

// Step 4: sync assinatura cancelada
$temAssCancelada = false;
foreach ($assinaturas as $ass) {
    if ($ass['status'] === 'cancelada') $temAssCancelada = true;
}
echo "  Step 4 (sync ass cancelada): " . ($temAssCancelada ? "SIM ⚠️" : "não") . "\n";

// Step 6.1: sync assinatura paga/approved → reativa
$temAssPaga = false;
foreach ($assinaturas as $ass) {
    if ($ass['status'] === 'paga' || $ass['status_gateway'] === 'approved') $temAssPaga = true;
}
echo "  Step 6.1 (sync ass paga→ativa): " . ($temAssPaga ? "SIM" : "não") . "\n";

// Step 7: reativar
$podReativar = ($correto && $correto >= date('Y-m-d'));
echo "  Step 7 (reativar): vencimento OK=" . ($podReativar ? "sim" : "não") . " | sem parcela atrasada=" . (!$stmt ? "sim" : "verificar") . "\n";

// 6. Diagnóstico
echo "\n--- DIAGNÓSTICO ---\n";
if ($mat['status'] === 'cancelada' && $correto && $correto >= date('Y-m-d')) {
    echo "  ⚠️ Matrícula CANCELADA mas próxima parcela ({$correto}) é FUTURA\n";
    
    if ($diverge) {
        echo "  CAUSA: proxima_data_vencimento ({$mat['proxima_data_vencimento']}) está desatualizado\n";
        echo "  → O job calculou vencimento pelo campo antigo e cancelou por data\n";
        echo "  → Deveria ser {$correto}\n";
    }
    if ($temAssCancelada) {
        echo "  CAUSA: Step 4 do job sincroniza matrícula com assinatura cancelada\n";
        echo "  → Mesmo que a parcela futura exista, a assinatura cancelada força cancelamento\n";
        echo "  → A aluna desistiu da assinatura mas paga manual - o step 4 não deveria aplicar\n";
    }
}

// 7. Fix
if ($fix) {
    echo "\n--- APLICANDO FIX ---\n";
    
    if ($correto && $correto !== $mat['proxima_data_vencimento']) {
        $stmt = $pdo->prepare("UPDATE matriculas SET proxima_data_vencimento = ?, data_vencimento = ?, updated_at = NOW() WHERE id = 116");
        $stmt->execute([$correto, $correto]);
        echo "  ✅ proxima_data_vencimento: {$mat['proxima_data_vencimento']} → {$correto}\n";
        echo "  ✅ data_vencimento: {$mat['data_vencimento']} → {$correto}\n";
    }
    
    if ($correto >= date('Y-m-d')) {
        $stmt = $pdo->prepare("UPDATE matriculas SET status_id = (SELECT id FROM status_matricula WHERE codigo = 'ativa' LIMIT 1), updated_at = NOW() WHERE id = 116");
        $stmt->execute();
        echo "  ✅ Status: cancelada → ativa\n";
    }
    
    // Verificar resultado
    $stmt = $pdo->query("SELECT sm.codigo as status, m.proxima_data_vencimento, m.data_vencimento FROM matriculas m INNER JOIN status_matricula sm ON sm.id = m.status_id WHERE m.id = 116");
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "\n  RESULTADO: status={$r['status']} | prox_venc={$r['proxima_data_vencimento']} | data_venc={$r['data_vencimento']}\n";
} else {
    echo "\n  Use --fix para corrigir.\n";
}
