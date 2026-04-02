<?php
/**
 * Fix: sincronizar datas da matrícula com as parcelas reais.
 * - Matrículas COM assinatura (integração): usa datas da tabela assinaturas
 * - Matrículas SEM assinatura (manual): usa datas das parcelas
 *
 * Uso: php database/fix_matricula_datas_sync.php [--dry-run]
 */

$dryRun = in_array('--dry-run', $argv ?? []);

$db = require __DIR__ . '/../config/database.php';

echo "=== Sincronizar datas da matrícula com parcelas ===\n";
if ($dryRun) echo "⚠️  MODO DRY-RUN (sem alterações)\n";
echo "\n";

// =====================================================================
// 1. MATRÍCULAS COM ASSINATURA (integração) — usar tabela assinaturas
// =====================================================================
echo "--- Matrículas com assinatura (integração) ---\n\n";

$assinaturas = $db->query("
    SELECT a.matricula_id, a.tenant_id, a.data_inicio as ass_inicio, a.dia_cobranca, 
           a.proxima_cobranca, a.status_id as ass_status, a.data_fim as ass_fim,
           m.data_inicio as mat_inicio, m.data_vencimento as mat_venc, m.proxima_data_vencimento as mat_prox
    FROM assinaturas a
    INNER JOIN matriculas m ON m.id = a.matricula_id
    ORDER BY a.matricula_id
")->fetchAll(PDO::FETCH_ASSOC);

$matriculasComAssinatura = [];
$corrigidasAss = 0;

foreach ($assinaturas as $ass) {
    $matId = (int) $ass['matricula_id'];
    $tenantId = (int) $ass['tenant_id'];
    $matriculasComAssinatura[$matId] = true;

    $novoInicio = $ass['ass_inicio'];

    // Para assinatura ativa (status=1): proxima = próxima ocorrência do dia_cobranca
    // Para assinatura cancelada (status=6): proxima = NULL
    $novoProx = null;
    $novoAcessoAte = null;

    if ((int) $ass['ass_status'] === 1 && $ass['dia_cobranca']) {
        $dia = (int) $ass['dia_cobranca'];
        $hoje = new DateTime();
        $mesAtual = (int) $hoje->format('m');
        $anoAtual = (int) $hoje->format('Y');

        // Calcular próxima data de cobrança baseada no dia_cobranca
        $ultimoDiaMes = (int) (new DateTime("$anoAtual-$mesAtual-01"))->format('t');
        $diaReal = min($dia, $ultimoDiaMes);
        $proxData = new DateTime("$anoAtual-$mesAtual-$diaReal");

        // Se já passou esse dia no mês atual, vai pro próximo mês
        if ($proxData <= $hoje) {
            $proxData->modify('+1 month');
            $ultimoDiaProxMes = (int) $proxData->format('t');
            $proxData->setDate((int) $proxData->format('Y'), (int) $proxData->format('m'), min($dia, $ultimoDiaProxMes));
        }

        $novoProx = $proxData->format('Y-m-d');
        $novoAcessoAte = $novoProx;
    } else {
        // Cancelada: acesso até = data_fim da assinatura (período contratado)
        $novoAcessoAte = $ass['ass_fim'] ?: $novoInicio;
    }

    // Verificar se algo mudou
    $changed = false;
    $changes = [];

    if ($novoInicio !== $ass['mat_inicio']) {
        $changes[] = "inicio: {$ass['mat_inicio']} → {$novoInicio}";
        $changed = true;
    }
    if ($novoAcessoAte && $novoAcessoAte !== $ass['mat_venc']) {
        $changes[] = "acesso_ate: {$ass['mat_venc']} → {$novoAcessoAte}";
        $changed = true;
    }
    if ($novoProx !== $ass['mat_prox']) {
        $changes[] = "prox_venc: {$ass['mat_prox']} → " . ($novoProx ?: 'NULL');
        $changed = true;
    }

    if ($changed) {
        echo "Matrícula #{$matId} (assinatura): " . implode(' | ', $changes) . "\n";

        if (!$dryRun) {
            $sets = [];
            $params = [];

            if ($novoInicio !== $ass['mat_inicio']) {
                $sets[] = "data_inicio = ?";
                $params[] = $novoInicio;
            }
            if ($novoAcessoAte && $novoAcessoAte !== $ass['mat_venc']) {
                $sets[] = "data_vencimento = ?";
                $params[] = $novoAcessoAte;
            }
            if ($novoProx !== $ass['mat_prox']) {
                $sets[] = "proxima_data_vencimento = ?";
                $params[] = $novoProx;
            }
            $sets[] = "updated_at = NOW()";

            $sql = "UPDATE matriculas SET " . implode(', ', $sets) . " WHERE id = ? AND tenant_id = ?";
            $params[] = $matId;
            $params[] = $tenantId;
            $db->prepare($sql)->execute($params);
        }
        $corrigidasAss++;
    }
}

echo "\nAssinatura: {$corrigidasAss} corrigidas de " . count($assinaturas) . "\n\n";

// =====================================================================
// 2. MATRÍCULAS SEM ASSINATURA (manual) — usar parcelas
// =====================================================================
echo "--- Matrículas sem assinatura (manual) ---\n\n";

// Buscar todas as matrículas que possuem parcelas
$matriculas = $db->query("
    SELECT DISTINCT m.id, m.tenant_id, m.data_inicio, m.data_vencimento, m.proxima_data_vencimento
    FROM matriculas m
    INNER JOIN pagamentos_plano pp ON pp.matricula_id = m.id
    ORDER BY m.id
")->fetchAll(PDO::FETCH_ASSOC);

$corrigidas = 0;

foreach ($matriculas as $mat) {
    $matId = (int) $mat['id'];
    $tenantId = (int) $mat['tenant_id'];

    // PULAR matrículas com assinatura (já tratadas acima)
    if (isset($matriculasComAssinatura[$matId])) {
        continue;
    }

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
        echo "Matrícula #{$matId} (manual): " . implode(' | ', $changes) . "\n";

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
                $params[] = $proxVenc;
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

echo "\nManual: {$corrigidas} corrigidas\n";
echo "\n=== Resultado ===\n";
echo "Total assinatura: {$corrigidasAss}\n";
echo "Total manual: {$corrigidas}\n";
echo "Total: " . ($corrigidasAss + $corrigidas) . "\n";
if ($dryRun) echo "(nenhuma alteração feita — remova --dry-run para aplicar)\n";
