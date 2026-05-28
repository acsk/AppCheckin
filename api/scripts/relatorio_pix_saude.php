<?php
/**
 * Relatório copiável: PIX sem baixa, webhooks, job PIX, logs PHP.
 * Cole a saída no chat (Cursor) para diagnóstico.
 *
 * Uso (produção):
 *   php scripts/relatorio_pix_saude.php --tenant=3
 *   php scripts/relatorio_pix_saude.php --tenant=3 --days=7
 *   php scripts/relatorio_pix_saude.php --payment-id=161301089356 --tenant=3
 *   php scripts/relatorio_pix_saude.php --tenant=3 --fix   # tenta job PIX nos pendentes (dry-run se --dry-run)
 *
 * Saída entre marcadores === RELATORIO PIX === (copie tudo).
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

date_default_timezone_set('America/Sao_Paulo');

$opts = getopt('', [
    'tenant:',
    'days:',
    'payment-id:',
    'matricula-id:',
    'fix',
    'dry-run',
    'json',
    'php-log:',
    'quiet',
]);

$tenantId = isset($opts['tenant']) ? (int) $opts['tenant'] : 0;
$days = isset($opts['days']) ? max(1, (int) $opts['days']) : 7;
$paymentFilter = isset($opts['payment-id']) ? preg_replace('/\D/', '', (string) $opts['payment-id']) : '';
$matriculaFilter = isset($opts['matricula-id']) ? (int) $opts['matricula-id'] : 0;
$runFix = isset($opts['fix']);
$dryRun = isset($opts['dry-run']);
$asJson = isset($opts['json']);
$phpLogPath = $opts['php-log'] ?? null;
$quiet = isset($opts['quiet']);

$pdo = require __DIR__ . '/../config/database.php';
$root = dirname(__DIR__);
$jobLog = $root . '/storage/logs/atualizar_pagamentos_mp.log';

$report = [
    'gerado_em' => date('Y-m-d H:i:s'),
    'servidor' => gethostname() ?: 'unknown',
    'tenant' => $tenantId ?: null,
    'janela_dias' => $days,
    'pix_sem_baixa' => [],
    'webhooks_recentes' => [],
    'job_log_tail' => [],
    'php_log_webhook' => [],
    'payment_detalhe' => null,
    'acoes_fix' => [],
];

$out = static function (string $line) use ($quiet): void {
    if (!$quiet) {
        echo $line . "\n";
    }
};

$section = static function (string $title) use ($out): void {
    $out('');
    $out('--- ' . $title . ' ---');
};

if (!$asJson) {
    $out('=== RELATORIO PIX INICIO ===');
    $out('Cole este bloco inteiro no chat (incluindo INICIO/FIM).');
    $out('Gerado: ' . $report['gerado_em'] . ' | Host: ' . $report['servidor']);
}

// ── 1. PIX em pagamentos_pix sem baixa em pagamentos_plano ─────────────────
$pixSemBaixa = [];
if ($pdo->query("SHOW TABLES LIKE 'pagamentos_pix'")->rowCount() > 0) {
    $sql = "
        SELECT px.payment_id, px.matricula_id, px.tenant_id, px.status AS pix_status,
               px.created_at, u.nome AS aluno_nome,
               m.valor AS matricula_valor, sm.codigo AS matricula_status
        FROM pagamentos_pix px
        INNER JOIN matriculas m ON m.id = px.matricula_id
        INNER JOIN alunos a ON a.id = m.aluno_id
        INNER JOIN usuarios u ON u.id = a.usuario_id
        INNER JOIN status_matricula sm ON sm.id = m.status_id
        WHERE px.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
          AND px.payment_id IS NOT NULL AND TRIM(px.payment_id) != ''
    ";
    $params = [$days];
    if ($tenantId > 0) {
        $sql .= ' AND px.tenant_id = ?';
        $params[] = $tenantId;
    }
    if ($matriculaFilter > 0) {
        $sql .= ' AND px.matricula_id = ?';
        $params[] = $matriculaFilter;
    }
    if ($paymentFilter !== '') {
        $sql .= ' AND px.payment_id = ?';
        $params[] = $paymentFilter;
    }
    $sql .= ' GROUP BY px.payment_id, px.matricula_id, px.tenant_id ORDER BY px.created_at DESC LIMIT 30';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $stmtBaixa = $pdo->prepare("
        SELECT id, valor, data_pagamento, observacoes
        FROM pagamentos_plano
        WHERE matricula_id = ?
          AND status_pagamento_id = 2
          AND (observacoes LIKE ? OR observacoes LIKE ?)
        LIMIT 1
    ");

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $pid = preg_replace('/\D/', '', (string) $row['payment_id']);
        $mid = (int) $row['matricula_id'];
        $stmtBaixa->execute([$mid, "%ID: {$pid}%", "%Payment #{$pid}%"]);
        $baixa = $stmtBaixa->fetch(PDO::FETCH_ASSOC);

        if ($baixa) {
            continue;
        }

        $espelho = null;
        $stPm = $pdo->prepare('SELECT status, date_approved FROM pagamentos_mercadopago WHERE payment_id = ? LIMIT 1');
        $stPm->execute([$pid]);
        $espelho = $stPm->fetch(PDO::FETCH_ASSOC) ?: null;

        $whCount = 0;
        if ($pdo->query("SHOW TABLES LIKE 'webhook_payloads_mercadopago'")->rowCount() > 0) {
            $stWh = $pdo->prepare('SELECT COUNT(*) FROM webhook_payloads_mercadopago WHERE payment_id = ? OR payload LIKE ?');
            $stWh->execute([$pid, '%' . $pid . '%']);
            $whCount = (int) $stWh->fetchColumn();
        }

        $item = [
            'payment_id' => $pid,
            'matricula_id' => $mid,
            'tenant_id' => (int) $row['tenant_id'],
            'aluno' => $row['aluno_nome'],
            'valor_matricula' => (float) $row['matricula_valor'],
            'status_matricula' => $row['matricula_status'],
            'pix_criado' => $row['created_at'],
            'espelho_mp' => $espelho ? ($espelho['status'] . ' @ ' . ($espelho['date_approved'] ?? '-')) : 'AUSENTE',
            'webhooks_salvos' => $whCount,
        ];
        $pixSemBaixa[] = $item;
    }
}
$report['pix_sem_baixa'] = $pixSemBaixa;

if (!$asJson) {
    $section('PIX sem baixa (pagamentos_pix → pagamentos_plano)');
    if ($pixSemBaixa === []) {
        $out('(nenhum na janela — ou todos já baixados)');
    } else {
        foreach ($pixSemBaixa as $i => $p) {
            $out(sprintf(
                '%d) payment=%s | mat=%s | %s | R$ %.2f | status=%s | espelho=%s | webhooks=%d | pix_em=%s',
                $i + 1,
                $p['payment_id'],
                $p['matricula_id'],
                $p['aluno'],
                $p['valor_matricula'],
                $p['status_matricula'],
                $p['espelho_mp'],
                $p['webhooks_salvos'],
                $p['pix_criado']
            ));
        }
    }
}

// ── 2. Detalhe de um payment (--payment-id) ───────────────────────────────
if ($paymentFilter !== '' && $tenantId > 0) {
    $detail = ['payment_id' => $paymentFilter, 'mp' => null, 'erro_mp' => null];
    try {
        $mp = new \App\Services\MercadoPagoService($tenantId);
        $detail['mp'] = $mp->buscarPagamento($paymentFilter);
    } catch (Throwable $e) {
        $detail['erro_mp'] = $e->getMessage();
    }
    $report['payment_detalhe'] = $detail;

    if (!$asJson) {
        $section('MP live (payment ' . $paymentFilter . ')');
        if ($detail['erro_mp']) {
            $out('ERRO: ' . $detail['erro_mp']);
        } elseif (is_array($detail['mp'])) {
            $m = $detail['mp'];
            $out('status=' . ($m['status'] ?? '?') . ' | valor=' . ($m['transaction_amount'] ?? '?'));
            $out('external_reference=' . ($m['external_reference'] ?? '-'));
            $meta = is_array($m['metadata'] ?? null) ? $m['metadata'] : [];
            $out('metadata.matricula_id=' . ($meta['matricula_id'] ?? '-'));
            $out('date_approved=' . ($m['date_approved'] ?? '-'));
            $method = strtolower((string) ($m['payment_method_id'] ?? ''));
            $out('payment_method_id=' . ($m['payment_method_id'] ?? '-') . ($method === 'pix' || str_contains($method, 'pix') ? ' (PIX)' : ' (NAO-PIX)'));
        }
    }
}

// ── 3. Webhooks recentes ──────────────────────────────────────────────────
if ($pdo->query("SHOW TABLES LIKE 'webhook_payloads_mercadopago'")->rowCount() > 0) {
    $sqlWh = "
        SELECT id, created_at, tipo, status, payment_id, external_reference,
               LEFT(erro_processamento, 120) AS erro_curto
        FROM webhook_payloads_mercadopago
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
    ";
    $pWh = [$days];
    if ($tenantId > 0) {
        $sqlWh .= ' AND tenant_id = ?';
        $pWh[] = $tenantId;
    }
    if ($paymentFilter !== '') {
        $sqlWh .= ' AND (payment_id = ? OR payload LIKE ?)';
        $pWh[] = $paymentFilter;
        $pWh[] = '%' . $paymentFilter . '%';
    }
    $sqlWh .= ' ORDER BY id DESC LIMIT 15';

    $stmtWh = $pdo->prepare($sqlWh);
    $stmtWh->execute($pWh);
    $report['webhooks_recentes'] = $stmtWh->fetchAll(PDO::FETCH_ASSOC);

    if (!$asJson) {
        $section('Webhooks salvos (últimos ' . $days . ' dias)');
        if ($report['webhooks_recentes'] === []) {
            $out('(nenhum — MP pode não estar notificando este servidor)');
        } else {
            foreach ($report['webhooks_recentes'] as $w) {
                $out(sprintf(
                    '#%s %s | %s | %s | payment=%s | %s',
                    $w['id'],
                    $w['created_at'],
                    $w['tipo'],
                    $w['status'],
                    $w['payment_id'] ?? '-',
                    $w['erro_curto'] ?? ''
                ));
            }
        }
    }
}

// ── 4. Tail do job PIX ────────────────────────────────────────────────────
if (is_readable($jobLog)) {
    $lines = file($jobLog, FILE_IGNORE_NEW_LINES) ?: [];
    $report['job_log_tail'] = array_slice($lines, -40);
    if (!$asJson) {
        $section('Job atualizar_pagamentos_mp (últimas 40 linhas)');
        foreach ($report['job_log_tail'] as $line) {
            $out($line);
        }
    }
} elseif (!$asJson) {
    $section('Job log');
    $out('(arquivo não encontrado: storage/logs/atualizar_pagamentos_mp.log)');
}

// ── 5. PHP error_log com [Webhook MP] ─────────────────────────────────────
$candidateLogs = array_filter([
    $phpLogPath,
    '/home/u304177849/logs/appcheckin_com_br.php.error.log',
    '/home/u304177849/domains/appcheckin.com.br/logs/error.log',
    $root . '/storage/logs/php-error.log',
    ini_get('error_log') ?: null,
]);

foreach ($candidateLogs as $logPath) {
    if (!is_string($logPath) || $logPath === '' || !is_readable($logPath)) {
        continue;
    }
    $all = @file($logPath, FILE_IGNORE_NEW_LINES);
    if (!is_array($all)) {
        continue;
    }
    $filtered = [];
    foreach (array_slice($all, -500) as $line) {
        if (stripos($line, '[Webhook MP]') !== false
            || stripos($line, 'atualizar_pagamentos_mp') !== false
            || ($paymentFilter !== '' && str_contains($line, $paymentFilter))
        ) {
            $filtered[] = $line;
        }
    }
    $report['php_log_webhook'] = array_slice($filtered, -25);
    if (!$asJson && $report['php_log_webhook'] !== []) {
        $section('PHP error_log (Webhook MP / job) — ' . basename($logPath));
        foreach ($report['php_log_webhook'] as $line) {
            $out(mb_substr($line, 0, 300));
        }
    }
    break;
}

if (!$asJson && $report['php_log_webhook'] === []) {
    $section('PHP error_log');
    $out('Não encontrado automaticamente. Use: --php-log=/caminho/para/error.log');
    $out('Hostinger: painel → Logs → PHP error log do domínio api.appcheckin.com.br');
}

// ── 6. --fix opcional ─────────────────────────────────────────────────────
if ($runFix && $pixSemBaixa !== []) {
    $jobScript = $root . '/jobs/atualizar_pagamentos_mp.php';
    $phpBin = PHP_BINARY ?: 'php';
    foreach ($pixSemBaixa as $p) {
        $cmd = sprintf(
            '%s %s --payment-id=%s --tenant=%d%s 2>&1',
            escapeshellarg($phpBin),
            escapeshellarg($jobScript),
            $p['payment_id'],
            (int) $p['tenant_id'],
            $dryRun ? ' --dry-run' : ''
        );
        $report['acoes_fix'][] = ['cmd' => $cmd, 'output' => shell_exec($cmd) ?? ''];
    }
    if (!$asJson) {
        $section('--fix (job PIX por pendência)');
        foreach ($report['acoes_fix'] as $a) {
            $out('$ ' . $a['cmd']);
            $out(trim((string) $a['output']));
        }
    }
}

// ── Comandos úteis ────────────────────────────────────────────────────────
if (!$asJson) {
    $section('Comandos para você / para colar no chat depois');
    $out('php scripts/relatorio_pix_saude.php --tenant=3 --days=7');
    $out('php debug_pagamento_mp.php <payment_id> --mp --tenant=3');
    $out('php jobs/atualizar_pagamentos_mp.php --payment-id=<id> --tenant=3');
    $out('php database/show_webhook_payload.php payment <payment_id>');
    $out('');
    $out('=== RELATORIO PIX FIM ===');
}

if ($asJson) {
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
}

$dir = $root . '/storage/logs';
if (is_dir($dir) && is_writable($dir) && !$quiet && !$asJson) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $file = $dir . '/relatorio_pix_' . date('Ymd_His') . '.json';
    file_put_contents($file, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    $out('');
    $out('(Cópia salva: ' . $file . ')');
}
