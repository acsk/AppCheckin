<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Database\Connection;

try {
    echo "Conectando ao banco...\n";
    $pdo = Connection::getInstance()->getConnection();
    
    echo "Adicionando coluna modalidade_id à tabela wods...\n";
    $pdo->exec("ALTER TABLE wods ADD COLUMN modalidade_id INT NULL AFTER tenant_id");
    echo "✓ Coluna adicionada\n";
    
    echo "Adicionando constraint FK...\n";
    $pdo->exec("ALTER TABLE wods ADD CONSTRAINT fk_wods_modalidade FOREIGN KEY (modalidade_id) REFERENCES modalidades(id) ON DELETE SET NULL");
    echo "✓ Constraint adicionada\n";
    
    echo "Adicionando índice...\n";
    $pdo->exec("ALTER TABLE wods ADD INDEX idx_wods_modalidade (modalidade_id)");
    echo "✓ Índice adicionado\n";
    
    echo "\n✓ Migration 064 executada com sucesso!\n";
    
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "⚠ Coluna modalidade_id já existe\n";
    } elseif (strpos($e->getMessage(), 'Duplicate key name') !== false) {
        echo "⚠ Índice ou constraint já existe\n";
    } else {
        echo "✗ Erro: " . $e->getMessage() . "\n";
        exit(1);
    }
}
