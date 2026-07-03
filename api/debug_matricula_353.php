<?php
/**
 * Investigação matrícula #353 — check-in bloqueado
 * Uso: php debug_matricula_353.php
 */

declare(strict_types=1);

date_default_timezone_set('America/Sao_Paulo');

$host = getenv('PROD_DB_HOST') ?: 'srv1314.hstgr.io';
$dbname = getenv('PROD_DB_NAME') ?: 'u304177849_api';
$user = getenv('PROD_DB_USER') ?: 'u304177849_api';
$pass = getenv('PROD_DB_PASS') ?: '+DEEJ&7t';

$db = new PDO(
    "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
    $user,
    $pass,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

$matriculaId = 353;
$hoje = date('Y-m-d');

echo "====== MATRÍCULA #{$matriculaId} ======\n";
echo "Data consulta: {$hoje} " . date('H:i:s') . "\n\n";

$stmt = $db->prepare("
    SELECT m.*, sm.codigo AS status_codigo, sm.nome AS status_nome,
           sm.permite_checkin, sm.ativo AS status_ativo, sm.dias_tolerancia,
           p.nome AS plano_nome, p.duracao_dias, p.modalidade_id, p.checkins_semanais,
           mm.nome AS modalidade_nome,
           a.nome AS aluno_nome, a.id AS aluno_id, u.email AS aluno_email, u.id AS usuario_id,
           mot.codigo AS motivo_codigo
    FROM matriculas m
    INNER JOIN status_matricula sm ON sm.id = m.status_id
    INNER JOIN planos p ON p.id = m.plano_id
    LEFT JOIN modalidades mm ON mm.id = p.modalidade_id
    INNER JOIN alunos a ON a.id = m.aluno_id
    INNER JOIN usuarios u ON u.id = a.usuario_id
    LEFT JOIN motivo_matricula mot ON mot.id = m.motivo_id
    WHERE m.id = ?
");
$stmt->execute([$matriculaId]);
$m = $stmt->fetch();

if (!$m) {
    echo "Matrícula não encontrada.\n";
    exit(1);
}

echo "Aluno: {$m['aluno_nome']} ({$m['aluno_email']})\n";
echo "Aluno ID: {$m['aluno_id']} | Usuario ID: {$m['usuario_id']} | Tenant: {$m['tenant_id']}\n";
echo "Plano: {$m['plano_nome']} | Modalidade: {$m['modalidade_nome']}\n";
echo "Check-ins semanais do plano: " . ($m['checkins_semanais'] ?? '-') . "\n";
echo "Status: {$m['status_nome']} ({$m['status_codigo']})\n";
echo "permite_checkin: {$m['permite_checkin']} | status_ativo: {$m['status_ativo']} | tolerância: " . ($m['dias_tolerancia'] ?? 'null') . "\n";
echo "Motivo matrícula: " . ($m['motivo_codigo'] ?? '-') . "\n";
echo "Valor: R$ {$m['valor']}\n";
echo "Data matrícula: {$m['data_matricula']}\n";
echo "Data início: {$m['data_inicio']}\n";
echo "Data vencimento: {$m['data_vencimento']}\n";
echo "Próxima data vencimento: " . ($m['proxima_data_vencimento'] ?? 'NULL') . "\n";
echo "Cancelamento: " . ($m['data_cancelamento'] ?? 'não') . " | motivo: " . ($m['motivo_cancelamento'] ?? '-') . "\n";
echo "Pacote contrato: " . ($m['pacote_contrato_id'] ?? 'não') . "\n";
echo "Atualizado em: {$m['updated_at']}\n\n";

$vencRef = $m['proxima_data_vencimento'] ?: $m['data_vencimento'];
$diasAtraso = $vencRef ? (int) ((strtotime($hoje) - strtotime($vencRef)) / 86400) : null;
echo "Vencimento referência: {$vencRef}\n";
echo "Dias em relação a hoje: " . ($diasAtraso === null ? 'n/a' : ($diasAtraso > 0 ? "atrasado {$diasAtraso} dia(s)" : ($diasAtraso === 0 ? 'vence hoje' : 'faltam ' . abs($diasAtraso) . ' dia(s)'))) . "\n\n";

$stmtPag = $db->prepare("
    SELECT pp.id, pp.valor, pp.valor_original, pp.desconto, pp.credito_aplicado,
           pp.data_vencimento, pp.data_pagamento, sp.nome AS status,
           pp.status_pagamento_id, pp.observacoes, pp.created_at
    FROM pagamentos_plano pp
    INNER JOIN status_pagamento sp ON sp.id = pp.status_pagamento_id
    WHERE pp.matricula_id = ?
    ORDER BY pp.data_vencimento ASC, pp.id ASC
");
$stmtPag->execute([$matriculaId]);
$pagamentos = $stmtPag->fetchAll();

echo "=== PARCELAS (" . count($pagamentos) . ") ===\n";
foreach ($pagamentos as $p) {
    echo "#{$p['id']} | {$p['status']} (id={$p['status_pagamento_id']}) | R$ {$p['valor']}";
    echo " | venc {$p['data_vencimento']}";
    if ($p['data_pagamento']) {
        echo " | pago {$p['data_pagamento']}";
    }
    echo "\n";
    if ($p['observacoes']) {
        echo "   obs: {$p['observacoes']}\n";
    }
}

echo "\n=== CHECK-INS RECENTES ===\n";
$stmtCk = $db->prepare("
    SELECT c.id, c.data_checkin, c.presente, c.turma_id, t.nome AS turma_nome, c.created_at
    FROM checkins c
    LEFT JOIN turmas t ON t.id = c.turma_id
    WHERE c.matricula_id = ?
    ORDER BY c.id DESC
    LIMIT 10
");
try {
    $stmtCk->execute([$matriculaId]);
    $checkins = $stmtCk->fetchAll();
    if (!$checkins) {
        echo "Nenhum check-in registrado nesta matrícula.\n";
    } else {
        foreach ($checkins as $c) {
            echo "#{$c['id']} | {$c['data_checkin']} | presente=" . var_export($c['presente'], true)
                . " | turma " . ($c['turma_nome'] ?? $c['turma_id']) . "\n";
        }
    }
} catch (Throwable $e) {
    echo "Erro ao buscar checkins: {$e->getMessage()}\n";
    // fallback sem join
    $stmtCk2 = $db->prepare("SELECT * FROM checkins WHERE matricula_id = ? ORDER BY id DESC LIMIT 10");
    $stmtCk2->execute([$matriculaId]);
    print_r($stmtCk2->fetchAll());
}

echo "\n=== OUTRAS MATRÍCULAS DO ALUNO ===\n";
$stmtOutras = $db->prepare("
    SELECT m.id, sm.codigo AS status, sm.permite_checkin, m.data_inicio, m.data_vencimento,
           m.proxima_data_vencimento, p.nome AS plano, mm.nome AS modalidade
    FROM matriculas m
    INNER JOIN status_matricula sm ON sm.id = m.status_id
    INNER JOIN planos p ON p.id = m.plano_id
    LEFT JOIN modalidades mm ON mm.id = p.modalidade_id
    WHERE m.aluno_id = ? AND m.id != ?
    ORDER BY m.id DESC
");
$stmtOutras->execute([$m['aluno_id'], $matriculaId]);
$outras = $stmtOutras->fetchAll();
if (!$outras) {
    echo "Nenhuma outra matrícula.\n";
} else {
    foreach ($outras as $o) {
        echo "#{$o['id']} {$o['status']} permite_checkin={$o['permite_checkin']} | {$o['modalidade']} | {$o['plano']} | {$o['data_inicio']} a " . ($o['proxima_data_vencimento'] ?: $o['data_vencimento']) . "\n";
    }
}

echo "\n=== ASSINATURA MP ===\n";
$stmtAss = $db->prepare("
    SELECT a.id, a.status_gateway, ast.nome AS status, a.valor, a.data_inicio, a.data_fim,
           a.payment_url, a.external_reference, a.atualizado_em
    FROM assinaturas a
    LEFT JOIN assinatura_status ast ON ast.id = a.status_id
    WHERE a.matricula_id = ?
");
$stmtAss->execute([$matriculaId]);
$assinaturas = $stmtAss->fetchAll();
if (!$assinaturas) {
    echo "Nenhuma assinatura.\n";
} else {
    foreach ($assinaturas as $a) {
        echo "#{$a['id']} {$a['status']} gateway={$a['status_gateway']} R$ {$a['valor']} | {$a['data_inicio']} a {$a['data_fim']}\n";
        echo "  ref: {$a['external_reference']}\n";
    }
}

echo "\n=== DIAGNÓSTICO CHECK-IN ===\n";
$bloqueios = [];

if ((int) $m['permite_checkin'] !== 1) {
    $bloqueios[] = "Status '{$m['status_codigo']}' NÃO permite check-in (permite_checkin=0).";
}
if ((int) $m['status_ativo'] !== 1) {
    $bloqueios[] = "Status marcado como inativo no domínio.";
}
if ($m['status_codigo'] === 'bloqueado') {
    $bloqueios[] = "Matrícula bloqueada administrativamente.";
}
if ($m['status_codigo'] === 'pendente') {
    $bloqueios[] = "Matrícula pendente — aguarda pagamento da primeira parcela.";
}
if ($m['status_codigo'] === 'vencida') {
    $bloqueios[] = "Matrícula vencida — pagamento em atraso.";
}
if ($m['status_codigo'] === 'cancelada') {
    $bloqueios[] = "Matrícula cancelada.";
}
if ($m['status_codigo'] === 'finalizada') {
    $bloqueios[] = "Matrícula finalizada.";
}
if ($vencRef && $vencRef < $hoje && $m['status_codigo'] === 'ativa') {
    $bloqueios[] = "Status ativa mas data de vencimento ({$vencRef}) já passou — inconsistência.";
}

$temPago = false;
$temAberto = false;
foreach ($pagamentos as $p) {
    if ((int) $p['status_pagamento_id'] === 2) {
        $temPago = true;
    }
    if (in_array((int) $p['status_pagamento_id'], [1, 3], true)) {
        $temAberto = true;
        if ($p['data_vencimento'] < $hoje) {
            $bloqueios[] = "Parcela #{$p['id']} em aberto e vencida em {$p['data_vencimento']}.";
        }
    }
}

if ($m['status_codigo'] === 'pendente' && $temPago) {
    $bloqueios[] = "INCONSISTÊNCIA: há parcela paga mas status ainda pendente.";
}
if ($m['status_codigo'] === 'pendente' && !$temPago && $temAberto) {
    $bloqueios[] = "Aguardando pagamento da parcela em aberto — comportamento esperado.";
}

if (!$bloqueios) {
    echo "Nenhum bloqueio óbvio encontrado. Status permite check-in.\n";
    echo "Se o aluno ainda não consegue, verificar: limite semanal, turma/modalidade, app desatualizado ou erro de rede.\n";
} else {
    foreach ($bloqueios as $b) {
        echo "- {$b}\n";
    }
}

echo "\n";
