<?php
/**
 * Corrige datas duplicadas da matrícula diária #324 (WALDESSANDRO).
 * Uso: php debug_corrigir_matricula_324.php [--dry-run]
 */

declare(strict_types=1);

$dryRun = in_array('--dry-run', $argv, true);
$matriculaId = 324;

$host = getenv('PROD_DB_HOST') ?: 'srv1314.hstgr.io';
$dbname = getenv('PROD_DB_NAME') ?: 'u304177849_api';
$user = getenv('PROD_DB_USER') ?: 'u304177849_api';
$pass = getenv('PROD_DB_PASS') ?: '+DEEJ&7t';

$pdo = new PDO(
    "mysql:host={$host};port=3306;dbname={$dbname};charset=utf8mb4",
    $user,
    $pass,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

echo "Correção matrícula diária #{$matriculaId}" . ($dryRun ? ' (DRY-RUN)' : '') . "\n";
echo str_repeat('─', 60) . "\n";

// Parcelas pagas com vencimento errado (14/05) — alinhar ao dia do pagamento
$correcoesParcelas = [
    589 => '2026-05-27',
    598 => '2026-05-29',
    617 => '2026-06-02',
    669 => '2026-06-12',
];

foreach ($correcoesParcelas as $parcelaId => $novaData) {
    $stmt = $pdo->prepare("
        SELECT id, data_vencimento, data_pagamento, observacoes
        FROM pagamentos_plano WHERE id = ? AND matricula_id = ?
    ");
    $stmt->execute([$parcelaId, $matriculaId]);
    $p = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$p) {
        echo "  ⏭️  Parcela #{$parcelaId} não encontrada\n";
        continue;
    }
    if ($p['data_vencimento'] === $novaData) {
        echo "  ✓  Parcela #{$parcelaId} já está com venc {$novaData}\n";
        continue;
    }
    echo "  → Parcela #{$parcelaId}: venc {$p['data_vencimento']} → {$novaData} (pago em {$p['data_pagamento']})\n";
    if (!$dryRun) {
        $pdo->prepare("
            UPDATE pagamentos_plano
            SET data_vencimento = ?,
                observacoes = CONCAT(COALESCE(observacoes, ''), ' [Vencimento corrigido: diária]'),
                updated_at = NOW()
            WHERE id = ?
        ")->execute([$novaData, $parcelaId]);
    }
}

// Última diária paga: vigência da matrícula
$dataInicio = '2026-06-12';
$dataVencimento = '2026-06-13'; // diária +1 dia

$stmtMat = $pdo->prepare("SELECT data_inicio, data_vencimento, proxima_data_vencimento, status_id FROM matriculas WHERE id = ?");
$stmtMat->execute([$matriculaId]);
$mat = $stmtMat->fetch(PDO::FETCH_ASSOC);

echo "\nMatrícula #{$matriculaId}:\n";
echo "  Atual:  inicio={$mat['data_inicio']} venc={$mat['data_vencimento']} proxima={$mat['proxima_data_vencimento']}\n";
echo "  Novo:   inicio={$dataInicio} venc={$dataVencimento} proxima={$dataVencimento} (status cancelada mantido)\n";

if (!$dryRun) {
    $pdo->prepare("
        UPDATE matriculas
        SET data_inicio = ?,
            data_vencimento = ?,
            proxima_data_vencimento = ?,
            updated_at = NOW()
        WHERE id = ?
    ")->execute([$dataInicio, $dataVencimento, $dataVencimento, $matriculaId]);
    echo "\n✅ Correção aplicada.\n";
} else {
    echo "\n(dry-run — nenhuma alteração gravada)\n";
}

// Verificação final
echo "\n" . str_repeat('─', 60) . "\nParcelas após correção:\n";
$stmtFinal = $pdo->prepare("
    SELECT pp.id, sp.nome as status, pp.data_vencimento, pp.data_pagamento
    FROM pagamentos_plano pp
    JOIN status_pagamento sp ON sp.id = pp.status_pagamento_id
    WHERE pp.matricula_id = ? AND pp.status_pagamento_id = 2
    ORDER BY pp.data_pagamento
");
$stmtFinal->execute([$matriculaId]);
foreach ($stmtFinal->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo "  #{$row['id']} {$row['status']} | venc: {$row['data_vencimento']} | pago: {$row['data_pagamento']}\n";
}

echo "\n";
