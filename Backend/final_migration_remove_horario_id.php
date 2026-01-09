<?php
/**
 * Script para remover horario_id da tabela turmas
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
    
    echo "[INFO] Removendo constraint horario_id...\n";
    $pdo->exec("ALTER TABLE turmas DROP FOREIGN KEY turmas_ibfk_5");
    echo "✅ Constraint removida\n";
    
    echo "[INFO] Removendo coluna horario_id...\n";
    $pdo->exec("ALTER TABLE turmas DROP COLUMN horario_id");
    echo "✅ Coluna removida\n";
    
    echo "\n✅ Migração concluída com sucesso!\n";
    
} catch (Exception $e) {
    echo "\n❌ Erro ao aplicar migração:\n";
    echo $e->getMessage() . "\n";
    exit(1);
}
