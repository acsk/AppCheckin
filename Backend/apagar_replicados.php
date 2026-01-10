<?php

/**
 * Script para apagar agendamentos criados pela replicação
 * Mantém apenas as turmas do dia 9 (dia_id 17)
 */

require 'vendor/autoload.php';

try {
    $dsn = 'mysql:host=mysql;dbname=appcheckin;charset=utf8mb4';
    $user = 'root';
    $pass = 'root';
    
    $pdo = new PDO(
        $dsn,
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    echo "╔════════════════════════════════════════════════════════════════╗\n";
    echo "║  Limpeza de Agendamentos Replicados (Tenant 5)               ║\n";
    echo "╚════════════════════════════════════════════════════════════════╝\n\n";
    
    // 1. Buscar turmas a deletar (excluindo dia_id 17)
    echo "1️⃣  Buscando turmas a deletar (exceto dia 17 - 2026-01-09)...\n\n";
    
    $sql = "SELECT t.id, t.nome, d.data, t.horario_inicio, t.horario_fim
            FROM turmas t
            JOIN dias d ON t.dia_id = d.id
            WHERE t.tenant_id = 5 
            AND t.dia_id != 17
            ORDER BY d.data, t.horario_inicio";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $turmas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "📊 Turmas encontradas para deletar: " . count($turmas) . "\n\n";
    
    if (count($turmas) > 0) {
        echo "Detalhes:\n";
        echo "─────────────────────────────────────────────────────────────\n";
        foreach ($turmas as $turma) {
            echo "  ID #{$turma['id']}: {$turma['data']} - {$turma['horario_inicio']} a {$turma['horario_fim']}\n";
        }
        echo "─────────────────────────────────────────────────────────────\n\n";
    }
    
    // 2. Confirmar exclusão
    echo "⚠️  ATENÇÃO: Esta operação é IRREVERSÍVEL!\n";
    echo "   Você está prestes a deletar " . count($turmas) . " agendamento(s).\n";
    echo "   Serão mantidos apenas os 3 do dia 09/01/2026 (dia_id 17).\n\n";
    
    echo "Deseja continuar? (sim/não): ";
    $handle = fopen("php://stdin", "r");
    $resposta = trim(fgets($handle));
    fclose($handle);
    
    if (strtolower($resposta) !== 'sim' && $resposta !== 's') {
        echo "\n❌ Operação cancelada pelo usuário.\n";
        exit(0);
    }
    
    // 3. Deletar turmas
    echo "\n2️⃣  Deletando turmas...\n\n";
    
    $sqlDelete = "DELETE FROM turmas 
                  WHERE tenant_id = 5 
                  AND dia_id != 17";
    
    $stmtDelete = $pdo->prepare($sqlDelete);
    $stmtDelete->execute();
    
    $deletadas = $stmtDelete->rowCount();
    
    echo "✅ $deletadas turma(s) deletada(s)\n\n";
    
    // 4. Verificação final
    echo "3️⃣  Verificando estado final...\n\n";
    
    $sqlVerifyDel = "SELECT COUNT(*) as qtd FROM turmas WHERE tenant_id = 5 AND dia_id != 17";
    $stmtVerifyDel = $pdo->prepare($sqlVerifyDel);
    $stmtVerifyDel->execute();
    $resultDel = $stmtVerifyDel->fetch(PDO::FETCH_ASSOC);
    
    $sqlVerifyKeep = "SELECT COUNT(*) as qtd FROM turmas WHERE tenant_id = 5 AND dia_id = 17";
    $stmtVerifyKeep = $pdo->prepare($sqlVerifyKeep);
    $stmtVerifyKeep->execute();
    $resultKeep = $stmtVerifyKeep->fetch(PDO::FETCH_ASSOC);
    
    echo "📊 Status:\n";
    echo "   ├─ Turmas deletadas: $deletadas ✅\n";
    echo "   ├─ Turmas mantidas (dia 09/01): " . $resultKeep['qtd'] . "\n";
    echo "   └─ Turmas restantes fora do dia 09/01: " . $resultDel['qtd'] . "\n\n";
    
    if ($resultDel['qtd'] == 0) {
        echo "✅ SUCESSO: Todos os agendamentos replicados foram removidos!\n";
        echo "✅ Mantidas apenas as " . $resultKeep['qtd'] . " turmas originais do dia 09/01/2026\n\n";
        
        // Listar as turmas mantidas
        $sqlKeep = "SELECT t.id, t.nome, t.horario_inicio, t.horario_fim, p.nome as professor
                    FROM turmas t
                    JOIN professores p ON t.professor_id = p.id
                    WHERE t.tenant_id = 5 AND t.dia_id = 17
                    ORDER BY t.horario_inicio";
        
        $stmtKeep = $pdo->prepare($sqlKeep);
        $stmtKeep->execute();
        $kept = $stmtKeep->fetchAll(PDO::FETCH_ASSOC);
        
        echo "Turmas mantidas:\n";
        echo "─────────────────────────────────────────────────────────────\n";
        foreach ($kept as $t) {
            echo "  #{$t['id']}: {$t['horario_inicio']} - {$t['horario_fim']} ({$t['professor']})\n";
        }
        echo "─────────────────────────────────────────────────────────────\n";
    } else {
        echo "❌ ERRO: Ainda existem agendamentos após deleção!\n";
    }
    
    echo "\n╔════════════════════════════════════════════════════════════════╗\n";
    echo "║                       OPERAÇÃO CONCLUÍDA                       ║\n";
    echo "╚════════════════════════════════════════════════════════════════╝\n";
    
} catch (\Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
