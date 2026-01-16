<?php

$host = 'mysql';
$port = 3306;
$user = 'root';
$password = 'root';
$database = 'appcheckin';

try {
    echo "Conectando ao banco...\n";
    $dsn = "mysql:host=$host;port=$port;dbname=$database";
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
    ]);
    
    echo "✓ Conectado ao banco\n\n";
    
    echo "Alterando constraint única de wods...\n";
    
    // Remover a constraint antiga
    echo "  Removendo constraint uq_tenant_data...\n";
    try {
        $pdo->exec("ALTER TABLE wods DROP KEY uq_tenant_data");
        echo "  ✓ Constraint antiga removida\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), "check that column/key exists") !== false) {
            echo "  ⚠ Constraint não encontrada (pode ter sido removida antes)\n";
        } else {
            throw $e;
        }
    }
    
    // Adicionar a nova constraint que inclui modalidade_id
    echo "  Adicionando constraint uq_tenant_data_modalidade...\n";
    $pdo->exec("ALTER TABLE wods ADD UNIQUE KEY uq_tenant_data_modalidade (tenant_id, data, modalidade_id)");
    echo "  ✓ Nova constraint adicionada\n";
    
    echo "\n✓ Migration 065 executada com sucesso!\n";
    echo "\nAgora é possível ter múltiplos WODs na mesma data desde que sejam de modalidades diferentes.\n";
    
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
        echo "⚠ Constraint já existe\n";
    } else {
        echo "✗ Erro: " . $e->getMessage() . "\n";
        exit(1);
    }
}
