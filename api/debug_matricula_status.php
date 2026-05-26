<?php
/**
 * Diagnóstico: por que a matrícula aparece vencida mesmo com PIX pago?
 *
 * Uso: php debug_matricula_status.php 235
 */

require_once __DIR__ . '/vendor/autoload.php';

$matriculaId = isset($argv[1]) ? (int) $argv[1] : 0;
if ($matriculaId <= 0) {
    fwrite(STDERR, "Uso: php debug_matricula_status.php <matricula_id>\n");
    exit(1);
}

$pdo = require __DIR__ . '/config/database.php';
date_default_timezone_set('America/Sao_Paulo');

echo "====== STATUS MATRÍCULA #{$matriculaId} ======\n";
echo date('Y-m-d H:i:s') . "\n\n";

$stmt = $pdo->prepare("
    SELECT m.*, sm.codigo AS status_codigo, sm.nome AS status_nome,
           a.nome AS aluno_nome, p.nome AS plano_nome
    FROM matriculas m
    INNER JOIN status_matricula sm ON sm.id = m.status_id
    INNER JOIN alunos a ON a.id = m.aluno_id
    INNER JOIN planos p ON p.id = m.plano_id
    WHERE m.id = ?
");
$stmt->execute([$matriculaId]);
$m = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$m) {
    echo "Matrícula não encontrada.\n";
    exit(1);
}

echo "Aluno: {$m['aluno_nome']}\n";
echo "Plano: {$m['plano_nome']}\n";
echo "Status BD: {$m['status_codigo']} ({$m['status_nome']})\n";
echo "data_vencimento (acesso): {$m['data_vencimento']}\n";
echo "proxima_data_vencimento: " . ($m['proxima_data_vencimento'] ?? '-') . "\n\n";

echo "--- Parcelas (pagamentos_plano) ---\n";
$stmtPp = $pdo->prepare("
    SELECT pp.id, pp.valor, pp.data_vencimento, pp.data_pagamento,
           sp.codigo AS status, pp.observacoes,
           DATEDIFF(CURDATE(), pp.data_vencimento) AS dias_atraso
    FROM pagamentos_plano pp
    INNER JOIN status_pagamento sp ON sp.id = pp.status_pagamento_id
    WHERE pp.matricula_id = ?
    ORDER BY pp.data_vencimento DESC, pp.id DESC
    LIMIT 15
");
$stmtPp->execute([$matriculaId]);
foreach ($stmtPp->fetchAll(PDO::FETCH_ASSOC) as $p) {
    $flag = '';
    if (in_array($p['status'], ['pendente', 'atrasado'], true) && (int) $p['dias_atraso'] > 0) {
        $flag = ' ← ISSO DEIXA A MATRÍCULA VENCIDA';
    }
    echo sprintf(
        "#%s | %s | R$ %s | venc %s | pago %s%s\n",
        $p['id'],
        $p['status'],
        number_format((float) $p['valor'], 2, ',', '.'),
        $p['data_vencimento'],
        $p['data_pagamento'] ?: '—',
        $flag
    );
    if (!empty($p['observacoes'])) {
        echo "    obs: {$p['observacoes']}\n";
    }
}

echo "\n--- Mercado Pago (últimos) ---\n";
$stmtMp = $pdo->prepare("
    SELECT payment_id, status, transaction_amount, date_approved, external_reference
    FROM pagamentos_mercadopago
    WHERE matricula_id = ?
    ORDER BY id DESC LIMIT 5
");
$stmtMp->execute([$matriculaId]);
foreach ($stmtMp->fetchAll(PDO::FETCH_ASSOC) as $mp) {
    echo "payment {$mp['payment_id']} | {$mp['status']} | R$ {$mp['transaction_amount']} | {$mp['date_approved']}\n";
}

echo "\n--- Regra de status ---\n";
$stmtRegra = $pdo->prepare("
    SELECT
        SUM(CASE WHEN status_pagamento_id IN (1, 3) THEN 1 ELSE 0 END) AS pendentes,
        MAX(CASE WHEN status_pagamento_id IN (1, 3) AND data_vencimento < CURDATE()
            THEN DATEDIFF(CURDATE(), data_vencimento) ELSE 0 END) AS dias_atraso
    FROM pagamentos_plano WHERE matricula_id = ?
");
$stmtRegra->execute([$matriculaId]);
$r = $stmtRegra->fetch(PDO::FETCH_ASSOC);
$statusEsperado = 'ativa';
if ((int) $r['pendentes'] > 0) {
    $statusEsperado = (int) $r['dias_atraso'] >= 5 ? 'cancelada' : ((int) $r['dias_atraso'] >= 1 ? 'vencida' : 'ativa');
}
echo "Pendentes/atrasadas: {$r['pendentes']} | maior atraso: {$r['dias_atraso']} dia(s)\n";
echo "Status esperado pela regra: {$statusEsperado}\n";
echo "Status atual no BD: {$m['status_codigo']}\n";

if ($m['status_codigo'] === 'vencida' && (int) $r['pendentes'] > 0 && (int) $r['dias_atraso'] >= 1) {
    echo "\n>>> CAUSA: existe parcela pendente/atrasada com vencimento no passado.\n";
    echo ">>> O PIX pode ter quitado OUTRA parcela; baixe a parcela vencida ou rode o job após deploy da correção.\n";
}

echo "\nRecalcular status: php -r \"require 'vendor/autoload.php'; \\\$p=require 'config/database.php'; (new App\\Models\\PagamentoPlano(\\\$p))->atualizarStatusMatricula({$m['tenant_id']}, {$matriculaId});\"\n";
