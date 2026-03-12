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

$root = dirname(__DIR__);
require_once $root . '/config/database.php';

$dryRun = in_array('--dry-run', $argv ?? []);
$logFile = $root . '/storage/logs/reprocess_today_payments.log';

function logMsg($msg) {
    global $logFile;
    $ts = (new DateTime())->format('Y-m-d H:i:s');
    file_put_contents($logFile, "[$ts] $msg\n", FILE_APPEND);
}

$db = require $root . '/config/database.php';

logMsg('Iniciando job reprocess_today_payments' . ($dryRun ? ' (dry-run)' : ''));

try {
    $stmt = $db->prepare("SELECT id, tenant_id, matricula_id, gateway_id, gateway_assinatura_id, criado_em FROM assinaturas WHERE DATE(criado_em) = CURDATE()");
    $stmt->execute();
    $assinaturas = $stmt->fetchAll(PDO::FETCH_ASSOC) ?? [];

    logMsg('Assinaturas encontradas: ' . count($assinaturas));

    foreach ($assinaturas as $ass) {
        $matriculaId = $ass['matricula_id'];
        $assinaturaId = $ass['id'];

        if (empty($matriculaId)) {
            logMsg("Assinatura {$assinaturaId} sem matricula_id — pulando");
            continue;
        }

        // Buscar pagamentos do mesmo dia para esta matricula que não estejam aprovados
        $stmtP = $db->prepare(
            "SELECT id, payment_id, status, status_detail, date_created FROM pagamentos_mercadopago WHERE matricula_id = ? AND DATE(date_created) = CURDATE() AND (status IS NULL OR LOWER(status) != 'approved')"
        );
        $stmtP->execute([$matriculaId]);
        $pagamentos = $stmtP->fetchAll(PDO::FETCH_ASSOC) ?? [];

        logMsg("Assinatura {$assinaturaId} (matricula {$matriculaId}) - pagamentos a reprocessar: " . count($pagamentos));

        foreach ($pagamentos as $pg) {
            $paymentId = $pg['payment_id'] ?? null;
            if (empty($paymentId)) {
                logMsg("Pagamento registro {$pg['id']} sem payment_id — pulando");
                continue;
            }

            $url = "https://api.appcheckin.com.br/api/webhooks/mercadopago/payment/{$paymentId}/reprocess";

            if ($dryRun) {
                logMsg("[dry-run] POST {$url}");
                continue;
            }

            // Fazer requisição POST (sem payload) com timeout
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            // se necessário, incluir cabeçalhos de autenticação (ex: Authorization) — adicionar aqui
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json'
            ]);

            $resp = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_close($ch);

            if ($err) {
                logMsg("Erro ao chamar reprocess para payment {$paymentId}: {$err}");
            } else {
                logMsg("Reprocess payment {$paymentId} - HTTP {$httpCode} - resposta: " . substr($resp ?? '', 0, 1000));
            }
        }
    }

    logMsg('Job finalizado');
} catch (Exception $e) {
    logMsg('Erro no job: ' . $e->getMessage());
    exit(1);
}

return 0;
