-- Migration: Adicionar campos de pagamento ao pacote_contratos
SET @exist_payment_url := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'pacote_contratos'
      AND COLUMN_NAME = 'payment_url'
);
SET @sql_add_payment_url = IF(@exist_payment_url = 0,
    'ALTER TABLE pacote_contratos ADD COLUMN payment_url VARCHAR(500) NULL AFTER pagamento_id',
    'SELECT \"payment_url já existe\" AS info'
);
PREPARE stmt FROM @sql_add_payment_url;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exist_payment_preference := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'pacote_contratos'
      AND COLUMN_NAME = 'payment_preference_id'
);
SET @sql_add_payment_preference = IF(@exist_payment_preference = 0,
    'ALTER TABLE pacote_contratos ADD COLUMN payment_preference_id VARCHAR(100) NULL AFTER payment_url',
    'SELECT \"payment_preference_id já existe\" AS info'
);
PREPARE stmt FROM @sql_add_payment_preference;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
