<?php
/**
 * Job: excluir fisicamente cobranças canceladas (pagamentos_plano status 4).
 *
 * Remove apenas parcelas:
 * - status Cancelado (4)
 * - sem data_pagamento
 * - canceladas há mais de N dias (updated_at)
 *
 * Uso via cron (diário às 04:30):
 * 30 4 * * * docker exec appcheckin_php php /var/www/html/jobs/limpar_pagamentos_cancelados.php >> /var/log/appcheck/limpar_pagamentos_cancelados.log 2>&1
 *
 * Uso manual:
 * docker exec appcheckin_php php /var/www/html/jobs/limpar_pagamentos_cancelados.php
 * docker exec appcheckin_php php /var/www/html/jobs/limpar_pagamentos_cancelados.php --dry-run
 * docker exec appcheckin_php php /var/www/html/jobs/limpar_pagamentos_cancelados.php --dias=0 --tenant=3
 *
 * Opções:
 * --dry-run      Apenas conta, não exclui
 * --quiet        Modo silencioso
 * --tenant=N     Processa só o tenant N
 * --dias=N       Retenção em dias após cancelamento (default: 7)
 * --limit=N      Registros por lote DELETE (default: 1000; máx por query: 5000)
 */

define('LOCK_FILE', '/tmp/limpar_pagamentos_cancelados.lock');
define('MAX_POR_TENANT', 10000);

$options = getopt('', ['dry-run', 'quiet', 'tenant:', 'dias:', 'limit:']);
$dryRun = isset($options['dry-run']);
$quiet = isset($options['quiet']);
$tenantFilter = isset($options['tenant']) ? (int) $options['tenant'] : null;
$diasRetencao = isset($options['dias']) ? (int) $options['dias'] : 7;
$batchLimit = isset($options['limit']) ? (int) $options['limit'] : 1000;

function logMessage(string $message, bool $quiet = false): void
{
    if (!$quiet) {
        echo $message;
    }
}

if (file_exists(LOCK_FILE)) {
    $lockTime = filemtime(LOCK_FILE);
    if (time() - $lockTime > 600) {
        unlink(LOCK_FILE);
        logMessage("⚠️ Lock antigo removido\n", $quiet);
    } else {
        logMessage("❌ Já existe uma execução em andamento. Saindo...\n", $quiet);
        exit(0);
    }
}

file_put_contents(LOCK_FILE, (string) getmypid());
register_shutdown_function(static function (): void {
    if (file_exists(LOCK_FILE)) {
        unlink(LOCK_FILE);
    }
});

set_time_limit(600);

require_once __DIR__ . '/../vendor/autoload.php';
date_default_timezone_set('America/Sao_Paulo');

$db = require __DIR__ . '/../config/database.php';
$db->setAttribute(PDO::ATTR_TIMEOUT, 30);

$pagamentoModel = new \App\Models\PagamentoPlano($db);

logMessage("========================================\n", $quiet);
logMessage("LIMPEZA DE PAGAMENTOS CANCELADOS\n", $quiet);
logMessage('Data/Hora: ' . date('Y-m-d H:i:s') . "\n", $quiet);
logMessage("Retenção: {$diasRetencao} dia(s) | Lote: {$batchLimit}\n", $quiet);
if ($dryRun) {
    logMessage("⚠️ MODO DRY-RUN (nenhuma exclusão)\n", $quiet);
}
logMessage("========================================\n\n", $quiet);

$startTime = microtime(true);
$totalExcluidos = 0;

try {
    if ($tenantFilter) {
        $stmtTenants = $db->prepare('SELECT id, nome FROM tenants WHERE id = :id AND ativo = 1');
        $stmtTenants->execute(['id' => $tenantFilter]);
    } else {
        $stmtTenants = $db->query('SELECT id, nome FROM tenants WHERE ativo = 1 ORDER BY id ASC');
    }

    $tenants = $stmtTenants->fetchAll(PDO::FETCH_ASSOC);
    logMessage('📊 Processando ' . count($tenants) . " tenant(s)...\n\n", $quiet);

    foreach ($tenants as $tenant) {
        $tenantId = (int) $tenant['id'];
        $elegiveis = $pagamentoModel->contarCanceladosParaExcluir($tenantId, $diasRetencao);

        if ($elegiveis === 0) {
            continue;
        }

        logMessage("[Tenant #{$tenantId}] {$tenant['nome']}\n", $quiet);
        logMessage("  Elegíveis para exclusão: {$elegiveis}\n", $quiet);

        if ($dryRun) {
            $totalExcluidos += $elegiveis;
            logMessage("  [dry-run] Seriam excluídos: {$elegiveis}\n\n", $quiet);
            continue;
        }

        $excluidosTenant = 0;
        while ($excluidosTenant < MAX_POR_TENANT) {
            $restante = MAX_POR_TENANT - $excluidosTenant;
            $limiteLote = min(
                $batchLimit,
                $restante,
                \App\Models\PagamentoPlano::LIMITE_EXCLUSAO_LOTE
            );
            $excluidosLote = $pagamentoModel->excluirCanceladosAntigos($tenantId, $diasRetencao, $limiteLote);
            if ($excluidosLote === 0) {
                break;
            }
            $excluidosTenant += $excluidosLote;
        }

        $totalExcluidos += $excluidosTenant;
        logMessage("  ✓ Excluídos: {$excluidosTenant}\n\n", $quiet);
    }

    $elapsed = round(microtime(true) - $startTime, 2);
    logMessage("========================================\n", $quiet);
    logMessage("✅ CONCLUÍDO\n", $quiet);
    logMessage(($dryRun ? 'Total elegível' : 'Total excluído') . ": {$totalExcluidos}\n", $quiet);
    logMessage("Tempo: {$elapsed}s\n", $quiet);
    logMessage("========================================\n", $quiet);
} catch (Throwable $e) {
    logMessage('❌ ERRO: ' . $e->getMessage() . "\n", false);
    exit(1);
}

exit(0);
