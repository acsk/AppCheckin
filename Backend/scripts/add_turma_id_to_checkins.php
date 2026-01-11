<?php

/**
 * Script para adicionar coluna turma_id à tabela checkins
 * php scripts/add_turma_id_to_checkins.php
 */

try {
    $db = require __DIR__ . '/../config/database.php';
    
    echo "🔄 Verificando coluna turma_id...\n";
    
    // Verificar se coluna já existe
    $stmt = $db->query("SHOW COLUMNS FROM checkins LIKE 'turma_id'");
    $exists = $stmt->fetch();
    
    if ($exists) {
        echo "✅ Coluna turma_id já existe!\n";
        exit(0);
    }
    
    echo "➕ Adicionando coluna turma_id...\n";
    
    // Adicionar coluna
    $db->exec("
        ALTER TABLE checkins 
        ADD COLUMN turma_id INT NULL AFTER usuario_id
    ");
    
    echo "✅ Coluna adicionada!\n";
    
    // Adicionar constraint (foreign key)
    echo "🔗 Adicionando Foreign Key...\n";
    
    $db->exec("
        ALTER TABLE checkins 
        ADD CONSTRAINT fk_checkins_turma 
        FOREIGN KEY (turma_id) REFERENCES turmas(id) ON DELETE CASCADE
    ");
    
    echo "✅ Foreign Key adicionada!\n";
    echo "✨ Migração concluída com sucesso!\n";
    
    // Verificar resultado
    $stmt = $db->query("DESCRIBE checkins");
    echo "\n📋 Estrutura da tabela checkins:\n";
    while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
        echo "  - {$row['Field']}: {$row['Type']}\n";
    }
    
} catch (\Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    exit(1);
}
