<?php

try {
    $db = new PDO('mysql:host=127.0.0.1:3306;dbname=app_checkin', 'root', 'root', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    echo "✅ Conectado ao banco de dados\n";
    
    // Verifica se a coluna já existe
    $result = $db->query("SHOW COLUMNS FROM checkins LIKE 'turma_id'");
    if ($result->rowCount() > 0) {
        echo "✅ Coluna turma_id já existe\n";
        exit(0);
    }

    // Adiciona a coluna
    try {
        $db->exec("ALTER TABLE checkins ADD COLUMN turma_id INT NULL AFTER usuario_id");
        echo "✅ Coluna turma_id adicionada\n";
    } catch (Exception $e) {
        echo "❌ Erro ao adicionar coluna: " . $e->getMessage() . "\n";
        exit(1);
    }
    
    // Adiciona foreign key (se ainda não existe)
    try {
        $constraintExists = $db->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME = 'checkins' AND COLUMN_NAME = 'turma_id' AND REFERENCED_TABLE_NAME = 'turmas'");
        
        if ($constraintExists->rowCount() === 0) {
            $db->exec("ALTER TABLE checkins ADD CONSTRAINT fk_checkins_turma FOREIGN KEY (turma_id) REFERENCES turmas(id) ON DELETE CASCADE");
            echo "✅ Foreign key adicionada\n";
        } else {
            echo "✅ Foreign key já existe\n";
        }
    } catch (Exception $e) {
        echo "⚠️  Foreign key não adicionada (pode já existir): " . $e->getMessage() . "\n";
    }

    // Verificar final da estrutura
    $columns = $db->query("DESCRIBE checkins");
    $columnsArray = $columns->fetchAll();
    
    echo "\n📊 Estrutura atual de checkins:\n";
    foreach ($columnsArray as $col) {
        if (in_array($col['Field'], ['id', 'usuario_id', 'turma_id', 'horario_id'])) {
            echo "   - {$col['Field']}: {$col['Type']} {$col['Null']} " . ($col['Key'] ? "({$col['Key']})" : "") . "\n";
        }
    }
    
} catch (PDOException $e) {
    echo "❌ Erro de conexão: " . $e->getMessage() . "\n";
    exit(1);
}
