<?php
/**
 * Job para atualizar status de matrículas automaticamente
 * 
 * Este script deve ser executado periodicamente (ex: a cada hora ou diariamente)
 * para atualizar o status das matrículas baseado nos pagamentos vencidos.
 * 
 * Lógica de Status:
 * - Ativa (0 dias): Pagamento em dia
 * - Vencida (1-4 dias): Pagamento vencido, aguardando regularização  
 * - Cancelada (5+ dias): Inadimplência - acesso bloqueado
 * 
 * Uso via cron:
 * 0 6 * * * php /path/to/jobs/atualizar_status_matriculas.php >> /var/log/status_matriculas.log 2>&1
 * 
 * Uso manual:
 * docker exec appcheckin_php php /var/www/html/jobs/atualizar_status_matriculas.php
 * 
 * Opções:
 * --limit=N    Processa apenas N tenants por execução (default: 50)
 * --sleep=N    Pausa N milissegundos entre tenants (default: 100)
 * --quiet      Modo silencioso (apenas erros)
 */

// Configurações de performance
define('BATCH_LIMIT', 50);       // Máximo de tenants por execução
define('SLEEP_BETWEEN_TENANTS', 100000); // 100ms em microsegundos
define('LOCK_FILE', '/tmp/atualizar_status_matriculas.lock');
define('MAX_EXECUTION_TIME', 300); // 5 minutos máximo

// Processar argumentos da linha de comando
$options = getopt('', ['limit:', 'sleep:', 'quiet']);
$limit = isset($options['limit']) ? (int)$options['limit'] : BATCH_LIMIT;
$sleepTime = isset($options['sleep']) ? (int)$options['sleep'] * 1000 : SLEEP_BETWEEN_TENANTS;
$quiet = isset($options['quiet']);

// Função para log
function logMessage($message, $quiet = false) {
    if (!$quiet) {
        echo $message;
    }
}

// Verificar se já existe uma execução em andamento (lock)
if (file_exists(LOCK_FILE)) {
    $lockTime = filemtime(LOCK_FILE);
    // Se o lock tem mais de 10 minutos, provavelmente travou - remover
    if (time() - $lockTime > 600) {
        unlink(LOCK_FILE);
        logMessage("⚠️ Lock antigo removido (processo anterior pode ter travado)\n", $quiet);
    } else {
        logMessage("❌ Já existe uma execução em andamento. Saindo...\n", $quiet);
        exit(0);
    }
}

// Criar lock file
file_put_contents(LOCK_FILE, getmypid());

// Registrar handler para remover lock em caso de erro
register_shutdown_function(function() {
    if (file_exists(LOCK_FILE)) {
        unlink(LOCK_FILE);
    }
});

// Capturar sinais de término (CTRL+C, kill, etc)
if (function_exists('pcntl_signal')) {
    pcntl_signal(SIGTERM, function() {
        if (file_exists(LOCK_FILE)) unlink(LOCK_FILE);
        exit(0);
    });
    pcntl_signal(SIGINT, function() {
        if (file_exists(LOCK_FILE)) unlink(LOCK_FILE);
        exit(0);
    });
}

// Limitar tempo de execução
set_time_limit(MAX_EXECUTION_TIME);

require_once __DIR__ . '/../vendor/autoload.php';

// Configurar timezone
date_default_timezone_set('America/Sao_Paulo');

// Carregar configurações
$db = require __DIR__ . '/../config/database.php';

// Configurar conexão para não travar
$db->setAttribute(PDO::ATTR_TIMEOUT, 30);

logMessage("========================================\n", $quiet);
logMessage("ATUALIZAÇÃO DE STATUS DE MATRÍCULAS\n", $quiet);
logMessage("Data/Hora: " . date('Y-m-d H:i:s') . "\n", $quiet);
logMessage("Limite: {$limit} tenants | Sleep: " . ($sleepTime/1000) . "ms\n", $quiet);
logMessage("========================================\n\n", $quiet);

$startTime = microtime(true);

try {
    // Buscar tenants ativos COM LIMITE para não sobrecarregar
    // Usa ORDER BY RAND() para distribuir a carga ao longo do tempo
    $stmtTenants = $db->prepare("
        SELECT id, nome FROM tenants 
        WHERE ativo = 1 
        ORDER BY id ASC
        LIMIT :limit
    ");
    $stmtTenants->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmtTenants->execute();
    $tenants = $stmtTenants->fetchAll(PDO::FETCH_ASSOC);
    
    logMessage("📊 Processando " . count($tenants) . " tenant(s)...\n\n", $quiet);
    
    $totalAtualizado = 0;
    $tenantsProcessados = 0;
    
    foreach ($tenants as $tenant) {
        $tenantsProcessados++;
        logMessage("[{$tenantsProcessados}/" . count($tenants) . "] Tenant #{$tenant['id']}: {$tenant['nome']}\n", $quiet);
        
        try {
            // Iniciar transação para cada tenant (mais seguro)
            $db->beginTransaction();
            
            // 1. Marcar pagamentos como ATRASADOS (status_pagamento_id = 3)
            // LIMIT para não travar em tenants com muitos registros
            $sqlPagamentosAtrasados = "
                UPDATE pagamentos_plano 
                SET status_pagamento_id = 3, updated_at = NOW()
                WHERE tenant_id = :tenant_id
                AND status_pagamento_id = 1
                AND data_vencimento < CURDATE()
                AND data_pagamento IS NULL
                LIMIT 1000
            ";
            $stmt = $db->prepare($sqlPagamentosAtrasados);
            $stmt->execute(['tenant_id' => $tenant['id']]);
            $pagamentosAtualizados = $stmt->rowCount();
            logMessage("  ✓ Pagamentos Atrasados: {$pagamentosAtualizados}\n", $quiet);
            
            // 2. Atualizar matrículas para VENCIDA (1-4 dias de atraso)
            $sqlVencida = "
                UPDATE matriculas m
                SET m.status_id = (SELECT id FROM status_matricula WHERE codigo = 'vencida' LIMIT 1),
                    m.updated_at = NOW()
                WHERE m.tenant_id = :tenant_id 
                AND m.status_id = (SELECT id FROM status_matricula WHERE codigo = 'ativa' LIMIT 1)
                AND EXISTS (
                    SELECT 1 FROM pagamentos_plano pp
                    WHERE pp.matricula_id = m.id
                    AND pp.status_pagamento_id IN (1, 3)
                    AND pp.data_vencimento < CURDATE()
                    AND DATEDIFF(CURDATE(), pp.data_vencimento) >= 1
                    AND DATEDIFF(CURDATE(), pp.data_vencimento) < 5
                )
                LIMIT 500
            ";
            $stmt = $db->prepare($sqlVencida);
            $stmt->execute(['tenant_id' => $tenant['id']]);
            $vencidasAtualizadas = $stmt->rowCount();
            logMessage("  ✓ Matrículas Vencidas: {$vencidasAtualizadas}\n", $quiet);
            
            // 3. Atualizar matrículas para CANCELADA (5+ dias de atraso)
            $sqlCancelada = "
                UPDATE matriculas m
                SET m.status_id = (SELECT id FROM status_matricula WHERE codigo = 'cancelada' LIMIT 1),
                    m.updated_at = NOW()
                WHERE m.tenant_id = :tenant_id 
                AND m.status_id IN (SELECT id FROM status_matricula WHERE codigo IN ('ativa', 'vencida'))
                AND EXISTS (
                    SELECT 1 FROM pagamentos_plano pp
                    WHERE pp.matricula_id = m.id
                    AND pp.status_pagamento_id IN (1, 3)
                    AND pp.data_vencimento < CURDATE()
                    AND DATEDIFF(CURDATE(), pp.data_vencimento) >= 5
                )
                LIMIT 500
            ";
            $stmt = $db->prepare($sqlCancelada);
            $stmt->execute(['tenant_id' => $tenant['id']]);
            $canceladasAtualizadas = $stmt->rowCount();
            logMessage("  ✓ Matrículas Canceladas: {$canceladasAtualizadas}\n", $quiet);
            
            // 4. Sincronizar matrículas com assinaturas canceladas
            // Se a assinatura foi cancelada, a matrícula também deve ser cancelada
            $sqlSincAssCanceladas = "
                UPDATE matriculas m
                INNER JOIN assinaturas a ON a.matricula_id = m.id AND a.tenant_id = m.tenant_id
                INNER JOIN assinatura_status ast ON ast.id = a.status_id
                SET m.status_id = (SELECT id FROM status_matricula WHERE codigo = 'cancelada' LIMIT 1),
                    m.updated_at = NOW()
                WHERE m.tenant_id = :tenant_id
                AND ast.codigo = 'cancelada'
                AND m.status_id IN (SELECT id FROM status_matricula WHERE codigo IN ('ativa', 'vencida'))
                LIMIT 500
            ";
            $stmt = $db->prepare($sqlSincAssCanceladas);
            $stmt->execute(['tenant_id' => $tenant['id']]);
            $sincCanceladas = $stmt->rowCount();
            logMessage("  ✓ Matrículas sincronizadas (assinatura cancelada): {$sincCanceladas}\n", $quiet);
            
            // 5. Sincronizar matrículas com assinaturas pausadas
            // Se a assinatura foi pausada, a matrícula deve ficar suspensa
            $sqlSincAssPausadas = "
                UPDATE matriculas m
                INNER JOIN assinaturas a ON a.matricula_id = m.id AND a.tenant_id = m.tenant_id
                INNER JOIN assinatura_status ast ON ast.id = a.status_id
                SET m.status_id = (SELECT id FROM status_matricula WHERE codigo = 'suspensa' LIMIT 1),
                    m.updated_at = NOW()
                WHERE m.tenant_id = :tenant_id
                AND ast.codigo = 'pausada'
                AND m.status_id IN (SELECT id FROM status_matricula WHERE codigo IN ('ativa', 'vencida'))
                LIMIT 500
            ";
            $stmt = $db->prepare($sqlSincAssPausadas);
            $stmt->execute(['tenant_id' => $tenant['id']]);
            $sincPausadas = $stmt->rowCount();
            logMessage("  ✓ Matrículas sincronizadas (assinatura pausada): {$sincPausadas}\n", $quiet);
            
            // 6. Sincronizar matrículas com assinaturas expiradas
            $sqlSincAssExpiradas = "
                UPDATE matriculas m
                INNER JOIN assinaturas a ON a.matricula_id = m.id AND a.tenant_id = m.tenant_id
                INNER JOIN assinatura_status ast ON ast.id = a.status_id
                SET m.status_id = (SELECT id FROM status_matricula WHERE codigo = 'vencida' LIMIT 1),
                    m.updated_at = NOW()
                WHERE m.tenant_id = :tenant_id
                AND ast.codigo = 'expirada'
                AND m.status_id = (SELECT id FROM status_matricula WHERE codigo = 'ativa' LIMIT 1)
                LIMIT 500
            ";
            $stmt = $db->prepare($sqlSincAssExpiradas);
            $stmt->execute(['tenant_id' => $tenant['id']]);
            $sincExpiradas = $stmt->rowCount();
            logMessage("  ✓ Matrículas sincronizadas (assinatura expirada): {$sincExpiradas}\n", $quiet);
            
            // 7. Reativar matrículas que foram regularizadas
            // Só reativa se NÃO houver assinatura cancelada/pausada associada
            $sqlReativar = "
                UPDATE matriculas m
                SET m.status_id = (SELECT id FROM status_matricula WHERE codigo = 'ativa' LIMIT 1),
                    m.updated_at = NOW()
                WHERE m.tenant_id = :tenant_id 
                AND m.status_id = (SELECT id FROM status_matricula WHERE codigo = 'vencida' LIMIT 1)
                AND NOT EXISTS (
                    SELECT 1 FROM pagamentos_plano pp
                    WHERE pp.matricula_id = m.id
                    AND pp.status_pagamento_id IN (1, 3)
                    AND pp.data_vencimento < CURDATE()
                )
                AND NOT EXISTS (
                    SELECT 1 FROM assinaturas a
                    INNER JOIN assinatura_status ast ON ast.id = a.status_id
                    WHERE a.matricula_id = m.id
                    AND a.tenant_id = m.tenant_id
                    AND ast.codigo IN ('cancelada', 'pausada', 'expirada')
                )
                LIMIT 500
            ";
            $stmt = $db->prepare($sqlReativar);
            $stmt->execute(['tenant_id' => $tenant['id']]);
            $reativadas = $stmt->rowCount();
            logMessage("  ✓ Matrículas Reativadas: {$reativadas}\n", $quiet);
            
            // Commit da transação
            $db->commit();
            
            $totalTenant = $vencidasAtualizadas + $canceladasAtualizadas + $sincCanceladas + $sincPausadas + $sincExpiradas + $reativadas;
            $totalAtualizado += $totalTenant;
            
            if ($totalTenant > 0) {
                logMessage("  📝 Subtotal: {$totalTenant} alterações\n", $quiet);
            }
            logMessage("\n", $quiet);
            
        } catch (Exception $e) {
            // Rollback em caso de erro no tenant
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            logMessage("  ❌ ERRO no tenant: " . $e->getMessage() . "\n\n", $quiet);
            // Continua para o próximo tenant
            continue;
        }
        
        // Pausa entre tenants para não sobrecarregar o banco
        if ($tenantsProcessados < count($tenants)) {
            usleep($sleepTime);
        }
        
        // Verificar se está demorando demais
        $elapsed = microtime(true) - $startTime;
        if ($elapsed > MAX_EXECUTION_TIME - 30) { // Para 30s antes do limite
            logMessage("⚠️ Tempo limite atingido. Continuará na próxima execução.\n", $quiet);
            break;
        }
    }
    
    $elapsed = round(microtime(true) - $startTime, 2);
    
    logMessage("========================================\n", $quiet);
    logMessage("✅ CONCLUÍDO\n", $quiet);
    logMessage("Tenants processados: {$tenantsProcessados}\n", $quiet);
    logMessage("Total de alterações: {$totalAtualizado}\n", $quiet);
    logMessage("Tempo de execução: {$elapsed}s\n", $quiet);
    logMessage("========================================\n", $quiet);
    
} catch (Exception $e) {
    logMessage("❌ ERRO CRÍTICO: " . $e->getMessage() . "\n", false);
    exit(1);
} finally {
    // Remover lock
    if (file_exists(LOCK_FILE)) {
        unlink(LOCK_FILE);
    }
}

exit(0);
