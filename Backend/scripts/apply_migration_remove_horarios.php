<?php
/**
 * Script para aplicar a migração de remoção de horarios dependency
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
    
    echo "[INFO] Conectado ao banco de dados com sucesso\n";
    
    // Ler a migração
    $migrationPath = __DIR__ . '/database/migrations/021_remove_horarios_dependency.sql';
    $sql = file_get_contents($migrationPath);
    
    if ($sql === false) {
        throw new Exception("Erro ao ler arquivo de migração: {$migrationPath}");
    }
    
    echo "[INFO] Executando migração 021_remove_horarios_dependency.sql\n";
    
    // Executar as statements
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    foreach ($statements as $statement) {
        if (!empty($statement)) {
            echo "[EXEC] " . substr($statement, 0, 80) . "...\n";
            $pdo->exec($statement);
        }
    }
    
    echo "\n✅ Migração aplicada com sucesso!\n";
    
} catch (Exception $e) {
    echo "\n❌ Erro ao aplicar migração:\n";
    echo $e->getMessage() . "\n";
    exit(1);
}
