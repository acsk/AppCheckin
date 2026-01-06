-- Remover colunas desnecessárias de tenant_planos_sistema
-- Os dados de vencimento e forma de pagamento devem estar apenas em pagamentos_contrato

-- Verificar e remover foreign key se existir
SET @constraint_exists = (SELECT COUNT(*) 
    FROM information_schema.TABLE_CONSTRAINTS 
    WHERE CONSTRAINT_NAME = 'fk_contrato_forma_pagamento' 
    AND TABLE_NAME = 'tenant_planos_sistema' 
    AND TABLE_SCHEMA = 'appcheckin');

SET @drop_fk = IF(@constraint_exists > 0, 
    'ALTER TABLE tenant_planos_sistema DROP FOREIGN KEY fk_contrato_forma_pagamento', 
    'SELECT 1');
PREPARE stmt FROM @drop_fk;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Remover coluna data_vencimento se existir
SET @col_exists = (SELECT COUNT(*) 
    FROM information_schema.COLUMNS 
    WHERE TABLE_NAME = 'tenant_planos_sistema' 
    AND COLUMN_NAME = 'data_vencimento' 
    AND TABLE_SCHEMA = 'appcheckin');
SET @drop_col = IF(@col_exists > 0, 
    'ALTER TABLE tenant_planos_sistema DROP COLUMN data_vencimento', 
    'SELECT 1');
PREPARE stmt FROM @drop_col;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Remover coluna forma_pagamento se existir
SET @col_exists = (SELECT COUNT(*) 
    FROM information_schema.COLUMNS 
    WHERE TABLE_NAME = 'tenant_planos_sistema' 
    AND COLUMN_NAME = 'forma_pagamento' 
    AND TABLE_SCHEMA = 'appcheckin');
SET @drop_col = IF(@col_exists > 0, 
    'ALTER TABLE tenant_planos_sistema DROP COLUMN forma_pagamento', 
    'SELECT 1');
PREPARE stmt FROM @drop_col;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Remover coluna forma_pagamento_id se existir
SET @col_exists = (SELECT COUNT(*) 
    FROM information_schema.COLUMNS 
    WHERE TABLE_NAME = 'tenant_planos_sistema' 
    AND COLUMN_NAME = 'forma_pagamento_id' 
    AND TABLE_SCHEMA = 'appcheckin');
SET @drop_col = IF(@col_exists > 0, 
    'ALTER TABLE tenant_planos_sistema DROP COLUMN forma_pagamento_id', 
    'SELECT 1');
PREPARE stmt FROM @drop_col;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SELECT 'Migration 035 executada com sucesso! Colunas removidas de tenant_planos_sistema.' as status;
