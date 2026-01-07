-- Migration: Remover coluna valor_mensalidade da tabela modalidades
-- A modalidade não tem preço fixo, apenas os planos têm valores

-- Verificar se a coluna existe antes de tentar remover
SET @exist = (SELECT COUNT(*) 
              FROM information_schema.COLUMNS 
              WHERE TABLE_SCHEMA = DATABASE() 
              AND TABLE_NAME = 'modalidades' 
              AND COLUMN_NAME = 'valor_mensalidade');

SET @sqlstmt = IF(@exist > 0, 
    'ALTER TABLE modalidades DROP COLUMN valor_mensalidade', 
    'SELECT "Column valor_mensalidade does not exist, skipping..."');

PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SELECT 'Migration executada: Removida coluna valor_mensalidade de modalidades' as status;
