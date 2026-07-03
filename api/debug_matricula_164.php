<?php
/**
 * Investigação matrícula #164
 * Uso: php debug_matricula_164.php
 */

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

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

$matriculaId = 164;
$hoje = date('Y-m-d');

echo "====== MATRÍCULA #{$matriculaId} ======\n";
echo "Data consulta: {$hoje}\n\n";

$stmt = $db->prepare("
    SELECT m.*, sm.codigo AS status_codigo, sm.nome AS status_nome,
           p.nome AS plano_nome, p.duracao_dias, p.modalidade_id,
           mm.nome AS modalidade_nome,
           a.nome AS aluno_nome, u.email AS aluno_email,
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
echo "Plano: {$m['plano_nome']} | Modalidade: {$m['modalidade_nome']}\n";
echo "Status: {$m['status_nome']} ({$m['status_codigo']})\n";
echo "Motivo matrícula: " . ($m['motivo_codigo'] ?? '-') . "\n";
echo "Valor: R$ {$m['valor']}\n";
echo "Data matrícula: {$m['data_matricula']}\n";
echo "Data início: {$m['data_inicio']}\n";
echo "Data vencimento: {$m['data_vencimento']}\n";
echo "Próxima data vencimento: " . ($m['proxima_data_vencimento'] ?? 'NULL') . "\n";
echo "Cancelamento: " . ($m['data_cancelamento'] ?? 'não') . " | motivo: " . ($m['motivo_cancelamento'] ?? '-') . "\n";
echo "Observações: " . ($m['observacoes'] ?? '-') . "\n";
echo "Atualizado em: {$m['updated_at']}\n\n";

$stmtPag = $db->prepare("
    SELECT pp.id, pp.valor, pp.valor_original, pp.desconto, pp.credito_aplicado,
           pp.data_vencimento, pp.data_pagamento, sp.nome AS status,
           pp.status_pagamento_id, pp.observacoes, pp.created_at, pp.updated_at
    FROM pagamentos_plano pp
    INNER JOIN status_pagamento sp ON sp.id = pp.status_pagamento_id
    WHERE pp.matricula_id = ?
    ORDER BY pp.data_vencimento ASC, pp.id ASC
");
$stmtPag->execute([$matriculaId]);
$pagamentos = $stmtPag->fetchAll();

echo "=== PARCELAS (" . count($pagamentos) . ") ===\n";
foreach ($pagamentos as $p) {
    echo "#{$p['id']} | {$p['status']} | R$ {$p['valor']}";
    if ($p['desconto'] > 0) {
        echo " (desc R$ {$p['desconto']}, orig R$ " . ($p['valor_original'] ?? '-') . ")";
    }
    if ($p['credito_aplicado'] > 0) {
        echo " (crédito R$ {$p['credito_aplicado']})";
    }
    echo " | venc {$p['data_vencimento']}";
    if ($p['data_pagamento']) {
        echo " | pago {$p['data_pagamento']}";
    }
    echo "\n";
    if ($p['observacoes']) {
        echo "   obs: {$p['observacoes']}\n";
    }
}

echo "\n=== DESCONTOS MATRÍCULA ===\n";
$stmtDesc = $db->prepare("SELECT id, tipo, valor, percentual, ativo, vigencia_inicio, vigencia_fim, parcelas_restantes, motivo FROM matricula_descontos WHERE matricula_id = ?");
$stmtDesc->execute([$matriculaId]);
$descontos = $stmtDesc->fetchAll();
if (!$descontos) {
    echo "Nenhum desconto cadastrado.\n";
} else {
    foreach ($descontos as $d) {
        $ativo = (int) $d['ativo'] === 1 ? 'ATIVO' : 'inativo';
        $val = $d['valor'] ? "R$ {$d['valor']}" : "{$d['percentual']}%";
        echo "#{$d['id']} {$d['tipo']} {$val} [{$ativo}] vig {$d['vigencia_inicio']} a " . ($d['vigencia_fim'] ?? '∞') . " | {$d['motivo']}\n";
    }
}

echo "\n=== OUTRAS MATRÍCULAS MESMA MODALIDADE ===\n";
$stmtOutras = $db->prepare("
    SELECT m.id, sm.codigo AS status, m.data_inicio, m.data_vencimento, p.nome AS plano
    FROM matriculas m
    INNER JOIN status_matricula sm ON sm.id = m.status_id
    INNER JOIN planos p ON p.id = m.plano_id
    WHERE m.aluno_id = ? AND p.modalidade_id = ? AND m.id != ?
    ORDER BY m.id DESC
");
$stmtOutras->execute([$m['aluno_id'], $m['modalidade_id'], $matriculaId]);
$outras = $stmtOutras->fetchAll();
if (!$outras) {
    echo "Nenhuma outra matrícula na mesma modalidade.\n";
} else {
    foreach ($outras as $o) {
        echo "#{$o['id']} {$o['status']} | {$o['plano']} | {$o['data_inicio']} a {$o['data_vencimento']}\n";
    }
}

echo "\n=== DIAGNÓSTICO ===\n";
$pendentes = array_filter($pagamentos, fn ($p) => in_array($p['status'], ['aguardando', 'atrasado', 'pendente'], true) || (int) ($p['status'] === 'aguardando' ? 1 : 0));
$pagos = array_filter($pagamentos, fn ($p) => $p['status'] === 'pago');
$temPendente = false;
$temPago = false;
foreach ($pagamentos as $p) {
    if ($p['status_pagamento_id'] == 2 || stripos((string) $p['status'], 'pago') !== false) {
        $temPago = true;
    }
    if (in_array((int) $p['status_pagamento_id'], [1, 3], true)) {
        $temPendente = true;
    }
}

if ($m['status_codigo'] === 'pendente') {
    if ($temPendente && !$temPago) {
        echo "- Matrícula PENDENTE aguardando primeiro pagamento (parcela em aberto).\n";
    } elseif ($temPago) {
        echo "- INCONSISTÊNCIA: há parcela paga mas matrícula ainda pendente (status não atualizou).\n";
    } elseif (empty($pagamentos)) {
        echo "- INCONSISTÊNCIA: matrícula pendente sem nenhuma parcela gerada.\n";
    } else {
        echo "- Matrícula pendente; revisar parcelas acima.\n";
    }

    $venc = $m['proxima_data_vencimento'] ?? $m['data_vencimento'];
    if ($venc && $venc < $hoje) {
        echo "- Data de vencimento ({$venc}) já passou; job deveria ter movido para vencida/cancelada.\n";
    }
}

if ((float) $m['valor'] <= 0) {
    echo "- Plano com valor zero: deveria ter sido ativada automaticamente.\n";
}

echo "\n";
