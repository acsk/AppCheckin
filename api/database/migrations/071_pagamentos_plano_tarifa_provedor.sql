-- Valor líquido e tarifa do gateway (Asaas netValue). Não confundir com desconto do aluno.
-- Produção: aplicar manualmente no MySQL compartilhado (não usar php artisan migrate).

SET @dbname = DATABASE();

SET @col_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'pagamentos_plano' AND COLUMN_NAME = 'valor_liquido'
);
SET @sql = IF(
    @col_exists = 0,
    "ALTER TABLE pagamentos_plano ADD COLUMN valor_liquido DECIMAL(10,2) NULL COMMENT 'Valor líquido creditado pelo provedor (Asaas netValue)' AFTER valor",
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'pagamentos_plano' AND COLUMN_NAME = 'desconto_provedor'
);
SET @sql = IF(
    @col_exists = 0,
    "ALTER TABLE pagamentos_plano ADD COLUMN desconto_provedor DECIMAL(10,2) NULL DEFAULT 0.00 COMMENT 'Tarifa do provedor (value - netValue)' AFTER valor_liquido",
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
