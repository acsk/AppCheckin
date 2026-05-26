<?php
/**
 * Corrige baixa MP aplicada na parcela errada (valor/data divergentes).
 *
 * Uso:
 *   php jobs/corrigir_baixa_parcela_mp.php --parcela-id=575 --payment-id=160879679884 --tenant=3
 *   php jobs/corrigir_baixa_parcela_mp.php --parcela-id=575 --payment-id=160879679884 --tenant=3 --dry-run
 *   php jobs/corrigir_baixa_parcela_mp.php ... --reverter-parcela-errada=484
 *   php jobs/corrigir_baixa_parcela_mp.php ... --cancelar-parcela-errada=484
 *     (use cancelar se a parcela errada é duplicata — reverter deixa R$ 70 em aberto e matrícula vencida)
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

$options = getopt('', [
    'parcela-id:',
    'payment-id:',
    'tenant:',
    'reverter-parcela-errada:',
    'cancelar-parcela-errada:',
    'dry-run',
    'quiet',
]);
$parcelaId = isset($options['parcela-id']) ? (int) $options['parcela-id'] : 0;
$paymentId = isset($options['payment-id']) ? preg_replace('/\D/', '', (string) $options['payment-id']) : '';
$tenantId = isset($options['tenant']) ? (int) $options['tenant'] : 0;
$reverterParcelaId = isset($options['reverter-parcela-errada']) ? (int) $options['reverter-parcela-errada'] : 0;
$cancelarParcelaId = isset($options['cancelar-parcela-errada']) ? (int) $options['cancelar-parcela-errada'] : 0;
$dryRun = isset($options['dry-run']);
$quiet = isset($options['quiet']);

if ($parcelaId <= 0 || $paymentId === '' || $tenantId <= 0) {
    fwrite(STDERR, "Uso: php jobs/corrigir_baixa_parcela_mp.php --parcela-id=N --payment-id=ID --tenant=N [--reverter-parcela-errada=N] [--dry-run]\n");
    exit(1);
}

$pdo = require __DIR__ . '/../config/database.php';

function out(string $msg, bool $quiet): void
{
    if (!$quiet) {
        echo $msg . "\n";
    }
}

$stmt = $pdo->prepare("
    SELECT pp.*, m.id AS matricula_id, m.tenant_id
    FROM pagamentos_plano pp
    INNER JOIN matriculas m ON m.id = pp.matricula_id
    WHERE pp.id = ? AND pp.tenant_id = ?
");
$stmt->execute([$parcelaId, $tenantId]);
$parcela = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$parcela) {
    fwrite(STDERR, "Parcela #{$parcelaId} não encontrada no tenant {$tenantId}\n");
    exit(1);
}

$matriculaId = (int) $parcela['matricula_id'];

$stmtMp = $pdo->prepare("
    SELECT payment_id, status, transaction_amount, date_approved
    FROM pagamentos_mercadopago
    WHERE payment_id = ? AND tenant_id = ?
    LIMIT 1
");
$stmtMp->execute([$paymentId, $tenantId]);
$mp = $stmtMp->fetch(PDO::FETCH_ASSOC);

if (!$mp || strtolower((string) $mp['status']) !== 'approved') {
    fwrite(STDERR, "Payment {$paymentId} não encontrado ou não approved em pagamentos_mercadopago\n");
    exit(1);
}

$valorMp = (float) $mp['transaction_amount'];
$valorParcela = (float) $parcela['valor'];
$diff = abs($valorMp - $valorParcela);

out("Matrícula: {$matriculaId}", $quiet);
out("Parcela alvo #{$parcelaId}: R$ " . number_format($valorParcela, 2, ',', '.') . " | status_id={$parcela['status_pagamento_id']} | venc {$parcela['data_vencimento']}", $quiet);
out("MP {$paymentId}: R$ " . number_format($valorMp, 2, ',', '.') . " | diff R$ " . number_format($diff, 2, ',', '.'), $quiet);

if ($diff > 0.01) {
    out("⚠️  Valores divergentes — confira antes de confirmar.", $quiet);
}

$dateApproved = !empty($mp['date_approved'])
    ? date('Y-m-d H:i:s', strtotime((string) $mp['date_approved']))
    : date('Y-m-d H:i:s');

$obs = "Pago via Mercado Pago - ID: {$paymentId} (correção parcela #{$parcelaId})";

if ($dryRun) {
    out("[dry-run] Baixaria parcela #{$parcelaId}", $quiet);
    if ($reverterParcelaId > 0) {
        out("[dry-run] Reverteria parcela #{$reverterParcelaId} para atrasado", $quiet);
    }
    if ($cancelarParcelaId > 0) {
        out("[dry-run] Cancelaria parcela #{$cancelarParcelaId}", $quiet);
    }
    out("[dry-run] Chamaria atualizarStatusMatricula({$tenantId}, {$matriculaId})", $quiet);
    exit(0);
}

$pdo->beginTransaction();

try {
    if ($cancelarParcelaId > 0) {
        $stmtCan = $pdo->prepare("
            UPDATE pagamentos_plano
            SET status_pagamento_id = 4,
                data_pagamento = NULL,
                observacoes = CONCAT(COALESCE(observacoes, ''), ' [Cancelada: MP {$paymentId} realocado para #{$parcelaId}]'),
                updated_at = NOW()
            WHERE id = ? AND matricula_id = ? AND tenant_id = ?
              AND status_pagamento_id IN (1, 3)
        ");
        $stmtCan->execute([$cancelarParcelaId, $matriculaId, $tenantId]);
        out("🚫 Parcela #{$cancelarParcelaId} cancelada (rows: {$stmtCan->rowCount()})", $quiet);
    } elseif ($reverterParcelaId > 0) {
        $stmtRev = $pdo->prepare("
            UPDATE pagamentos_plano
            SET status_pagamento_id = 3,
                data_pagamento = NULL,
                observacoes = CONCAT(COALESCE(observacoes, ''), ' [Revertido: MP {$paymentId} realocado para parcela #{$parcelaId}]'),
                updated_at = NOW()
            WHERE id = ? AND matricula_id = ? AND tenant_id = ?
        ");
        $stmtRev->execute([$reverterParcelaId, $matriculaId, $tenantId]);
        out("↩️  Parcela #{$reverterParcelaId} revertida para atrasado (rows: {$stmtRev->rowCount()})", $quiet);
        out("⚠️  Se essa parcela não era devida, cancele com: php jobs/cancelar_parcela_plano.php --parcela-id={$reverterParcelaId} --tenant={$tenantId}", $quiet);
    }

    $stmtBaixa = $pdo->prepare("
        UPDATE pagamentos_plano
        SET status_pagamento_id = 2,
            data_pagamento = ?,
            forma_pagamento_id = 8,
            tipo_baixa_id = 4,
            observacoes = ?,
            updated_at = NOW()
        WHERE id = ? AND matricula_id = ? AND tenant_id = ?
    ");
    $stmtBaixa->execute([$dateApproved, $obs, $parcelaId, $matriculaId, $tenantId]);
    out("✅ Parcela #{$parcelaId} baixada (rows: {$stmtBaixa->rowCount()})", $quiet);

    $pagamentoModel = new \App\Models\PagamentoPlano($pdo);
    $pagamentoModel->atualizarStatusMatricula($tenantId, $matriculaId);

    $stmtSt = $pdo->prepare("
        SELECT sm.codigo FROM matriculas m
        INNER JOIN status_matricula sm ON sm.id = m.status_id
        WHERE m.id = ?
    ");
    $stmtSt->execute([$matriculaId]);
    $status = $stmtSt->fetchColumn();
    out("📌 Status matrícula após correção: {$status}", $quiet);

    if ($status === 'vencida') {
        $stmtPend = $pdo->prepare("
            SELECT pp.id, sp.nome, pp.valor, pp.data_vencimento,
                   DATEDIFF(CURDATE(), pp.data_vencimento) AS dias_atraso
            FROM pagamentos_plano pp
            INNER JOIN status_pagamento sp ON sp.id = pp.status_pagamento_id
            WHERE pp.matricula_id = ? AND pp.status_pagamento_id IN (1, 3)
        ");
        $stmtPend->execute([$matriculaId]);
        foreach ($stmtPend->fetchAll(PDO::FETCH_ASSOC) as $row) {
            out("   → aberta #{$row['id']} | {$row['nome']} | R$ {$row['valor']} | venc {$row['data_vencimento']} | atraso {$row['dias_atraso']}d", $quiet);
        }
    }

    $pdo->commit();
    out('Concluído.', $quiet);
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, '❌ ' . $e->getMessage() . "\n");
    exit(1);
}
