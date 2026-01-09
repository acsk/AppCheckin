<?php
/**
 * Script para limpar turmas duplicadas com o mesmo horário
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
    
    echo "\n=== LIMPANDO TURMAS DUPLICADAS ===\n\n";
    
    // Encontrar turmas com o mesmo horário no mesmo dia
    echo "[1] Encontrando turmas duplicadas (mesmo horário no mesmo dia):\n";
    
    $stmt = $pdo->query("
        SELECT t1.dia_id, t1.horario_inicio, t1.horario_fim, COUNT(*) as total,
               GROUP_CONCAT(t1.id ORDER BY t1.id) as ids
        FROM turmas t1
        WHERE t1.ativo = 1
        GROUP BY t1.dia_id, t1.horario_inicio, t1.horario_fim
        HAVING COUNT(*) > 1
    ");
    
    $duplicatas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($duplicatas)) {
        echo "   ✅ Nenhuma turma duplicada encontrada!\n";
    } else {
        foreach ($duplicatas as $dup) {
            echo "   - Dia ID: {$dup['dia_id']}, Horário: {$dup['horario_inicio']} - {$dup['horario_fim']}, Quantidade: {$dup['total']}\n";
            echo "     IDs: {$dup['ids']}\n";
            
            // Manter apenas a primeira turma, deletar as outras
            $ids = array_map('intval', explode(',', $dup['ids']));
            $idsParaDeletar = array_slice($ids, 1); // Remove a primeira (mantém)
            
            if (!empty($idsParaDeletar)) {
                echo "     Deletando IDs: " . implode(', ', $idsParaDeletar) . "\n";
                
                $placeholders = implode(',', array_fill(0, count($idsParaDeletar), '?'));
                $stmt = $pdo->prepare("DELETE FROM turmas WHERE id IN ($placeholders)");
                $stmt->execute($idsParaDeletar);
                
                echo "     ✅ {$stmt->rowCount()} turmas deletadas\n";
            }
        }
    }
    
    echo "\n[2] Verificando turmas agora:\n";
    
    $stmt = $pdo->prepare("
        SELECT dia_id, horario_inicio, horario_fim, COUNT(*) as total
        FROM turmas
        WHERE ativo = 1
        GROUP BY dia_id, horario_inicio, horario_fim
        HAVING COUNT(*) > 1
        LIMIT 10
    ");
    $stmt->execute();
    $duplicatas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($duplicatas)) {
        echo "   ✅ Nenhuma duplicata encontrada!\n";
    } else {
        echo "   ❌ Ainda existem duplicatas:\n";
        foreach ($duplicatas as $dup) {
            echo "      - Dia {$dup['dia_id']}: {$dup['horario_inicio']} - {$dup['horario_fim']} ({$dup['total']} turmas)\n";
        }
    }
    
    echo "\n✅ Limpeza concluída!\n\n";
    
} catch (Exception $e) {
    echo "\n❌ ERRO:\n";
    echo $e->getMessage() . "\n\n";
    exit(1);
}
