<?php
/**
 * Cancela parcela em pagamentos_plano e recalcula status da matrícula.
 *
 * Uso:
 *   php jobs/cancelar_parcela_plano.php --parcela-id=484 --motivo="Duplicada após troca de plano"
 *   php jobs/cancelar_parcela_plano.php --parcela-id=484 --tenant=3 --dry-run
 *
 * --tenant é opcional (usa o tenant_id da própria parcela).
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

$options = getopt('', ['parcela-id:', 'tenant:', 'motivo:', 'dry-run', 'quiet']);
$parcelaId = isset($options['parcela-id']) ? (int) $options['parcela-id'] : 0;
$tenantIdArg = isset($options['tenant']) ? (int) $options['tenant'] : 0;
$motivo = isset($options['motivo']) ? trim((string) $options['motivo']) : 'Cancelado via script administrativo';
$dryRun = isset($options['dry-run']);
$quiet = isset($options['quiet']);

if ($parcelaId <= 0) {
    fwrite(STDERR, "Uso: php jobs/cancelar_parcela_plano.php --parcela-id=N [--tenant=N] [--motivo=texto] [--dry-run]\n");
    exit(1);
}

$pdo = require __DIR__ . '/config/database.php';

$out = static function (string $msg) use ($quiet): void {
    if (!$quiet) {
        echo $msg . PHP_EOL;
    }
};

$out('▶ cancelar_parcela_plano.php iniciado');

$stmt = $pdo->prepare("
    SELECT pp.id, pp.tenant_id, pp.matricula_id, pp.valor, pp.data_vencimento,
           pp.status_pagamento_id, sp.nome AS status_nome
    FROM pagamentos_plano pp
    INNER JOIN status_pagamento sp ON sp.id = pp.status_pagamento_id
    WHERE pp.id = ?
    LIMIT 1
");
$stmt->execute([$parcelaId]);
$p = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$p) {
    fwrite(STDERR, "Parcela #{$parcelaId} não encontrada no banco.\n");
    exit(1);
}

$tenantId = $tenantIdArg > 0 ? $tenantIdArg : (int) $p['tenant_id'];
if ($tenantId <= 0) {
    fwrite(STDERR, "Parcela #{$parcelaId} sem tenant_id — informe --tenant=N\n");
    exit(1);
}

if ($tenantIdArg > 0 && (int) $p['tenant_id'] !== $tenantIdArg) {
    $out("⚠️  tenant informado ({$tenantIdArg}) difere do registro ({$p['tenant_id']}) — usando {$tenantIdArg}");
    $tenantId = $tenantIdArg;
}

$matriculaId = (int) $p['matricula_id'];
$out("Parcela #{$parcelaId} | matrícula {$matriculaId} | {$p['status_nome']} | R$ {$p['valor']} | venc {$p['data_vencimento']}");

if ((int) $p['status_pagamento_id'] === 4) {
    $out('ℹ️  Parcela já está cancelada.');
    exit(0);
}

if ((int) $p['status_pagamento_id'] === 2) {
    fwrite(STDERR, "❌ Parcela já está PAGA — não use cancelar. Use corrigir_baixa_parcela_mp se necessário.\n");
    exit(1);
}

if ($dryRun) {
    $out('[dry-run] Cancelaria parcela e recalculeria status da matrícula.');
    exit(0);
}

$pdo->beginTransaction();
try {
    $obs = $motivo . " [cancelar_parcela_plano.php]";
    $stmtUp = $pdo->prepare("
        UPDATE pagamentos_plano
        SET status_pagamento_id = 4,
            data_pagamento = NULL,
            observacoes = CONCAT(COALESCE(observacoes, ''), ' | ', ?),
            updated_at = NOW()
        WHERE id = ? AND matricula_id = ?
    ");
    $stmtUp->execute([$obs, $parcelaId, $matriculaId]);
    $rows = $stmtUp->rowCount();
    $out("✅ Parcela #{$parcelaId} cancelada (rows: {$rows})");

    if ($rows === 0) {
        throw new RuntimeException("UPDATE não afetou linhas — confira tenant_id={$tenantId} e id={$parcelaId}");
    }

    $model = new \App\Models\PagamentoPlano($pdo);
    $model->atualizarStatusMatricula($tenantId, $matriculaId);

    $stmtMinPend = $pdo->prepare("
        SELECT MIN(data_vencimento) FROM pagamentos_plano
        WHERE matricula_id = ? AND status_pagamento_id IN (1, 3)
    ");
    $stmtMinPend->execute([$matriculaId]);
    $proxima = $stmtMinPend->fetchColumn();

    $stmtStatusAtiva = $pdo->query("SELECT id FROM status_matricula WHERE codigo = 'ativa' LIMIT 1");
    $statusAtivaId = (int) ($stmtStatusAtiva->fetchColumn() ?: 0);

    $stmtMat = $pdo->prepare('SELECT status_id, data_vencimento FROM matriculas WHERE id = ?');
    $stmtMat->execute([$matriculaId]);
    $mat = $stmtMat->fetch(PDO::FETCH_ASSOC);

    if ($statusAtivaId > 0 && (int) $mat['status_id'] === $statusAtivaId) {
        $pdo->prepare('
            UPDATE matriculas
            SET proxima_data_vencimento = ?, updated_at = NOW()
            WHERE id = ? AND tenant_id = ?
        ')->execute([$proxima ?: $mat['data_vencimento'], $matriculaId, $tenantId]);
    }

    $stmtSt = $pdo->prepare("
        SELECT sm.codigo FROM matriculas m
        INNER JOIN status_matricula sm ON sm.id = m.status_id
        WHERE m.id = ?
    ");
    $stmtSt->execute([$matriculaId]);
    $out('📌 Status matrícula: ' . $stmtSt->fetchColumn());

    $stmtPend = $pdo->prepare("
        SELECT pp.id, sp.nome, pp.valor, pp.data_vencimento
        FROM pagamentos_plano pp
        INNER JOIN status_pagamento sp ON sp.id = pp.status_pagamento_id
        WHERE pp.matricula_id = ? AND pp.status_pagamento_id IN (1, 3)
    ");
    $stmtPend->execute([$matriculaId]);
    $pendentes = $stmtPend->fetchAll(PDO::FETCH_ASSOC);
    if ($pendentes !== []) {
        $out('⚠️  Ainda há parcelas abertas:');
        foreach ($pendentes as $row) {
            $out("   → #{$row['id']} {$row['nome']} R$ {$row['valor']} venc {$row['data_vencimento']}");
        }
    }

    $pdo->commit();
    $out('Concluído.');
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, '❌ ' . $e->getMessage() . "\n");
    exit(1);
}
