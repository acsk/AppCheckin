<?php
/**
 * Verificar estado final das turmas
 */

require_once __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$host = $_ENV['DB_HOST'];
$dbname = $_ENV['DB_NAME'];
$user = $_ENV['DB_USER'];
$pass = $_ENV['DB_PASS'];

try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    echo "\n=== ESTADO FINAL - TURMAS ===\n\n";
    
    // Total de turmas
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM turmas WHERE ativo = 1");
    $total = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "[1] Total de turmas ativas: {$total['total']}\n\n";
    
    // Turmas no dia 09/01/2026 (dia_id 17)
    echo "[2] Turmas do dia 09/01/2026 (dia_id 17):\n";
    $stmt = $pdo->query("
        SELECT t.id, t.nome, t.horario_inicio, t.horario_fim, p.nome as professor_nome
        FROM turmas t
        LEFT JOIN professores p ON t.professor_id = p.id
        WHERE t.dia_id = 17 AND t.ativo = 1
        ORDER BY t.horario_inicio ASC
    ");
    $turmas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($turmas)) {
        echo "   Nenhuma turma encontrada\n";
    } else {
        foreach ($turmas as $turma) {
            echo "   - [{$turma['id']}] {$turma['nome']}: {$turma['horario_inicio']} - {$turma['horario_fim']} ({$turma['professor_nome']})\n";
        }
    }
    
    // Verificar se há duplicatas
    echo "\n[3] Verificando duplicatas (mesmo horário no mesmo dia):\n";
    $stmt = $pdo->query("
        SELECT t1.dia_id, t1.horario_inicio, t1.horario_fim, COUNT(*) as total
        FROM turmas t1
        WHERE t1.ativo = 1
        GROUP BY t1.dia_id, t1.horario_inicio, t1.horario_fim
        HAVING COUNT(*) > 1
        LIMIT 10
    ");
    $duplicatas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($duplicatas)) {
        echo "   ✅ Nenhuma duplicata encontrada!\n";
    } else {
        echo "   ❌ Duplicatas encontradas:\n";
        foreach ($duplicatas as $dup) {
            echo "      - Dia {$dup['dia_id']}: {$dup['horario_inicio']} - {$dup['horario_fim']} ({$dup['total']} turmas)\n";
        }
    }
    
    // Estatísticas
    echo "\n[4] Estatísticas por horário:\n";
    $stmt = $pdo->query("
        SELECT horario_inicio, horario_fim, COUNT(*) as quantidade
        FROM turmas
        WHERE ativo = 1
        GROUP BY horario_inicio, horario_fim
        ORDER BY horario_inicio ASC
        LIMIT 20
    ");
    $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($stats as $stat) {
        echo "   - {$stat['horario_inicio']} - {$stat['horario_fim']}: {$stat['quantidade']} turmas\n";
    }
    
    echo "\n✅ Verificação concluída!\n\n";
    
} catch (Exception $e) {
    echo "\n❌ ERRO:\n";
    echo $e->getMessage() . "\n\n";
    exit(1);
}
