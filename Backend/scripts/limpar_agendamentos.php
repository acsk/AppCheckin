<?php

/**
 * Script para apagar todos os agendamentos a partir de 10/01/2026
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
    echo "║  Limpeza de Agendamentos a partir de 10/01/2026              ║\n";
    echo "╚════════════════════════════════════════════════════════════════╝\n\n";
    
    // 1. Buscar turmas que serão deletadas
    echo "1️⃣  Buscando turmas a partir de 10/01/2026...\n\n";
    
    $sql = "SELECT t.id, t.nome, d.data, t.horario_inicio, t.horario_fim, t.professor_id
            FROM turmas t
            JOIN dias d ON t.dia_id = d.id
            WHERE d.data >= '2026-01-10'
            ORDER BY d.data, t.horario_inicio";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $turmas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "📊 Turmas encontradas: " . count($turmas) . "\n\n";
    
    if (count($turmas) > 0) {
        echo "Detalhes:\n";
        echo "─────────────────────────────────────────────────────────────\n";
        foreach ($turmas as $turma) {
            echo "  ID #{$turma['id']}: {$turma['data']} - {$turma['horario_inicio']} a {$turma['horario_fim']}\n";
            echo "           Turma: {$turma['nome']}\n";
        }
        echo "─────────────────────────────────────────────────────────────\n\n";
    }
    
    // 2. Confirmar exclusão
    echo "⚠️  ATENÇÃO: Esta operação é IRREVERSÍVEL!\n";
    echo "   Você está prestes a deletar " . count($turmas) . " agendamento(s).\n\n";
    
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
                  WHERE dia_id IN (
                    SELECT id FROM dias WHERE data >= '2026-01-10'
                  )";
    
    $stmtDelete = $pdo->prepare($sqlDelete);
    $stmtDelete->execute();
    
    $deletadas = $stmtDelete->rowCount();
    
    echo "✅ $deletadas turma(s) deletada(s)\n\n";
    
    // 4. Verificação final
    echo "3️⃣  Verificando estado final...\n\n";
    
    $sqlVerify = "SELECT COUNT(*) as qtd FROM turmas t
                  JOIN dias d ON t.dia_id = d.id
                  WHERE d.data >= '2026-01-10'";
    
    $stmtVerify = $pdo->prepare($sqlVerify);
    $stmtVerify->execute();
    $result = $stmtVerify->fetch(PDO::FETCH_ASSOC);
    
    if ($result['qtd'] == 0) {
        echo "✅ SUCESSO: Todos os agendamentos foram removidos!\n\n";
        
        // Mostrar agendamentos restantes
        $sqlRest = "SELECT COUNT(*) as qtd FROM turmas t
                    JOIN dias d ON t.dia_id = d.id
                    WHERE d.data < '2026-01-10'";
        
        $stmtRest = $pdo->prepare($sqlRest);
        $stmtRest->execute();
        $restante = $stmtRest->fetch(PDO::FETCH_ASSOC);
        
        echo "📊 Agendamentos anteriores a 10/01/2026: " . $restante['qtd'] . "\n";
        echo "📊 Agendamentos a partir de 10/01/2026: 0\n\n";
    } else {
        echo "❌ ERRO: Ainda existem " . $result['qtd'] . " agendamento(s) após deleção!\n";
    }
    
    echo "╔════════════════════════════════════════════════════════════════╗\n";
    echo "║                       OPERAÇÃO CONCLUÍDA                       ║\n";
    echo "╚════════════════════════════════════════════════════════════════╝\n";
    
} catch (\Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
