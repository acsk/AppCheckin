-- Remover relacionamento de plano direto na tabela usuarios
-- A relação de plano agora é gerenciada apenas através de matriculas

-- Remover foreign key primeiro (se existir) - A FK correta é usuarios_ibfk_1 que aponta para planos
SET @fk_exists = (
    SELECT COUNT(1) 
    FROM information_schema.table_constraints 
    WHERE constraint_schema = DATABASE() 
    AND table_name = 'usuarios' 
    AND constraint_name = 'usuarios_ibfk_1'
    AND constraint_type = 'FOREIGN KEY'
);

SET @sql = IF(@fk_exists > 0, 
    'ALTER TABLE usuarios DROP FOREIGN KEY usuarios_ibfk_1', 
    'SELECT "FK usuarios_ibfk_1 não existe" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Remover coluna plano_id (se existir)
SET @col_exists = (
    SELECT COUNT(1) 
    FROM information_schema.columns 
    WHERE table_schema = DATABASE() 
    AND table_name = 'usuarios' 
    AND column_name = 'plano_id'
);

SET @sql = IF(@col_exists > 0, 
    'ALTER TABLE usuarios DROP COLUMN plano_id', 
    'SELECT "Coluna plano_id não existe" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Remover coluna data_vencimento_plano (se existir)
SET @col_exists = (
    SELECT COUNT(1) 
    FROM information_schema.columns 
    WHERE table_schema = DATABASE() 
    AND table_name = 'usuarios' 
    AND column_name = 'data_vencimento_plano'
);

SET @sql = IF(@col_exists > 0, 
    'ALTER TABLE usuarios DROP COLUMN data_vencimento_plano', 
    'SELECT "Coluna data_vencimento_plano não existe" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
