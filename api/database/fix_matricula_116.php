<?php
/**
 * Fix Matrícula #116
 * Problema: proxima_data_vencimento ficou em 2026-03-19 (nunca atualizou após baixa manual)
 *           porque o sync pulava matrículas com assinatura (mesmo cancelada).
 *           Job marcou como cancelada (14 dias de atraso).
 * 
 * Fix: Recalcular proxima_data_vencimento pela próxima parcela pendente e reativar.
 * 
 * Uso: /opt/alt/php82/usr/bin/php database/fix_matricula_116.php [--execute]
 */
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

$execute = in_array('--execute', $argv);
echo "=== Fix Matrícula #116 ===\n";
echo "Modo: " . ($execute ? "EXECUTANDO" : "DRY-RUN (use --execute)") . "\n\n";

// Estado atual
$stmt = $pdo->query("
    SELECT m.id, m.status_id, m.proxima_data_vencimento, m.data_vencimento, sm.codigo
    FROM matriculas m
    INNER JOIN status_matricula sm ON sm.id = m.status_id
    WHERE m.id = 116
");
$mat = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Estado atual: status={$mat['codigo']} | prox_venc=" . ($mat['proxima_data_vencimento'] ?? 'NULL') . " | data_venc={$mat['data_vencimento']}\n";

// Próxima parcela pendente
$stmt = $pdo->query("
    SELECT MIN(data_vencimento) as prox_venc
    FROM pagamentos_plano
    WHERE matricula_id = 116 AND status_pagamento_id IN (1, 3)
");
$proxVenc = $stmt->fetchColumn();
echo "Próxima parcela pendente: " . ($proxVenc ?? 'nenhuma') . "\n";

// Max parcela paga (fallback)
$stmt = $pdo->query("
    SELECT MAX(data_vencimento) as max_pago
    FROM pagamentos_plano
    WHERE matricula_id = 116 AND status_pagamento_id = 2
");
$maxPago = $stmt->fetchColumn();
echo "Última parcela paga: " . ($maxPago ?? 'nenhuma') . "\n";

$novaProxVenc = $proxVenc ?? $maxPago;
$novoStatus = 'ativa';
$hoje = date('Y-m-d');

if ($novaProxVenc && $novaProxVenc < $hoje) {
    $diff = (strtotime($hoje) - strtotime($novaProxVenc)) / 86400;
    if ($diff >= 5) $novoStatus = 'cancelada';
    elseif ($diff >= 1) $novoStatus = 'vencida';
}

echo "\nAção: proxima_data_vencimento → {$novaProxVenc} | status → {$novoStatus}\n";

if ($execute) {
    $stmt = $pdo->prepare("
        UPDATE matriculas
        SET proxima_data_vencimento = ?,
            data_vencimento = ?,
            status_id = (SELECT id FROM status_matricula WHERE codigo = ? LIMIT 1),
            updated_at = NOW()
        WHERE id = 116
    ");
    $stmt->execute([$novaProxVenc, $novaProxVenc, $novoStatus]);
    echo "✅ Matrícula #116 corrigida!\n";
} else {
    echo "(use --execute para aplicar)\n";
}
