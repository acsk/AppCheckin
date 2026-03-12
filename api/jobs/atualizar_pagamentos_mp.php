<?php
/**
 * Job: Reprocessar pagamentos do dia corrente para assinaturas criadas hoje
 *
 * Regras:
 * - Seleciona registros da tabela `assinaturas` onde DATE(criado_em) = CURDATE()
 * - Para cada assinatura encontrada, busca pagamentos em `pagamentos_mercadopago`
 *   onde `matricula_id` coincide, DATE(date_created) = CURDATE() e status != 'approved'
 * - Para cada pagamento, chama endpoint de reprocessamento:
 *   POST https://api.appcheckin.com.br/api/webhooks/mercadopago/payment/{payment_id}/reprocess
 *
 * Uso:
 *  php jobs/reprocess_today_payments.php [--dry-run]
 */

define('LOCK_FILE', '/tmp/atualizar_pagamentos_mp.lock');
define('MAX_EXECUTION_TIME', 300);

$options = getopt('', ['dry-run', 'quiet', 'tenant:','verbose']);
$dryRun = isset($options['dry-run']);
$quiet = isset($options['quiet']);
$tenantId = isset($options['tenant']) ? (int)$options['tenant'] : null;
$verbose = isset($options['verbose']);

$root = dirname(__DIR__);
$logFile = $root . '/storage/logs/atualizar_pagamentos_mp.log';

function logMsg($message, $isQuiet = false, $toFile = true) {
    global $logFile, $verbose;
    $ts = date('Y-m-d H:i:s');
    $line = "[$ts] $message";
    if (!$isQuiet) {
        echo $line . "\n";
    }
    if ($toFile) file_put_contents($logFile, $line . "\n", FILE_APPEND);
}

// Lock
if (file_exists(LOCK_FILE)) {
    $lockTime = filemtime(LOCK_FILE);
    if (time() - $lockTime > 600) {
        unlink(LOCK_FILE);
        logMsg("⚠️  Lock antigo removido", $quiet);
    } else {
        logMsg("❌ Job já está em execução (lock ativo)", $quiet);
        exit(1);
    }
}
file_put_contents(LOCK_FILE, getmypid());
set_time_limit(MAX_EXECUTION_TIME);

try {
    logMsg("🚀 Iniciando job: atualizar_pagamentos_mp" . ($dryRun ? ' (dry-run)' : ''), $quiet);

    // Conectar DB
    require_once __DIR__ . '/../config/database.php';
    if (!isset($pdo) || !$pdo) {
        // config/database.php retorna PDO, capture em $pdo
        $pdo = require __DIR__ . '/../config/database.php';
    }
    if (!isset($pdo)) throw new Exception('Erro ao conectar ao banco');

    $sqlAss = "SELECT id, tenant_id, matricula_id, gateway_id, gateway_assinatura_id, criado_em FROM assinaturas WHERE DATE(criado_em) = CURDATE()";
    if ($tenantId) {
        $sqlAss .= " AND tenant_id = ?";
        $stmt = $pdo->prepare($sqlAss);
        $stmt->execute([$tenantId]);
    } else {
        $stmt = $pdo->prepare($sqlAss);
        $stmt->execute();
    }
    $assinaturas = $stmt->fetchAll(PDO::FETCH_ASSOC) ?? [];
    logMsg('Assinaturas encontradas: ' . count($assinaturas), $quiet);

    foreach ($assinaturas as $ass) {
        $matriculaId = $ass['matricula_id'];
        $assinaturaId = $ass['id'];

        if (empty($matriculaId)) {
            logMsg("Assinatura {$assinaturaId} sem matricula_id — pulando", $quiet);
            continue;
        }

        $sqlP = "SELECT id, payment_id, status_id, status, status_detail, date_created FROM pagamentos_mercadopago WHERE matricula_id = ? AND DATE(date_created) = CURDATE() AND (status_id IS NULL OR status_id <> 6)";
        $stmtP = $pdo->prepare($sqlP);
        $stmtP->execute([$matriculaId]);
        $pagamentos = $stmtP->fetchAll(PDO::FETCH_ASSOC) ?? [];

        logMsg("Assinatura {$assinaturaId} (matricula {$matriculaId}) - pagamentos a reprocessar: " . count($pagamentos), $quiet);

        foreach ($pagamentos as $pg) {
            $paymentId = $pg['payment_id'] ?? null;
            $statusId = isset($pg['status_id']) ? (int)$pg['status_id'] : null;
            if (empty($paymentId)) {
                logMsg("Pagamento registro {$pg['id']} sem payment_id — pulando", $quiet);
                continue;
            }

            // Só reprocessar se status_id != 6 (ou NULL)
            if ($statusId === 6) {
                logMsg("Pagamento {$pg['id']} (payment_id={$paymentId}) possui status_id=6 — pulando", $quiet);
                continue;
            }

            $url = "https://api.appcheckin.com.br/api/webhooks/mercadopago/payment/{$paymentId}/reprocess";

            if ($dryRun) {
                logMsg("[dry-run] POST {$url}", $quiet);
                continue;
            }

            // Fazer requisição POST (sem payload) com timeout
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json'
            ]);

            $resp = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_close($ch);

            if ($err) {
                logMsg("Erro ao chamar reprocess para payment {$paymentId}: {$err}", $quiet);
            } else {
                logMsg("Reprocess payment {$paymentId} - HTTP {$httpCode} - resposta: " . substr($resp ?? '', 0, 1000), $quiet);
            }
        }
    }

    if (file_exists(LOCK_FILE)) unlink(LOCK_FILE);
    logMsg('Job finalizado', $quiet);
    exit(0);

} catch (Exception $e) {
    if (file_exists(LOCK_FILE)) unlink(LOCK_FILE);
    logMsg('❌ ERRO: ' . $e->getMessage());
    exit(1);
}
