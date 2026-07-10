<?php
/**
 * Restaura status (e opcionalmente datas) de matrículas a partir de um snapshot CSV
 * extraído do dump appchekin.sql (09/07 ~22:59), sem restaurar o banco inteiro.
 *
 * Só altera matrículas que no backup eram ativa/vencida/pendente e hoje estão cancelada.
 *
 * Uso:
 *   php scripts/restaurar_status_matriculas_do_bkp.php --csv=/caminho/matriculas_bkp.csv --dry-run
 *   php scripts/restaurar_status_matriculas_do_bkp.php --csv=/caminho/matriculas_bkp.csv
 *   php scripts/restaurar_status_matriculas_do_bkp.php --csv=... --restaurar-datas
 *   php scripts/restaurar_status_matriculas_do_bkp.php --csv=... --alinhar-max-pago
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

date_default_timezone_set('America/Sao_Paulo');

$opts = getopt('', ['csv:', 'dry-run', 'restaurar-datas', 'alinhar-max-pago', 'quiet', 'tenant:']);
$csvPath = $opts['csv'] ?? '';
$dryRun = isset($opts['dry-run']);
$restaurarDatas = isset($opts['restaurar-datas']);
$alinharMaxPago = isset($opts['alinhar-max-pago']);
$quiet = isset($opts['quiet']);
$tenantFilter = isset($opts['tenant']) ? (int) $opts['tenant'] : null;

if ($csvPath === '' || !is_readable($csvPath)) {
    fwrite(STDERR, "Uso: php scripts/restaurar_status_matriculas_do_bkp.php --csv=/caminho/arquivo.csv [--dry-run] [--restaurar-datas] [--alinhar-max-pago]\n");
    exit(1);
}

if ($restaurarDatas && $alinharMaxPago) {
    fwrite(STDERR, "Escolha só um: --restaurar-datas OU --alinhar-max-pago\n");
    exit(1);
}

$pdo = require __DIR__ . '/../config/database.php';

$say = static function (string $msg) use ($quiet): void {
    if (!$quiet) {
        echo $msg . PHP_EOL;
    }
};

$say('=== Restaurar status de matrículas a partir do backup ===');
$say(date('Y-m-d H:i:s'));
$say("CSV: {$csvPath}");
if ($dryRun) {
    $say('MODO DRY-RUN — nenhuma alteração será feita');
}
if ($restaurarDatas) {
    $say('Datas: restaurar data_vencimento/proxima do backup');
} elseif ($alinharMaxPago) {
    $say('Datas: alinhar ao MAX(parcela paga) após restaurar status');
} else {
    $say('Datas: manter as atuais (só status)');
}

$fh = fopen($csvPath, 'r');
if ($fh === false) {
    fwrite(STDERR, "Não foi possível abrir o CSV.\n");
    exit(1);
}

$header = fgetcsv($fh);
if ($header === false) {
    fwrite(STDERR, "CSV vazio.\n");
    exit(1);
}

$bkpKey = static fn (int $id, int $tenantId): string => $id . ':' . $tenantId;

$bkp = [];
while (($row = fgetcsv($fh)) !== false) {
    $item = array_combine($header, $row);
    if ($item === false) {
        continue;
    }
    $id = (int) $item['id'];
    $tenantId = (int) $item['tenant_id'];
    if ($tenantFilter !== null && $tenantId !== $tenantFilter) {
        continue;
    }
    $bkp[$bkpKey($id, $tenantId)] = $item;
}
fclose($fh);

$say('Linhas no snapshot: ' . count($bkp));

$statusIds = [];
$stmtStatus = $pdo->query('SELECT id, codigo FROM status_matricula');
foreach ($stmtStatus->fetchAll(PDO::FETCH_ASSOC) as $s) {
    $statusIds[$s['codigo']] = (int) $s['id'];
}

$candidatas = [];
$sqlAtual = "
    SELECT m.id, m.tenant_id, m.data_vencimento, m.proxima_data_vencimento, sm.codigo AS status_atual
    FROM matriculas m
    INNER JOIN status_matricula sm ON sm.id = m.status_id
";
$paramsAtual = [];
if ($tenantFilter !== null) {
    $sqlAtual .= ' WHERE m.tenant_id = :tenant_id';
    $paramsAtual['tenant_id'] = $tenantFilter;
}
$stmtAtual = $pdo->prepare($sqlAtual);
$stmtAtual->execute($paramsAtual);
foreach ($stmtAtual->fetchAll(PDO::FETCH_ASSOC) as $atual) {
    $id = (int) $atual['id'];
    $tenantId = (int) $atual['tenant_id'];
    $key = $bkpKey($id, $tenantId);
    if (!isset($bkp[$key])) {
        continue;
    }
    $snap = $bkp[$key];
    $statusBkp = $snap['status_codigo'] ?? '';
    if (!in_array($statusBkp, ['ativa', 'vencida', 'pendente'], true)) {
        continue;
    }
    if ($atual['status_atual'] !== 'cancelada') {
        continue;
    }
    $candidatas[] = [
        'id' => $id,
        'tenant_id' => $tenantId,
        'status_bkp' => $statusBkp,
        'status_atual' => $atual['status_atual'],
        'dv_bkp' => $snap['data_vencimento'] ?: null,
        'prox_bkp' => $snap['proxima_data_vencimento'] ?: null,
        'dv_atual' => $atual['data_vencimento'],
        'prox_atual' => $atual['proxima_data_vencimento'],
    ];
}

$say('Candidatas a restaurar: ' . count($candidatas));
$contagem = ['ativa' => 0, 'vencida' => 0, 'pendente' => 0];
foreach ($candidatas as $c) {
    $contagem[$c['status_bkp']]++;
    $say(sprintf(
        '  #%d | cancelada → %s | dv_bkp=%s prox_bkp=%s | dv_atual=%s prox_atual=%s',
        $c['id'],
        $c['status_bkp'],
        $c['dv_bkp'] ?? '-',
        $c['prox_bkp'] ?? '-',
        $c['dv_atual'] ?? '-',
        $c['prox_atual'] ?? '-'
    ));
}
$say(sprintf(
    'Resumo: %d → ativa, %d → vencida, %d → pendente',
    $contagem['ativa'],
    $contagem['vencida'],
    $contagem['pendente']
));

if ($dryRun || $candidatas === []) {
    exit(0);
}

$pdo->beginTransaction();
try {
    $upd = $pdo->prepare('
        UPDATE matriculas
        SET status_id = :status_id,
            data_vencimento = COALESCE(:data_vencimento, data_vencimento),
            proxima_data_vencimento = COALESCE(:proxima, proxima_data_vencimento),
            updated_at = NOW()
        WHERE id = :id AND tenant_id = :tenant_id
    ');

    $atualizadas = 0;
    foreach ($candidatas as $c) {
        $statusId = $statusIds[$c['status_bkp']] ?? null;
        if ($statusId === null) {
            throw new RuntimeException("Status desconhecido: {$c['status_bkp']}");
        }

        $dv = null;
        $prox = null;
        if ($restaurarDatas) {
            $dv = $c['dv_bkp'];
            $prox = $c['prox_bkp'];
        } elseif ($alinharMaxPago) {
            $stmtMax = $pdo->prepare("
                SELECT MAX(data_vencimento)
                FROM pagamentos_plano
                WHERE matricula_id = ? AND tenant_id = ? AND status_pagamento_id = 2
            ");
            $stmtMax->execute([$c['id'], $c['tenant_id']]);
            $maxPago = $stmtMax->fetchColumn();
            if ($maxPago) {
                $dv = $maxPago;
                $prox = $maxPago;
            }
        }

        $upd->execute([
            'status_id' => $statusId,
            'data_vencimento' => $dv,
            'proxima' => $prox,
            'id' => $c['id'],
            'tenant_id' => $c['tenant_id'],
        ]);
        $atualizadas += $upd->rowCount();
    }

    $pdo->commit();
    $say("✅ Restauradas: {$atualizadas}");
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, 'ERRO: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
