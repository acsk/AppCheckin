<?php
/**
 * Script para verificar o estado da tabela turmas
 */

require_once __DIR__ . '/vendor/autoload.php';

// Carregar variáveis de ambiente
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Conectar ao banco
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
    
    echo "[INFO] Estrutura da tabela turmas:\n\n";
    
    // Obter informações das colunas
    $stmt = $pdo->query("DESCRIBE turmas");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($columns as $col) {
        echo sprintf("%-20s %-15s %s\n", $col['Field'], $col['Type'], $col['Null'] === 'YES' ? 'NULL' : 'NOT NULL');
    }
    
    echo "\n[INFO] Constraints:\n";
    $stmt = $pdo->query("SELECT CONSTRAINT_NAME, COLUMN_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME = 'turmas' AND TABLE_SCHEMA = '{$dbname}'");
    $constraints = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($constraints as $con) {
        echo "- {$con['CONSTRAINT_NAME']} ({$con['COLUMN_NAME']})\n";
    }
    
} catch (Exception $e) {
    echo "❌ Erro:\n";
    echo $e->getMessage() . "\n";
    exit(1);
}
