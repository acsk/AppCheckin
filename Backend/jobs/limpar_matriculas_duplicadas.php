<?php
/**
 * Job para Limpar Matrículas Sem Pagamento
 * 
 * Este script cancela automaticamente matrículas que não foram pagas,
 * mantendo apenas a matrícula mais recente COM PAGAMENTO CONFIRMADO por modalidade.
 * 
 * Lógica:
 * 1. Para cada usuário, verifica se tem múltiplas matrículas
 * 2. Agrupa por modalidade
 * 3. Para cada modalidade:
 *    - Mantém a matrícula COM PAGAMENTO (status ativa)
 *    - Se múltiplas tiverem pagamento, mantém a mais recente
 *    - Se nenhuma tiver pagamento, mantém a mais recente (aguardando pagamento)
 *    - Cancela as demais SEM PAGAMENTO
 * 
 * Uso via cron:
 * 0 5 * * * php /path/to/jobs/limpar_matriculas_duplicadas.php >> /var/log/limpar_matriculas.log 2>&1
 * 
 * Uso manual:
 * docker exec appcheckin_php php /var/www/html/jobs/limpar_matriculas_duplicadas.php
 * 
 * Opções:
 * --dry-run   Simula as alterações sem fazer de verdade
 * --quiet     Modo silencioso (apenas erros)
 * --tenant=N  Processa apenas tenant N
 */

define('LOCK_FILE', '/tmp/limpar_matriculas.lock');

// Processar argumentos
$options = getopt('', ['dry-run', 'quiet', 'tenant:']);
$dryRun = isset($options['dry-run']);
$quiet = isset($options['quiet']);
$tenantId = isset($options['tenant']) ? (int)$options['tenant'] : null;

function logMessage($message, $quiet = false) {
    if (!$quiet) {
        echo $message;
    }
}

// Verificar lock
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

file_put_contents(LOCK_FILE, getmypid());
register_shutdown_function(function() {
    if (file_exists(LOCK_FILE)) {
        unlink(LOCK_FILE);
    }
});

set_time_limit(600);

require_once __DIR__ . '/../vendor/autoload.php';
date_default_timezone_set('America/Sao_Paulo');

$db = require __DIR__ . '/../config/database.php';
$db->setAttribute(PDO::ATTR_TIMEOUT, 30);

logMessage("========================================\n", $quiet);
logMessage("LIMPEZA DE MATRÍCULAS DUPLICADAS\n", $quiet);
logMessage("Data/Hora: " . date('Y-m-d H:i:s') . "\n", $quiet);
if ($dryRun) {
    logMessage("⚠️ MODO DRY-RUN (Nenhuma alteração será feita)\n", $quiet);
}
logMessage("========================================\n\n", $quiet);

$startTime = microtime(true);

try {
    // 1. Buscar tenants a processar
    if ($tenantId) {
        $sqlTenants = "SELECT id, nome FROM tenants WHERE id = :id AND ativo = 1";
        $stmt = $db->prepare($sqlTenants);
        $stmt->execute(['id' => $tenantId]);
    } else {
        $sqlTenants = "SELECT id, nome FROM tenants WHERE ativo = 1 ORDER BY id ASC";
        $stmt = $db->prepare($sqlTenants);
        $stmt->execute();
    }
    
    $tenants = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    logMessage("📊 Processando " . count($tenants) . " tenant(s)...\n\n", $quiet);
    
    $totalCanceladas = 0;
    $totalCanceleadas = 0;
    
    foreach ($tenants as $tenant) {
        $tId = $tenant['id'];
        logMessage("[Tenant #{$tId}] {$tenant['nome']}\n", $quiet);
        
        try {
            $db->beginTransaction();
            
            // 2. Buscar usuários com múltiplas matrículas ativas
            $sqlUsuarios = "
                SELECT DISTINCT m.usuario_id
                FROM matriculas m
                WHERE m.tenant_id = :tenant_id
                AND m.status IN ('ativa', 'pendente', 'vencida')
                GROUP BY m.usuario_id
                HAVING COUNT(*) > 1
            ";
            
            $stmt = $db->prepare($sqlUsuarios);
            $stmt->execute(['tenant_id' => $tId]);
            $usuarios = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            logMessage("  Usuários com múltiplas matrículas: " . count($usuarios) . "\n", $quiet);
            
            foreach ($usuarios as $usr) {
                $usrId = $usr['usuario_id'];
                
                // Buscar todas as matrículas do usuário neste tenant com info de pagamentos
                $sqlMatriculas = "
                    SELECT m.id, m.data_matricula, m.data_vencimento, m.data_inicio, m.status, m.created_at,
                           p.nome as plano_nome, mo.nome as modalidade_nome, mo.id as modalidade_id,
                           COUNT(DISTINCT pp.id) as total_pagamentos
                    FROM matriculas m
                    INNER JOIN planos p ON m.plano_id = p.id
                    INNER JOIN modalidades mo ON p.modalidade_id = mo.id
                    LEFT JOIN pagamentos_plano pp ON m.id = pp.matricula_id
                    WHERE m.usuario_id = :usuario_id
                    AND m.tenant_id = :tenant_id
                    AND m.status IN ('ativa', 'pendente')
                    GROUP BY m.id
                    ORDER BY m.data_matricula DESC, m.created_at DESC
                ";
                
                $stmt = $db->prepare($sqlMatriculas);
                $stmt->execute(['usuario_id' => $usrId, 'tenant_id' => $tId]);
                $matriculas = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                
                // Agrupar por modalidade
                $matriculasPorModalidade = [];
                foreach ($matriculas as $m) {
                    $modId = $m['modalidade_id'];
                    if (!isset($matriculasPorModalidade[$modId])) {
                        $matriculasPorModalidade[$modId] = [];
                    }
                    $matriculasPorModalidade[$modId][] = $m;
                }
                
                // Para cada modalidade, manter a melhor e cancelar as demais
                foreach ($matriculasPorModalidade as $modId => $matriculasMod) {
                    // Ordenar por: 1º DATA MAIS RECENTE (data_matricula ou data_inicio)
                    //             2º CRIADO MAIS RECENTE (created_at)
                    //             3º COM PAGAMENTO (total_pagamentos > 0)
                    //             4º STATUS (ativa > pendente)
                    usort($matriculasMod, function($a, $b) {
                        // Comparar por data_matricula/data_inicio (mais recente primeiro)
                        $dataA = strtotime($a['data_matricula'] ?? $a['data_inicio'] ?? $a['created_at']);
                        $dataB = strtotime($b['data_matricula'] ?? $b['data_inicio'] ?? $b['created_at']);
                        
                        if ($dataA !== $dataB) {
                            return $dataB - $dataA; // Mais recente primeiro
                        }
                        
                        // Se mesmo dia, comparar por created_at (mais recente primeiro)
                        $criadoA = strtotime($a['created_at']);
                        $criadoB = strtotime($b['created_at']);
                        
                        if ($criadoA !== $criadoB) {
                            return $criadoB - $criadoA;
                        }
                        
                        // Se mesmo created_at, prioriza COM PAGAMENTO
                        $temPagtoA = (int)$a['total_pagamentos'] > 0 ? 1 : 0;
                        $temPagtoB = (int)$b['total_pagamentos'] > 0 ? 1 : 0;
                        
                        if ($temPagtoA !== $temPagtoB) {
                            return $temPagtoB - $temPagtoA;
                        }
                        
                        // Se ambos com ou sem pagamento, prioriza ativa
                        $statusPriority = ['ativa' => 2, 'pendente' => 1];
                        $priorityA = $statusPriority[$a['status']] ?? 0;
                        $priorityB = $statusPriority[$b['status']] ?? 0;
                        
                        return $priorityB - $priorityA;
                    });
                    
                    // A primeira é a que devemos manter - MANTER
                    $matriculaVigente = $matriculasMod[0];
                    $pagtoInfo = (int)$matriculaVigente['total_pagamentos'] > 0 ? "com " . $matriculaVigente['total_pagamentos'] . " pagamento(s)" : "sem pagamento";
                    logMessage("    Mantendo: {$matriculaVigente['plano_nome']} (Data: {$matriculaVigente['data_matricula']}, Status: {$matriculaVigente['status']}, $pagtoInfo) ✓\n", $quiet);
                    
                    // Cancelar todas as outras
                    for ($idx = 1; $idx < count($matriculasMod); $idx++) {
                        $m = $matriculasMod[$idx];
                        $pagtoInfo = (int)$m['total_pagamentos'] > 0 ? "com " . $m['total_pagamentos'] . " pagamento(s)" : "sem pagamento";
                        logMessage("    Cancelando: {$m['plano_nome']} (Data: {$m['data_matricula']}, Status: {$m['status']}, $pagtoInfo)\n", $quiet);
                        
                        if (!$dryRun) {
                            $sqlCancel = "
                                UPDATE matriculas 
                                SET status = 'cancelada', updated_at = NOW()
                                WHERE id = :id
                            ";
                            $stmtCancel = $db->prepare($sqlCancel);
                            $stmtCancel->execute(['id' => $m['id']]);
                            $totalCanceleadas++;
                        }
                    }
                }
                
                $totalCanceladas++;
            }
            
            $db->commit();
            logMessage("\n", $quiet);
            
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            logMessage("  ❌ ERRO: " . $e->getMessage() . "\n\n", $quiet);
            continue;
        }
    }
    
    $elapsed = round(microtime(true) - $startTime, 2);
    
    logMessage("========================================\n", $quiet);
    logMessage("✅ CONCLUÍDO\n", $quiet);
    logMessage("Usuários processados: {$totalCanceladas}\n", $quiet);
    logMessage("Matrículas canceladas: {$totalCanceleadas}\n", $quiet);
    logMessage("Tempo: {$elapsed}s\n", $quiet);
    if ($dryRun) {
        logMessage("⚠️ Modo DRY-RUN: Nenhuma alteração foi feita\n", $quiet);
    }
    logMessage("========================================\n", $quiet);
    
} catch (Exception $e) {
    logMessage("❌ ERRO CRÍTICO: " . $e->getMessage() . "\n", false);
    exit(1);
} finally {
    if (file_exists(LOCK_FILE)) {
        unlink(LOCK_FILE);
    }
}

exit(0);
