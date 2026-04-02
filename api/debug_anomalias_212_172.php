<?php
require 'vendor/autoload.php';
require 'config/database.php';

foreach ([212, 172] as $matId) {
    echo "\n" . str_repeat('=', 70) . "\n";
    echo "=== MATRÍCULA #{$matId} ===\n";
    echo str_repeat('=', 70) . "\n";

    // Dados da matrícula
    $stmt = $pdo->query("
        SELECT m.id, a.nome as aluno, p.nome as plano, m.tipo_cobranca,
               m.data_matricula, m.data_inicio, m.data_vencimento, 
               m.proxima_data_vencimento, sm.codigo as status,
               m.created_at, m.updated_at
        FROM matriculas m
        INNER JOIN status_matricula sm ON sm.id = m.status_id
        INNER JOIN alunos a ON a.id = m.aluno_id
        INNER JOIN planos p ON p.id = m.plano_id
        WHERE m.id = {$matId}
    ");
    $mat = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$mat) { echo "  NÃO ENCONTRADA\n"; continue; }

    echo "\n  Aluno: {$mat['aluno']}\n";
    echo "  Plano: {$mat['plano']}\n";
    echo "  Tipo cobrança: {$mat['tipo_cobranca']}\n";
    echo "  Status: {$mat['status']}\n";
    echo "  data_matricula: {$mat['data_matricula']}\n";
    echo "  data_inicio: {$mat['data_inicio']}\n";
    echo "  data_vencimento: {$mat['data_vencimento']}\n";
    echo "  proxima_data_vencimento: " . ($mat['proxima_data_vencimento'] ?? 'NULL') . "\n";
    echo "  created_at: {$mat['created_at']}\n";
    echo "  updated_at: {$mat['updated_at']}\n";

    // Vencimento efetivo e dias
    $vencEfetivo = $mat['proxima_data_vencimento'] ?? $mat['data_vencimento'];
    $dias = (strtotime(date('Y-m-d')) - strtotime($vencEfetivo)) / 86400;
    echo "\n  Vencimento efetivo: {$vencEfetivo} ({$dias} dias atrás)\n";

    // Assinaturas
    $stmt = $pdo->query("
        SELECT a.id, a.tipo_cobranca, ast.codigo as status, a.status_gateway,
               a.data_inicio, a.data_fim, a.proxima_cobranca
        FROM assinaturas a
        LEFT JOIN assinatura_status ast ON ast.id = a.status_id
        WHERE a.matricula_id = {$matId}
    ");
    $assinaturas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "\n  ASSINATURAS (" . count($assinaturas) . "):\n";
    foreach ($assinaturas as $ass) {
        echo "    Ass #{$ass['id']} | tipo={$ass['tipo_cobranca']} | status={$ass['status']} | gw={$ass['status_gateway']} | inicio={$ass['data_inicio']} | fim=" . ($ass['data_fim'] ?? 'NULL') . " | prox=" . ($ass['proxima_cobranca'] ?? 'NULL') . "\n";
    }

    // Parcelas
    $stmt = $pdo->query("
        SELECT pp.id, pp.data_vencimento, pp.data_pagamento, sp.nome as status,
               pp.status_pagamento_id, pp.valor, pp.tipo_baixa_id,
               SUBSTRING(pp.observacoes, 1, 60) as obs
        FROM pagamentos_plano pp
        LEFT JOIN status_pagamento sp ON sp.id = pp.status_pagamento_id
        WHERE pp.matricula_id = {$matId}
        ORDER BY pp.data_vencimento DESC
        LIMIT 10
    ");
    $parcelas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "\n  ÚLTIMAS PARCELAS (" . count($parcelas) . "):\n";
    foreach ($parcelas as $p) {
        echo "    Pag #{$p['id']} | venc={$p['data_vencimento']} | pag=" . ($p['data_pagamento'] ?? '-') . " | {$p['status']} (id={$p['status_pagamento_id']}) | R\${$p['valor']}";
        if ($p['obs']) echo " | {$p['obs']}";
        echo "\n";
    }

    // Resumo parcelas
    $stmt = $pdo->query("
        SELECT sp.nome, COUNT(*) as total
        FROM pagamentos_plano pp
        LEFT JOIN status_pagamento sp ON sp.id = pp.status_pagamento_id
        WHERE pp.matricula_id = {$matId}
        GROUP BY pp.status_pagamento_id, sp.nome
    ");
    echo "\n  RESUMO PARCELAS:\n";
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        echo "    {$r['nome']}: {$r['total']}\n";
    }

    // Parcelas pendentes futuras/passadas
    $stmt = $pdo->query("
        SELECT 
            SUM(CASE WHEN data_vencimento >= CURDATE() THEN 1 ELSE 0 END) as pendentes_futuras,
            SUM(CASE WHEN data_vencimento < CURDATE() THEN 1 ELSE 0 END) as pendentes_passadas
        FROM pagamentos_plano
        WHERE matricula_id = {$matId} AND status_pagamento_id IN (1, 3)
    ");
    $pend = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "\n  Pendentes futuras: " . ($pend['pendentes_futuras'] ?? 0) . "\n";
    echo "  Pendentes em atraso: " . ($pend['pendentes_passadas'] ?? 0) . "\n";

    // Diagnóstico
    echo "\n  --- DIAGNÓSTICO ---\n";
    if ($mat['tipo_cobranca'] === 'diaria' || $mat['tipo_cobranca'] === 'avulso') {
        echo "  Tipo '{$mat['tipo_cobranca']}': pode não ter parcelas recorrentes.\n";
        echo "  Verificar se data_vencimento deveria ser atualizada manualmente.\n";
    }
    if (!$mat['proxima_data_vencimento']) {
        echo "  ⚠️ proxima_data_vencimento NULL\n";
    }
    if ($dias > 5 && $mat['status'] === 'ativa') {
        echo "  ⚠️ Ativa com {$dias} dias de vencimento - o job deveria ter cancelado.\n";
    }
}
echo "\n";
