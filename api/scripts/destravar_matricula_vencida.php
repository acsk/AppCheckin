<?php
/**
 * Destrava matrícula vencida quando o PIX já quitou outra parcela (duplicata em aberto).
 *
 * Uso:
 *   php scripts/destravar_matricula_vencida.php --matricula-id=49 --tenant=3
 *   php scripts/destravar_matricula_vencida.php --matricula-id=49 --tenant=3 --dry-run
 *   php scripts/destravar_matricula_vencida.php --matricula-id=49 --tenant=3 --parcela-id=576
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

date_default_timezone_set('America/Sao_Paulo');

$opts = getopt('', ['matricula-id:', 'tenant:', 'parcela-id:', 'dry-run', 'quiet']);
$matriculaId = isset($opts['matricula-id']) ? (int) $opts['matricula-id'] : 0;
$tenantId = isset($opts['tenant']) ? (int) $opts['tenant'] : 0;
$parcelaForcada = isset($opts['parcela-id']) ? (int) $opts['parcela-id'] : 0;
$dryRun = isset($opts['dry-run']);
$quiet = isset($opts['quiet']);

if ($matriculaId <= 0 || $tenantId <= 0) {
    fwrite(STDERR, "Uso: php scripts/destravar_matricula_vencida.php --matricula-id=N --tenant=N [--parcela-id=N] [--dry-run]\n");
    exit(1);
}

$pdo = require __DIR__ . '/../config/database.php';

$say = static function (string $msg) use ($quiet): void {
    if (!$quiet) {
        echo $msg . PHP_EOL;
    }
};

$say("=== Destravar matrícula #{$matriculaId} (tenant {$tenantId}) ===");
$say(date('Y-m-d H:i:s'));

$stmtMat = $pdo->prepare("
    SELECT m.id, m.tenant_id, m.data_vencimento, m.proxima_data_vencimento,
           sm.codigo AS status_codigo, u.nome AS aluno
    FROM matriculas m
    INNER JOIN status_matricula sm ON sm.id = m.status_id
    INNER JOIN alunos a ON a.id = m.aluno_id
    INNER JOIN usuarios u ON u.id = a.usuario_id
    WHERE m.id = ? AND m.tenant_id = ?
");
$stmtMat->execute([$matriculaId, $tenantId]);
$mat = $stmtMat->fetch(PDO::FETCH_ASSOC);

if (!$mat) {
    fwrite(STDERR, "Matrícula não encontrada.\n");
    exit(1);
}

$say("Aluno: {$mat['aluno']}");
$say("Status atual: {$mat['status_codigo']}");
$say("data_vencimento: {$mat['data_vencimento']} | proxima: " . ($mat['proxima_data_vencimento'] ?? '-'));

$stmtAbertas = $pdo->prepare("
    SELECT pp.id, pp.valor, pp.data_vencimento, pp.status_pagamento_id, sp.nome AS status_nome,
           DATEDIFF(CURDATE(), pp.data_vencimento) AS dias_atraso
    FROM pagamentos_plano pp
    INNER JOIN status_pagamento sp ON sp.id = pp.status_pagamento_id
    WHERE pp.matricula_id = ? AND pp.tenant_id = ?
      AND pp.status_pagamento_id IN (1, 3)
      AND pp.data_pagamento IS NULL
    ORDER BY pp.data_vencimento ASC
");
$stmtAbertas->execute([$matriculaId, $tenantId]);
$abertas = $stmtAbertas->fetchAll(PDO::FETCH_ASSOC);

if ($abertas === []) {
    $say('Nenhuma parcela aberta (1/3). Só recalculando status e datas...');
} else {
    $say('Parcelas abertas: ' . count($abertas));
    foreach ($abertas as $p) {
        $say(sprintf(
            '  #%s | %s | R$ %.2f | venc %s | atraso %s dia(s)',
            $p['id'],
            $p['status_nome'],
            $p['valor'],
            $p['data_vencimento'],
            $p['dias_atraso']
        ));
    }
}

$stmtPagoRecente = $pdo->prepare("
    SELECT id, valor, data_pagamento, observacoes
    FROM pagamentos_plano
    WHERE matricula_id = ? AND tenant_id = ?
      AND status_pagamento_id = 2
      AND data_pagamento IS NOT NULL
    ORDER BY data_pagamento DESC
    LIMIT 5
");

$cancelar = [];

if ($parcelaForcada > 0) {
    $cancelar[] = $parcelaForcada;
    $say("Modo: cancelar parcela informada #{$parcelaForcada}");
} else {
    $stmtPagoRecente->execute([$matriculaId, $tenantId]);
    $pagos = $stmtPagoRecente->fetchAll(PDO::FETCH_ASSOC);

    foreach ($abertas as $aberta) {
        $idAberta = (int) $aberta['id'];
        $valorAberta = (float) $aberta['valor'];
        $vencAberta = $aberta['data_vencimento'];

        foreach ($pagos as $pago) {
            if (abs((float) $pago['valor'] - $valorAberta) > 0.01) {
                continue;
            }
            $dataPago = $pago['data_pagamento'];
            // PIX já quitou ciclo igual: parcela aberta é duplicata
            if ($dataPago >= date('Y-m-d', strtotime($vencAberta . ' -45 days'))) {
                $cancelar[] = $idAberta;
                $say("→ Duplicata: aberta #{$idAberta} vs paga #{$pago['id']} em {$dataPago}");
                break;
            }
        }
    }
}

$cancelar = array_values(array_unique($cancelar));

if ($cancelar === [] && $abertas !== []) {
    $say('');
    $say('Nenhuma duplicata automática detectada. Cancele manualmente:');
    $say("  php jobs/cancelar_parcela_plano.php --parcela-id=ID --tenant={$tenantId}");
    exit(1);
}

$motivo = 'Duplicata: PIX já quitou outra parcela [destravar_matricula_vencida.php]';

if (!$dryRun && $cancelar !== []) {
    $pdo->beginTransaction();
    try {
        foreach ($cancelar as $pid) {
            $stmtUp = $pdo->prepare("
                UPDATE pagamentos_plano
                SET status_pagamento_id = 4,
                    data_pagamento = NULL,
                    observacoes = CONCAT(COALESCE(observacoes, ''), ' | ', ?),
                    updated_at = NOW()
                WHERE id = ? AND matricula_id = ? AND tenant_id = ?
                  AND status_pagamento_id IN (1, 3)
            ");
            $stmtUp->execute([$motivo, $pid, $matriculaId, $tenantId]);
            $say($stmtUp->rowCount() > 0
                ? "✅ Parcela #{$pid} cancelada"
                : "⚠️  Parcela #{$pid} não atualizada (já cancelada/paga?)");
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        fwrite(STDERR, '❌ ' . $e->getMessage() . PHP_EOL);
        exit(1);
    }
} elseif ($dryRun && $cancelar !== []) {
    foreach ($cancelar as $pid) {
        $say("[dry-run] Cancelaria parcela #{$pid}");
    }
}

if (!$dryRun) {
    $model = new \App\Models\PagamentoPlano($pdo);
    $model->atualizarStatusMatricula($tenantId, $matriculaId);

    $stmtRegra = $pdo->prepare("
        SELECT
            SUM(CASE WHEN status_pagamento_id IN (1, 3) THEN 1 ELSE 0 END) AS pendentes,
            MAX(CASE WHEN status_pagamento_id IN (1, 3) AND data_vencimento < CURDATE()
                THEN DATEDIFF(CURDATE(), data_vencimento) ELSE 0 END) AS dias_atraso
        FROM pagamentos_plano WHERE matricula_id = ? AND tenant_id = ?
    ");
    $stmtRegra->execute([$matriculaId, $tenantId]);
    $regra = $stmtRegra->fetch(PDO::FETCH_ASSOC);
    $pendentes = (int) ($regra['pendentes'] ?? 0);
    $diasAtraso = (int) ($regra['dias_atraso'] ?? 0);

    $stmtStatusAtiva = $pdo->query("SELECT id FROM status_matricula WHERE codigo = 'ativa' LIMIT 1");
    $statusAtivaId = (int) ($stmtStatusAtiva->fetchColumn() ?: 0);

    // Sem pendente atrasada: alinhar proxima_data e forçar ativa se data_vencimento ainda válida
    if ($pendentes === 0 || $diasAtraso === 0) {
        $dataVenc = $mat['data_vencimento'];
        $proxima = $dataVenc;
        if ($pendentes > 0) {
            $stmtMin = $pdo->prepare("
                SELECT MIN(data_vencimento) FROM pagamentos_plano
                WHERE matricula_id = ? AND status_pagamento_id IN (1, 3)
            ");
            $stmtMin->execute([$matriculaId]);
            $proxima = $stmtMin->fetchColumn() ?: $dataVenc;
        }

        if ($statusAtivaId > 0 && $dataVenc >= date('Y-m-d')) {
            $pdo->prepare("
                UPDATE matriculas
                SET status_id = ?,
                    proxima_data_vencimento = ?,
                    updated_at = NOW()
                WHERE id = ? AND tenant_id = ?
            ")->execute([$statusAtivaId, $proxima, $matriculaId, $tenantId]);
            $say("✅ Matrícula forçada para ATIVA (acesso até {$dataVenc}, próx. {$proxima})");
        }
    }
}

$stmtFinal = $pdo->prepare("
    SELECT sm.codigo, m.data_vencimento, m.proxima_data_vencimento
    FROM matriculas m
    INNER JOIN status_matricula sm ON sm.id = m.status_id
    WHERE m.id = ?
");
$stmtFinal->execute([$matriculaId]);
$f = $stmtFinal->fetch(PDO::FETCH_ASSOC);

$say('');
$say('--- Resultado ---');
$say('Status: ' . ($f['codigo'] ?? '?'));
$say('data_vencimento: ' . ($f['data_vencimento'] ?? '-'));
$say('proxima_data_vencimento: ' . ($f['proxima_data_vencimento'] ?? '-'));
$say('');
$say('Validar: php debug_matricula_status.php ' . $matriculaId . ' --tenant=' . $tenantId);
