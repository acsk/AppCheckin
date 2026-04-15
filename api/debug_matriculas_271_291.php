<?php
/**
 * Debug: matrícula #291 criada em vez de reaproveitar #271
 * Uso: php debug_matriculas_271_291.php
 */

$db = require __DIR__ . '/config/database.php';

echo "====== DEBUG MATRÍCULA — #271 vs #291 ======\n";
echo "Data BRT: " . date('Y-m-d H:i:s') . "\n\n";

// ─── 1. Dados das duas matrículas ────────────────────────────────────────────
$stmt = $db->prepare("
    SELECT m.id, m.aluno_id, m.plano_id, m.plano_ciclo_id, m.status_id,
           m.data_matricula, m.data_inicio, m.data_vencimento, m.proxima_data_vencimento,
           m.valor, m.motivo_id, m.matricula_anterior_id, m.criado_por, m.updated_at,
           sm.codigo AS status_codigo, sm.nome AS status_nome,
           p.nome AS plano_nome, p.modalidade_id,
           a.nome AS aluno_nome
    FROM matriculas m
    INNER JOIN status_matricula sm ON sm.id = m.status_id
    INNER JOIN planos p ON p.id = m.plano_id
    INNER JOIN alunos a ON a.id = m.aluno_id
    WHERE m.id IN (271, 291)
    ORDER BY m.id ASC
");
$stmt->execute();
$matriculas = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "1. DADOS DAS MATRÍCULAS\n";
echo str_repeat('-', 80) . "\n";
foreach ($matriculas as $m) {
    echo "  Matrícula #" . $m['id'] . "\n";
    echo "    Aluno            : {$m['aluno_nome']} (aluno_id={$m['aluno_id']})\n";
    echo "    Plano            : {$m['plano_nome']} (plano_id={$m['plano_id']}, modalidade={$m['modalidade_id']})\n";
    echo "    Status           : {$m['status_codigo']} (id={$m['status_id']})\n";
    echo "    data_matricula   : {$m['data_matricula']}\n";
    echo "    data_inicio      : {$m['data_inicio']}\n";
    echo "    data_vencimento  : {$m['data_vencimento']}\n";
    echo "    proxima_data_venc: {$m['proxima_data_vencimento']}\n";
    echo "    valor            : R\$ " . number_format($m['valor'], 2, ',', '.') . "\n";
    echo "    updated_at       : {$m['updated_at']}\n";
    echo "\n";
}

if (count($matriculas) < 2) {
    echo "  ❌ Alguma matrícula não encontrada!\n";
    exit(1);
}

$m271 = $matriculas[0];
$m291 = $matriculas[1];
$alunoId = $m271['aluno_id'];
$tenantId = null;

// Pegar tenant
$stmtTenant = $db->prepare("SELECT tenant_id FROM matriculas WHERE id = 271 LIMIT 1");
$stmtTenant->execute();
$tenantId = (int) $stmtTenant->fetchColumn();
echo "  tenant_id: $tenantId\n\n";

// ─── 2. Query de reaproveitamento (exatamente como está no código) ───────────
echo "2. QUERY DE REAPROVEITAMENTO (como o código executa)\n";
echo str_repeat('-', 80) . "\n";

$modalidadeId = $m271['modalidade_id'];

$stmt = $db->prepare("
    SELECT m.id, m.plano_id, m.status_id, m.data_vencimento, m.proxima_data_vencimento,
           sm.codigo as status_codigo
    FROM matriculas m
    INNER JOIN planos p ON p.id = m.plano_id
    INNER JOIN status_matricula sm ON sm.id = m.status_id
    WHERE m.aluno_id = ? AND m.tenant_id = ? AND p.modalidade_id = ?
    ORDER BY m.updated_at DESC, m.id DESC
    LIMIT 1
");
$stmt->execute([$alunoId, $tenantId, $modalidadeId]);
$encontrada = $stmt->fetch(PDO::FETCH_ASSOC);

if ($encontrada) {
    echo "  Matrícula encontrada pela query: #" . $encontrada['id'] . "\n";
    echo "    status_codigo    : {$encontrada['status_codigo']}\n";
    echo "    data_vencimento  : {$encontrada['data_vencimento']}\n";
    echo "    proxima_data_venc: {$encontrada['proxima_data_vencimento']}\n";

    $vencimento = $encontrada['proxima_data_vencimento'] ?? $encontrada['data_vencimento'];
    $hoje = date('Y-m-d');
    $vencidaPorData = $vencimento && $vencimento < $hoje;

    echo "    vencimento usado : $vencimento\n";
    echo "    hoje             : $hoje\n";
    echo "    vencidaPorData   : " . ($vencidaPorData ? 'SIM' : 'NÃO') . "\n";

    $statusCodigo = $encontrada['status_codigo'];

    // Lógica exata do controller
    $reutiliza = ($statusCodigo === 'vencida' || $vencidaPorData);
    echo "    reutilizandoMatricula: " . ($reutiliza ? '✅ SIM' : '❌ NÃO') . "\n";

    if (!$reutiliza) {
        echo "\n  ❌ ROOT CAUSE:\n";
        if ($statusCodigo === 'cancelada') {
            echo "     status='cancelada' — o código só reutiliza status='vencida' ou data vencida.\n";
            echo "     Matrícula #271 foi cancelada (não vencida), por isso criou a #291.\n";
        } elseif ($statusCodigo === 'ativa') {
            echo "     status='ativa' — matrícula ainda ativa, bloqueou a criação corretamente.\n";
        } else {
            echo "     status='$statusCodigo' — não satisfaz condição de reaproveitamento.\n";
        }
    }
} else {
    echo "  ❌ Query não retornou nenhuma matrícula!\n";
}

// ─── 3. Verificar se a #271 deveria ser reaproveitada ────────────────────────
echo "\n3. ANÁLISE — #271 deveria ser reaproveitada?\n";
echo str_repeat('-', 80) . "\n";

$venc271 = $m271['proxima_data_vencimento'] ?? $m271['data_vencimento'];
$hoje = date('Y-m-d');
echo "  status_271       : {$m271['status_codigo']}\n";
echo "  vencimento_271   : $venc271\n";
echo "  data criação #291: {$m291['data_matricula']}\n";
echo "  vencimento < hoje: " . ($venc271 < $hoje ? 'SIM' : 'NÃO') . "\n";

if ($m271['status_codigo'] === 'cancelada') {
    echo "\n  ℹ️  A matrícula foi manualmente cancelada antes do vencimento.\n";
    echo "     Na data de criação da #291 ({$m291['data_matricula']}),\n";
    echo "     a #271 já estava 'cancelada' — o código não a reaproveita.\n";
}

// ─── 4. Pagamentos das duas matrículas ───────────────────────────────────────
echo "\n4. PAGAMENTOS\n";
echo str_repeat('-', 80) . "\n";

$stmt = $db->prepare("
    SELECT pp.id, pp.matricula_id, pp.valor, pp.data_vencimento, pp.status_pagamento_id,
           sp.nome AS status_nome, pp.observacoes
    FROM pagamentos_plano pp
    INNER JOIN status_pagamento sp ON sp.id = pp.status_pagamento_id
    WHERE pp.matricula_id IN (271, 291)
    ORDER BY pp.matricula_id ASC, pp.data_vencimento ASC
");
$stmt->execute();
$pagamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($pagamentos as $p) {
    echo "  Matrícula #{$p['matricula_id']} → Pagamento #{$p['id']}: R\$" 
        . number_format($p['valor'], 2, ',', '.') 
        . " | venc {$p['data_vencimento']} | status: {$p['status_nome']}"
        . ($p['observacoes'] ? " | obs: {$p['observacoes']}" : '') . "\n";
}

echo "\n";
echo "5. FIX NECESSÁRIO\n";
echo str_repeat('-', 80) . "\n";
echo "  O código em MatriculaController::create() linha ~350:\n";
echo "    if (\$statusCodigo === 'vencida' || \$vencidaPorData) {\n";
echo "      \$reutilizandoMatricula = true;\n";
echo "    }\n\n";
echo "  FIX: adicionar 'cancelada' como status reutilizável:\n";
echo "    if (\$statusCodigo === 'vencida' || \$statusCodigo === 'cancelada' || \$vencidaPorData) {\n";
echo "      \$reutilizandoMatricula = true;\n";
echo "    }\n\n";
echo "  Isso faz com que matrículas canceladas antes do vencimento também\n";
echo "  sejam reaproveitadas em vez de criar um novo registro.\n";

echo "\n" . str_repeat('=', 80) . "\n";
echo "Fim do diagnóstico.\n";
