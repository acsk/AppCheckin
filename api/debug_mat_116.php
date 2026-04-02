<?php
/**
 * Fix Matrícula 116: proxima_data_vencimento parada em 2026-03-19
 * Assinatura cancelada, baixa manual feita, parcela #264 aguardando 17/04/2026
 * 
 * Uso: php debug_mat_116.php [--execute]
 */
require 'vendor/autoload.php';
require 'config/database.php';

$execute = in_array('--execute', $argv);
echo "=== Fix Matrícula 116 ===\n";
echo "Modo: " . ($execute ? "EXECUTANDO" : "DRY-RUN (use --execute)") . "\n\n";

// Estado atual
$stmt = $pdo->query("
    SELECT m.id, m.proxima_data_vencimento, m.data_vencimento, sm.codigo as status
    FROM matriculas m
    INNER JOIN status_matricula sm ON sm.id = m.status_id
    WHERE m.id = 116
");
$mat = $stmt->fetch(PDO::FETCH_ASSOC);
echo "  Status atual: {$mat['status']}\n";
echo "  proxima_data_vencimento: " . ($mat['proxima_data_vencimento'] ?? 'NULL') . "\n";
echo "  data_vencimento: {$mat['data_vencimento']}\n";

// Próxima parcela pendente
$stmt = $pdo->query("
    SELECT MIN(data_vencimento) as prox_venc
    FROM pagamentos_plano
    WHERE matricula_id = 116 AND status_pagamento_id IN (1, 3)
");
$proxVenc = $stmt->fetchColumn();
echo "  Próxima parcela pendente: " . ($proxVenc ?? 'nenhuma') . "\n";

// Max pago
$stmt = $pdo->query("
    SELECT MAX(data_vencimento) as max_pago
    FROM pagamentos_plano
    WHERE matricula_id = 116 AND status_pagamento_id = 2
");
$maxPago = $stmt->fetchColumn();
echo "  Último pgto pago até: " . ($maxPago ?? 'nenhum') . "\n";

// Calcular acesso_ate: se tem pendente futuro, usa vencimento dele; senão, usa max pago
$novaProxVenc = $proxVenc ?: $maxPago;
$novaDataVenc = $proxVenc ?: $maxPago;
echo "\n  Nova proxima_data_vencimento: {$novaProxVenc}\n";
echo "  Nova data_vencimento: {$novaDataVenc}\n";

if ($execute && $novaProxVenc) {
    // 1. Atualizar proxima_data_vencimento e data_vencimento
    $stmt = $pdo->prepare("
        UPDATE matriculas 
        SET proxima_data_vencimento = ?,
            data_vencimento = ?,
            updated_at = NOW()
        WHERE id = 116
    ");
    $stmt->execute([$novaProxVenc, $novaDataVenc]);
    echo "  ✅ Datas atualizadas\n";

    // 2. Reativar como 'ativa' (proxima parcela é 17/04, futuro)
    $hoje = date('Y-m-d');
    if ($novaProxVenc >= $hoje) {
        $stmt = $pdo->prepare("
            UPDATE matriculas 
            SET status_id = (SELECT id FROM status_matricula WHERE codigo = 'ativa' LIMIT 1),
                updated_at = NOW()
            WHERE id = 116
        ");
        $stmt->execute();
        echo "  ✅ Status reativado para 'ativa'\n";
    } else {
        echo "  ⚠️ Próx vencimento no passado, não reativando\n";
    }

    // Verificar resultado
    $stmt = $pdo->query("
        SELECT sm.codigo as status, m.proxima_data_vencimento, m.data_vencimento
        FROM matriculas m
        INNER JOIN status_matricula sm ON sm.id = m.status_id
        WHERE m.id = 116
    ");
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "\n  Resultado: status={$r['status']} | prox_venc={$r['proxima_data_vencimento']} | data_venc={$r['data_vencimento']}\n";
} elseif (!$execute) {
    echo "\n  Use --execute para aplicar.\n";
}
