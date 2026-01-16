<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Database\Connection;

try {
    $pdo = Connection::getInstance()->getConnection();
    
    echo "=== Verificando estrutura da tabela wods ===\n\n";
    
    $stmt = $pdo->query("SHOW COLUMNS FROM wods");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($columns as $col) {
        echo "{$col['Field']}\t{$col['Type']}\t{$col['Null']}\t{$col['Key']}\n";
    }
    
    echo "\n=== Verificando último WOD criado ===\n\n";
    
    $stmt = $pdo->query("SELECT id, tenant_id, modalidade_id, data, titulo, status FROM wods ORDER BY id DESC LIMIT 1");
    $wod = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($wod) {
        print_r($wod);
    } else {
        echo "Nenhum WOD encontrado\n";
    }
    
} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
}
