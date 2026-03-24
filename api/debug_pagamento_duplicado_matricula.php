<?php
/**
 * Diagnóstico de pagamentos_plano duplicados após reprocessamento de paymentId.
 *
 * Uso:
 *   php debug_pagamento_duplicado_matricula.php --matricula=190
 *   php debug_pagamento_duplicado_matricula.php --matricula=190 --payment-id=123456789
 */

require_once __DIR__ . '/config/database.php';

$options = getopt('', ['matricula:', 'payment-id:']);

$matriculaId = isset($options['matricula']) ? (int)$options['matricula'] : 0;
$paymentId = isset($options['payment-id']) ? trim((string)$options['payment-id']) : '';

if ($matriculaId <= 0) {
    echo "❌ Informe a matrícula. Ex.: --matricula=190\n";
    exit(1);
}

if (!isset($pdo) || !$pdo) {
    $pdo = require __DIR__ . '/config/database.php';
}

if (!$pdo) {
    echo "❌ Erro ao conectar ao banco\n";
    exit(1);
}

echo "\n🔎 DIAGNÓSTICO DE DUPLICIDADE\n";
echo str_repeat('=', 70) . "\n";
echo "Matrícula: {$matriculaId}\n";
if ($paymentId !== '') {
    echo "Payment ID informado: {$paymentId}\n";
}
echo str_repeat('=', 70) . "\n\n";

$stmtMat = $pdo->prepare(
    "SELECT m.id, m.tenant_id, m.aluno_id, m.plano_id, m.tipo_cobranca,
            m.data_inicio, m.data_vencimento, m.proxima_data_vencimento,
            sm.codigo AS status_codigo, sm.nome AS status_nome
     FROM matriculas m
     LEFT JOIN status_matricula sm ON sm.id = m.status_id
     WHERE m.id = ?
     LIMIT 1"
);
$stmtMat->execute([$matriculaId]);
$matricula = $stmtMat->fetch(PDO::FETCH_ASSOC);

if (!$matricula) {
    echo "❌ Matrícula não encontrada\n";
    exit(1);
}

echo "📋 MATRÍCULA\n";
echo "- tenant_id: {$matricula['tenant_id']}\n";
echo "- aluno_id: {$matricula['aluno_id']}\n";
echo "- plano_id: {$matricula['plano_id']}\n";
echo "- tipo_cobranca: " . ($matricula['tipo_cobranca'] ?? 'N/A') . "\n";
echo "- status: " . ($matricula['status_codigo'] ?? 'N/A') . " - " . ($matricula['status_nome'] ?? 'N/A') . "\n";
echo "- data_inicio: " . ($matricula['data_inicio'] ?? 'N/A') . "\n";
echo "- data_vencimento: " . ($matricula['data_vencimento'] ?? 'N/A') . "\n";
echo "- proxima_data_vencimento: " . ($matricula['proxima_data_vencimento'] ?? 'N/A') . "\n\n";

$stmtAss = $pdo->prepare(
    "SELECT a.id, a.external_reference, a.gateway_preference_id, a.status_gateway,
            ast.codigo AS status_codigo, ast.nome AS status_nome,
            a.criado_em, a.atualizado_em
     FROM assinaturas a
     LEFT JOIN assinatura_status ast ON ast.id = a.status_id
     WHERE a.matricula_id = ?
     ORDER BY a.id DESC"
);
$stmtAss->execute([$matriculaId]);
$assinaturas = $stmtAss->fetchAll(PDO::FETCH_ASSOC) ?: [];

echo "📋 ASSINATURAS\n";
if (!$assinaturas) {
    echo "- Nenhuma assinatura encontrada\n\n";
} else {
    foreach ($assinaturas as $ass) {
        echo "- assinatura #{$ass['id']} | gateway={$ass['status_gateway']} | status={$ass['status_codigo']} | external_ref=" . ($ass['external_reference'] ?? 'N/A') . "\n";
    }
    echo "\n";
}

$sqlMp = "SELECT id, payment_id, external_reference, status, status_detail,
                  transaction_amount, date_approved, date_created, created_at
           FROM pagamentos_mercadopago
           WHERE matricula_id = ?";
$paramsMp = [$matriculaId];

if ($paymentId !== '') {
    $sqlMp .= " AND payment_id = ?";
    $paramsMp[] = $paymentId;
}

$sqlMp .= " ORDER BY id DESC";

$stmtMp = $pdo->prepare($sqlMp);
$stmtMp->execute($paramsMp);
$pagamentosMp = $stmtMp->fetchAll(PDO::FETCH_ASSOC) ?: [];

echo "📋 PAGAMENTOS_MERCADOPAGO\n";
if (!$pagamentosMp) {
    echo "- Nenhum pagamento_mercadopago encontrado\n\n";
} else {
    foreach ($pagamentosMp as $mp) {
        echo "- pm#{$mp['id']} | payment_id={$mp['payment_id']} | status={$mp['status']} | valor={$mp['transaction_amount']} | approved={$mp['date_approved']} | ext_ref=" . ($mp['external_reference'] ?? 'N/A') . "\n";
    }
    echo "\n";
}

$stmtPlano = $pdo->prepare(
    "SELECT pp.id, pp.tenant_id, pp.aluno_id, pp.matricula_id, pp.plano_id,
            pp.valor, pp.data_vencimento, pp.data_pagamento, pp.status_pagamento_id,
            pp.forma_pagamento_id, pp.tipo_baixa_id, pp.observacoes,
            pp.created_at, pp.updated_at,
            sp.nome AS status_nome
     FROM pagamentos_plano pp
     LEFT JOIN status_pagamento sp ON sp.id = pp.status_pagamento_id
     WHERE pp.matricula_id = ?
     ORDER BY pp.id ASC"
);
$stmtPlano->execute([$matriculaId]);
$pagamentosPlano = $stmtPlano->fetchAll(PDO::FETCH_ASSOC) ?: [];

echo "📋 PAGAMENTOS_PLANO\n";
if (!$pagamentosPlano) {
    echo "- Nenhum pagamento_plano encontrado\n\n";
    exit(0);
}

$candidatos = [];

foreach ($pagamentosPlano as $pp) {
    $flags = [];
    $obs = (string)($pp['observacoes'] ?? '');
    $statusId = (int)($pp['status_pagamento_id'] ?? 0);

    if ($statusId === 2) {
        $flags[] = 'PAGO';
    }

    if (stripos($obs, 'Mercado Pago') !== false) {
        $flags[] = 'MERCADO_PAGO';
    }

    if (stripos($obs, 'detectado via polling') !== false) {
        $flags[] = 'POLLING_JOB';
    }

    if (stripos($obs, 'webhook') !== false) {
        $flags[] = 'WEBHOOK';
    }

    if ($paymentId !== '') {
        if (strpos($obs, 'ID: ' . $paymentId) !== false) {
            $flags[] = 'MATCH_ID';
        }
        if (strpos($obs, 'Payment #' . $paymentId) !== false) {
            $flags[] = 'MATCH_PAYMENT';
        }
    }

    if ($statusId === 2 && (in_array('MATCH_ID', $flags, true) || in_array('MATCH_PAYMENT', $flags, true))) {
        $candidatos[] = [
            'id' => (int)$pp['id'],
            'motivo' => 'Pagamento pago vinculado ao paymentId informado',
        ];
    }

    echo sprintf(
        "- pp#%d | status=%s (%d) | venc=%s | pago=%s | valor=%.2f | baixa=%s | flags=[%s]\n",
        (int)$pp['id'],
        (string)($pp['status_nome'] ?? 'N/A'),
        $statusId,
        (string)($pp['data_vencimento'] ?? 'NULL'),
        (string)($pp['data_pagamento'] ?? 'NULL'),
        (float)($pp['valor'] ?? 0),
        (string)($pp['tipo_baixa_id'] ?? 'NULL'),
        implode(', ', $flags)
    );
    echo '  observacoes: ' . ($obs !== '' ? $obs : 'N/A') . "\n";
}

echo "\n🧠 ANÁLISE\n";

$pagos = array_values(array_filter($pagamentosPlano, static function (array $row): bool {
    return (int)($row['status_pagamento_id'] ?? 0) === 2;
}));

if (count($pagos) <= 1) {
    echo "- Não há duplicidade óbvia de pagamentos pagos para esta matrícula.\n";
    exit(0);
}

echo '- Existem ' . count($pagos) . " pagamentos com status PAGO para a matrícula.\n";

if ($paymentId !== '' && $candidatos) {
    echo "- Candidato(s) diretamente associado(s) ao paymentId {$paymentId}:\n";
    foreach ($candidatos as $cand) {
        echo "  • pagamentos_plano #{$cand['id']} => {$cand['motivo']}\n";
    }
}

$duplicadosMesmoDia = [];
for ($i = 0; $i < count($pagos); $i++) {
    for ($j = $i + 1; $j < count($pagos); $j++) {
        $a = $pagos[$i];
        $b = $pagos[$j];

        if (($a['valor'] ?? null) == ($b['valor'] ?? null)
            && ($a['data_pagamento'] ?? null) == ($b['data_pagamento'] ?? null)) {
            $duplicadosMesmoDia[] = [(int)$a['id'], (int)$b['id']];
        }
    }
}

if ($duplicadosMesmoDia) {
    echo "- Pares suspeitos por mesmo valor e mesma data_pagamento:\n";
    foreach ($duplicadosMesmoDia as $par) {
        echo "  • pagamentos_plano #{$par[0]} e #{$par[1]}\n";
    }
}

echo "\n✅ Diagnóstico concluído\n";