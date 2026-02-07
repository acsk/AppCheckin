<?php
/**
 * Job: Atualizar Status de Matrículas Vencidas
 * 
 * Este script verifica e atualiza automaticamente o status das matrículas
 * baseado na data de vencimento (proxima_data_vencimento).
 * 
 * Lógica:
 * - Matrículas ATIVAS com proxima_data_vencimento < hoje → Status VENCIDA
 * - Matrículas VENCIDAS com proxima_data_vencimento >= hoje → Status ATIVA (reativação)
 * 
 * Execução via Cron (recomendado: todo dia às 00:05):
 * 5 0 * * * php /path/to/jobs/atualizar_status_vencimento.php >> /var/log/status_vencimento.log 2>&1
 * 
 * Execução manual:
 * docker exec appcheckin_php php /var/www/html/jobs/atualizar_status_vencimento.php
 * 
 * Opções:
 * --dry-run    Simula execução sem alterar banco
 * --quiet      Modo silencioso (apenas erros)
 * --tenant=N   Processa apenas o tenant específico
 */

// Configurações
define('LOCK_FILE', '/tmp/atualizar_status_vencimento.lock');
define('MAX_EXECUTION_TIME', 300); // 5 minutos

// Processar argumentos
$options = getopt('', ['dry-run', 'quiet', 'tenant:']);
$dryRun = isset($options['dry-run']);
$quiet = isset($options['quiet']);
$tenantId = isset($options['tenant']) ? (int)$options['tenant'] : null;

// Função de log
function logMsg($message, $isQuiet = false) {
    if (!$isQuiet) {
        echo "[" . date('Y-m-d H:i:s') . "] " . $message . "\n";
    }
}

// Verificar lock
if (file_exists(LOCK_FILE)) {
    $lockTime = filemtime(LOCK_FILE);
    if (time() - $lockTime > 600) { // 10 minutos
        unlink(LOCK_FILE);
        logMsg("⚠️  Lock antigo removido", $quiet);
    } else {
        logMsg("❌ Job já está em execução (lock ativo)", $quiet);
        exit(1);
    }
}

// Criar lock
file_put_contents(LOCK_FILE, getmypid());

// Configurar timeout
set_time_limit(MAX_EXECUTION_TIME);

try {
    logMsg("🚀 Iniciando job de atualização de status de vencimento", $quiet);
    if ($dryRun) {
        logMsg("⚠️  MODO DRY-RUN (simulação - sem alterações no banco)", $quiet);
    }
    
    // Conectar ao banco
    require_once __DIR__ . '/../config/database.php';
    
    if (!isset($pdo)) {
        throw new Exception("Erro ao conectar ao banco de dados");
    }
    
    logMsg("✅ Conexão com banco estabelecida", $quiet);
    
    $hoje = date('Y-m-d');
    $estatisticas = [
        'ativas_vencidas' => 0,
        'vencidas_reativadas' => 0,
        'erros' => 0,
        'tempo_inicio' => microtime(true)
    ];
    
    // =====================================================================
    // 1. ATUALIZAR ATIVAS VENCIDAS → VENCIDA
    // =====================================================================
    
    logMsg("\n📋 Buscando matrículas ATIVAS vencidas...", $quiet);
    
    $sqlAtivasVencidas = "
        SELECT m.id, m.tenant_id, m.aluno_id, m.proxima_data_vencimento,
               u.nome as aluno_nome, u.email as aluno_email
        FROM matriculas m
        INNER JOIN alunos a ON a.id = m.aluno_id
        INNER JOIN usuarios u ON u.id = a.usuario_id
        WHERE m.status_id = 1 -- ativa
        AND m.proxima_data_vencimento IS NOT NULL
        AND m.proxima_data_vencimento < :hoje
    ";
    
    if ($tenantId) {
        $sqlAtivasVencidas .= " AND m.tenant_id = :tenant_id";
    }
    
    $stmtBuscar = $pdo->prepare($sqlAtivasVencidas);
    $params = ['hoje' => $hoje];
    if ($tenantId) {
        $params['tenant_id'] = $tenantId;
    }
    $stmtBuscar->execute($params);
    $ativasVencidas = $stmtBuscar->fetchAll(PDO::FETCH_ASSOC);
    
    logMsg("   Encontradas: " . count($ativasVencidas) . " matrículas", $quiet);
    
    if (count($ativasVencidas) > 0) {
        if (!$dryRun) {
            $stmtUpdate = $pdo->prepare("
                UPDATE matriculas
                SET status_id = 2, -- vencida
                    updated_at = NOW()
                WHERE id = :id
            ");
        }
        
        foreach ($ativasVencidas as $mat) {
            $diasVencido = (new DateTime($hoje))->diff(new DateTime($mat['proxima_data_vencimento']))->days;
            
            if ($dryRun) {
                logMsg("   [DRY-RUN] Matrícula #{$mat['id']} - {$mat['aluno_nome']} - Venceu há {$diasVencido} dia(s)", $quiet);
            } else {
                try {
                    $stmtUpdate->execute(['id' => $mat['id']]);
                    $estatisticas['ativas_vencidas']++;
                    logMsg("   ✅ Matrícula #{$mat['id']} → VENCIDA (venceu {$mat['proxima_data_vencimento']})", $quiet);
                } catch (Exception $e) {
                    $estatisticas['erros']++;
                    logMsg("   ❌ Erro matrícula #{$mat['id']}: " . $e->getMessage(), $quiet);
                }
            }
        }
    }
    
    // =====================================================================
    // 2. REATIVAR VENCIDAS COM DATA VÁLIDA → ATIVA
    // =====================================================================
    
    logMsg("\n📋 Buscando matrículas VENCIDAS com data válida para reativar...", $quiet);
    
    $sqlVencidasValidas = "
        SELECT m.id, m.tenant_id, m.aluno_id, m.proxima_data_vencimento,
               u.nome as aluno_nome, u.email as aluno_email
        FROM matriculas m
        INNER JOIN alunos a ON a.id = m.aluno_id
        INNER JOIN usuarios u ON u.id = a.usuario_id
        WHERE m.status_id = 2 -- vencida
        AND m.proxima_data_vencimento IS NOT NULL
        AND m.proxima_data_vencimento >= :hoje
    ";
    
    if ($tenantId) {
        $sqlVencidasValidas .= " AND m.tenant_id = :tenant_id";
    }
    
    $stmtBuscar2 = $pdo->prepare($sqlVencidasValidas);
    $stmtBuscar2->execute($params);
    $vencidasValidas = $stmtBuscar2->fetchAll(PDO::FETCH_ASSOC);
    
    logMsg("   Encontradas: " . count($vencidasValidas) . " matrículas", $quiet);
    
    if (count($vencidasValidas) > 0) {
        if (!$dryRun) {
            $stmtReativar = $pdo->prepare("
                UPDATE matriculas
                SET status_id = 1, -- ativa
                    updated_at = NOW()
                WHERE id = :id
            ");
        }
        
        foreach ($vencidasValidas as $mat) {
            $diasRestantes = (new DateTime($mat['proxima_data_vencimento']))->diff(new DateTime($hoje))->days;
            
            if ($dryRun) {
                logMsg("   [DRY-RUN] Matrícula #{$mat['id']} - {$mat['aluno_nome']} - Válida por mais {$diasRestantes} dia(s)", $quiet);
            } else {
                try {
                    $stmtReativar->execute(['id' => $mat['id']]);
                    $estatisticas['vencidas_reativadas']++;
                    logMsg("   ✅ Matrícula #{$mat['id']} → ATIVA (válida até {$mat['proxima_data_vencimento']})", $quiet);
                } catch (Exception $e) {
                    $estatisticas['erros']++;
                    logMsg("   ❌ Erro matrícula #{$mat['id']}: " . $e->getMessage(), $quiet);
                }
            }
        }
    }
    
    // =====================================================================
    // ESTATÍSTICAS FINAIS
    // =====================================================================
    
    $tempoTotal = round(microtime(true) - $estatisticas['tempo_inicio'], 2);
    
    logMsg("\n" . str_repeat("=", 70), $quiet);
    logMsg("📊 RESUMO DA EXECUÇÃO", $quiet);
    logMsg(str_repeat("=", 70), $quiet);
    
    if ($dryRun) {
        logMsg("⚠️  MODO DRY-RUN - Nenhuma alteração foi feita no banco", $quiet);
    }
    
    logMsg("Data de referência: {$hoje}", $quiet);
    if ($tenantId) {
        logMsg("Tenant específico: {$tenantId}", $quiet);
    }
    logMsg("", $quiet);
    logMsg("Matrículas ATIVAS que venceram:        {$estatisticas['ativas_vencidas']}", $quiet);
    logMsg("Matrículas VENCIDAS reativadas:        {$estatisticas['vencidas_reativadas']}", $quiet);
    logMsg("Total processado:                      " . ($estatisticas['ativas_vencidas'] + $estatisticas['vencidas_reativadas']), $quiet);
    logMsg("Erros:                                 {$estatisticas['erros']}", $quiet);
    logMsg("Tempo de execução:                     {$tempoTotal}s", $quiet);
    logMsg(str_repeat("=", 70), $quiet);
    
    // Remover lock
    if (file_exists(LOCK_FILE)) {
        unlink(LOCK_FILE);
    }
    
    logMsg("\n✅ Job finalizado com sucesso!", $quiet);
    exit(0);
    
} catch (Exception $e) {
    // Remover lock em caso de erro
    if (file_exists(LOCK_FILE)) {
        unlink(LOCK_FILE);
    }
    
    logMsg("\n❌ ERRO FATAL: " . $e->getMessage(), $quiet);
    logMsg("Stack trace: " . $e->getTraceAsString(), $quiet);
    exit(1);
}
