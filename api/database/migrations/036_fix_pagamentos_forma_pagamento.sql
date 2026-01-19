-- Corrigir tabela pagamentos_contrato para usar tabela forma_pagamento
-- Remove ENUM e adiciona FK para forma_pagamento

-- 1. Remover a coluna ENUM forma_pagamento se ainda existir
SET @col_exists = (SELECT COUNT(*) 
    FROM information_schema.COLUMNS 
    WHERE TABLE_NAME = 'pagamentos_contrato' 
    AND COLUMN_NAME = 'forma_pagamento' 
    AND TABLE_SCHEMA = 'appcheckin');
    
SET @drop_col = IF(@col_exists > 0, 
    'ALTER TABLE pagamentos_contrato DROP COLUMN forma_pagamento', 
    'SELECT 1');
PREPARE stmt FROM @drop_col;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2. Garantir que forma_pagamento_id existe e está configurada corretamente
SET @col_exists = (SELECT COUNT(*) 
    FROM information_schema.COLUMNS 
    WHERE TABLE_NAME = 'pagamentos_contrato' 
    AND COLUMN_NAME = 'forma_pagamento_id' 
    AND TABLE_SCHEMA = 'appcheckin');
    
SET @add_col = IF(@col_exists = 0, 
    'ALTER TABLE pagamentos_contrato ADD COLUMN forma_pagamento_id INT NULL AFTER status_pagamento_id', 
    'SELECT 1');
PREPARE stmt FROM @add_col;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3. Adicionar foreign key para forma_pagamento
SET @fk_exists = (SELECT COUNT(*) 
    FROM information_schema.TABLE_CONSTRAINTS 
    WHERE CONSTRAINT_NAME = 'fk_pagamento_forma_pagamento' 
    AND TABLE_NAME = 'pagamentos_contrato' 
    AND TABLE_SCHEMA = 'appcheckin');
    
SET @add_fk = IF(@fk_exists = 0, 
    'ALTER TABLE pagamentos_contrato ADD CONSTRAINT fk_pagamento_forma_pagamento FOREIGN KEY (forma_pagamento_id) REFERENCES forma_pagamento(id)', 
    'SELECT 1');
PREPARE stmt FROM @add_fk;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 4. Adicionar índice se não existir
SET @idx_exists = (SELECT COUNT(*) 
    FROM information_schema.STATISTICS 
    WHERE TABLE_NAME = 'pagamentos_contrato' 
    AND INDEX_NAME = 'idx_forma_pagamento' 
    AND TABLE_SCHEMA = 'appcheckin');
    
SET @add_idx = IF(@idx_exists = 0, 
    'ALTER TABLE pagamentos_contrato ADD INDEX idx_forma_pagamento (forma_pagamento_id)', 
    'SELECT 1');
PREPARE stmt FROM @add_idx;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SELECT 'Migration 036 executada com sucesso! Tabela pagamentos_contrato corrigida para usar forma_pagamento.' as status;
