<?php
/**
 * Fix: sincronizar datas da matrícula com as parcelas reais.
 * - data_inicio = vencimento da 1ª parcela não-cancelada
 * - proxima_data_vencimento = vencimento da próxima parcela pendente/atrasada
 * - data_vencimento (acesso até) = proxima_data_vencimento ou MAX(venc) dos pagos
 *
 * Uso: php database/fix_matricula_datas_sync.php [--dry-run]
 */

$dryRun = in_array('--dry-run', $argv ?? []);

$db = require __DIR__ . '/../config/database.php';

echo "=== Sincronizar datas da matrícula com parcelas ===\n";
if ($dryRun) echo "⚠️  MODO DRY-RUN (sem alterações)\n";
echo "\n";

// Buscar todas as matrículas que possuem parcelas
$matriculas = $db->query("
    SELECT DISTINCT m.id, m.tenant_id, m.data_inicio, m.data_vencimento, m.proxima_data_vencimento
    FROM matriculas m
    INNER JOIN pagamentos_plano pp ON pp.matricula_id = m.id
    ORDER BY m.id
")->fetchAll(PDO::FETCH_ASSOC);

echo "Matrículas com parcelas: " . count($matriculas) . "\n\n";

$corrigidas = 0;

foreach ($matriculas as $mat) {
    $matId = (int) $mat['id'];
    $tenantId = (int) $mat['tenant_id'];

    // 1. data_inicio = MIN(data_vencimento) de parcelas não-canceladas
    $stmt = $db->prepare("
        SELECT MIN(data_vencimento) FROM pagamentos_plano
        WHERE tenant_id = ? AND matricula_id = ? AND status_pagamento_id != 4
    ");
    $stmt->execute([$tenantId, $matId]);
    $minVenc = $stmt->fetchColumn();

    // 2. proxima_data_vencimento = MIN(data_vencimento) de pendentes/atrasadas
    $stmt = $db->prepare("
        SELECT MIN(data_vencimento) FROM pagamentos_plano
        WHERE tenant_id = ? AND matricula_id = ? AND status_pagamento_id IN (1, 3)
    ");
    $stmt->execute([$tenantId, $matId]);
    $proxVenc = $stmt->fetchColumn();

    // 3. data_vencimento (acesso até) = proxVenc quando há pendente, senão MAX(venc) dos pagos
    $dataAcessoAte = $proxVenc;
    if (!$dataAcessoAte) {
        $stmt = $db->prepare("
            SELECT MAX(data_vencimento) FROM pagamentos_plano
            WHERE tenant_id = ? AND matricula_id = ? AND status_pagamento_id = 2
        ");
        $stmt->execute([$tenantId, $matId]);
        $dataAcessoAte = $stmt->fetchColumn();
    }

    // Verificar se algo mudou
    $changed = false;
    $changes = [];

    if ($minVenc && $minVenc !== $mat['data_inicio']) {
        $changes[] = "inicio: {$mat['data_inicio']} → {$minVenc}";
        $changed = true;
    }
    if ($dataAcessoAte && $dataAcessoAte !== $mat['data_vencimento']) {
        $changes[] = "acesso_ate: {$mat['data_vencimento']} → {$dataAcessoAte}";
        $changed = true;
    }
    if ($proxVenc !== $mat['proxima_data_vencimento']) {
        $changes[] = "prox_venc: {$mat['proxima_data_vencimento']} → " . ($proxVenc ?: 'NULL');
        $changed = true;
    }

    if ($changed) {
        echo "Matrícula #{$matId}: " . implode(' | ', $changes) . "\n";

        if (!$dryRun) {
            $sets = [];
            $params = [];

            if ($minVenc && $minVenc !== $mat['data_inicio']) {
                $sets[] = "data_inicio = ?";
                $params[] = $minVenc;
            }
            if ($dataAcessoAte && $dataAcessoAte !== $mat['data_vencimento']) {
                $sets[] = "data_vencimento = ?";
                $params[] = $dataAcessoAte;
            }
            if ($proxVenc !== $mat['proxima_data_vencimento']) {
                $sets[] = "proxima_data_vencimento = ?";
                $params[] = $proxVenc; // pode ser null
            }
            $sets[] = "updated_at = NOW()";

            $sql = "UPDATE matriculas SET " . implode(', ', $sets) . " WHERE id = ? AND tenant_id = ?";
            $params[] = $matId;
            $params[] = $tenantId;
            $db->prepare($sql)->execute($params);
        }

        $corrigidas++;
    }
}

echo "\n=== Resultado ===\n";
echo "Total matrículas: " . count($matriculas) . "\n";
echo "Corrigidas: {$corrigidas}\n";
if ($dryRun) echo "(nenhuma alteração feita — remova --dry-run para aplicar)\n";
