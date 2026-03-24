<?php
/**
 * Job: Reprocessar pagamentos para assinaturas
 *
 * Regras:
 * - Por padrão: seleciona assinaturas criadas HOJE
 * - Para cada assinatura, busca pagamentos com status 'pending'
 * - Chama endpoint de reprocessamento para cada pagamento
 * - Desvia de pagamentos já com status_id=6 (approved)
 *
 * Modos de uso:
 *  php jobs/atualizar_pagamentos_mp.php [--dry-run]
 *  php jobs/atualizar_pagamentos_mp.php --matricula-id=58               (reprocessa matrícula específica)
 *  php jobs/atualizar_pagamentos_mp.php --assinatura-id=51             (reprocessa assinatura específica)
 *  php jobs/atualizar_pagamentos_mp.php --days=7                       (últimos 7 dias)
 *  php jobs/atualizar_pagamentos_mp.php --matricula-id=58 --dry-run    (simula)
 *
 * ⚠️  IMPORTANTE: Este job depende de webhooks retornarem para atualizar o banco localmente.
 *    Se a webhook falhar, o banco permanecerá desatualizado.
 */

define('LOCK_FILE', '/tmp/atualizar_pagamentos_mp.lock');
define('MAX_EXECUTION_TIME', 300);

$options = getopt('', ['dry-run', 'quiet', 'tenant:','verbose','matricula-id:','assinatura-id:','days:']);
$dryRun = isset($options['dry-run']);
$quiet = isset($options['quiet']);
$tenantId = isset($options['tenant']) ? (int)$options['tenant'] : null;
$verbose = isset($options['verbose']);
$matriculaId = isset($options['matricula-id']) ? (int)$options['matricula-id'] : null;
$assinaturaId = isset($options['assinatura-id']) ? (int)$options['assinatura-id'] : null;
$daysBack = isset($options['days']) ? (int)$options['days'] : 0; // 0 = apenas hoje, 7 = últimos 7 dias

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
    
    if ($matriculaId) {
        logMsg("↪️  Modo: Reprocessar Matrícula #$matriculaId", $quiet);
    }
    if ($assinaturaId) {
        logMsg("↪️  Modo: Reprocessar Assinatura #$assinaturaId", $quiet);
    }
    if ($daysBack > 0) {
        logMsg("↪️  Janela de tempo: últimos $daysBack dias", $quiet);
    }

    // Conectar DB
    require_once __DIR__ . '/../config/database.php';
    if (!isset($pdo) || !$pdo) {
        // config/database.php retorna PDO, capture em $pdo
        $pdo = require __DIR__ . '/../config/database.php';
    }
    if (!isset($pdo)) throw new Exception('Erro ao conectar ao banco');

    // Construir query de assinaturas com filtros flexíveis
    $sqlAss = "SELECT id, tenant_id, matricula_id, gateway_id, gateway_assinatura_id, criado_em FROM assinaturas WHERE 1=1";
    $paramsAss = [];
    
    if ($assinaturaId) {
        $sqlAss .= " AND id = ?";
        $paramsAss[] = $assinaturaId;
    } else {
        // Se não especificou assinatura, filtrar por data (com flexibilidade)
        if ($daysBack > 0) {
            $sqlAss .= " AND DATE(criado_em) >= DATE_SUB(CURDATE(), INTERVAL ? DAY)";
            $paramsAss[] = $daysBack;
        } else {
            $sqlAss .= " AND DATE(criado_em) = CURDATE()";
        }
    }
    
    if ($tenantId) {
        $sqlAss .= " AND tenant_id = ?";
        $paramsAss[] = $tenantId;
    }

    $stmt = $pdo->prepare($sqlAss);
    $stmt->execute($paramsAss);
    $assinaturas = $stmt->fetchAll(PDO::FETCH_ASSOC) ?? [];
    logMsg('Assinaturas encontradas: ' . count($assinaturas), $quiet);

    foreach ($assinaturas as $ass) {
        $matriculaIdAss = $ass['matricula_id'] ?? null;
        $assinaturaIdCur = $ass['id'];

        if (empty($matriculaIdAss) && !$matriculaId) {
            logMsg("Assinatura {$assinaturaIdCur} sem matricula_id — pulando", $quiet);
            continue;
        }

        // Se foi especificada uma matrícula via CLI, usar essa
        $mId = $matriculaId ?? $matriculaIdAss;

        // Construir query de pagamentos com filtros flexíveis
        $sqlP = "SELECT id, payment_id, status, status_detail, status_id, date_created 
                 FROM pagamentos_mercadopago 
                 WHERE matricula_id = ? 
                 AND LOWER(status) = 'pending'";
        
        $paramsP = [$mId];
        
        // Se especificou assinatura ou matrícula, não filtrar por data
        // Senão, filtrar por data (flexível com --days)
        if (!$assinaturaId && !$matriculaId && $daysBack == 0) {
            $sqlP .= " AND DATE(date_created) = CURDATE()";
        } elseif ($daysBack > 0) {
            $sqlP .= " AND DATE(date_created) >= DATE_SUB(CURDATE(), INTERVAL ? DAY)";
            $paramsP[] = $daysBack;
        }
        
        $stmtP = $pdo->prepare($sqlP);
        $stmtP->execute($paramsP);
        $pagamentos = $stmtP->fetchAll(PDO::FETCH_ASSOC) ?? [];

        logMsg("Assinatura {$assinaturaIdCur} (matricula {$mId}) - pagamentos pendentes: " . count($pagamentos), $quiet);

        foreach ($pagamentos as $pg) {
            $paymentId = $pg['payment_id'] ?? null;
            $statusIdBanco = isset($pg['status_id']) ? (int)$pg['status_id'] : null;
            $statusText = $pg['status'] ?? 'unknown';
            
            if (empty($paymentId)) {
                logMsg("Pagamento registro {$pg['id']} sem payment_id — pulando", $quiet);
                continue;
            }

            // status_id=6 significa 'approved', não reprocessar
            if ($statusIdBanco === 6) {
                logMsg("Pagamento {$pg['id']} (payment_id={$paymentId}) já tem status_id=6 (approved) — pulando", $quiet);
                continue;
            }

            $url = "https://api.appcheckin.com.br/api/webhooks/mercadopago/payment/{$paymentId}/reprocess";

            if ($dryRun) {
                logMsg("[dry-run] POST {$url} [status={$statusText}, status_id={$statusIdBanco}]", $quiet);
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
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

            $resp = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_close($ch);

            if ($err) {
                logMsg("❌ Erro ao chamar reprocess para payment {$paymentId}: {$err}", $quiet);
            } else {
                if ($httpCode >= 200 && $httpCode <= 299) {
                    logMsg("✅ Reprocess payment {$paymentId} - HTTP {$httpCode} OK", $quiet);
                } else {
                    logMsg("⚠️  Reprocess payment {$paymentId} - HTTP {$httpCode} - resposta: " . substr($resp ?? '', 0, 500), $quiet);
                }
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
