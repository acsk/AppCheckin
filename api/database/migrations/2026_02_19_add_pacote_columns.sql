-- Migration: Adicionar colunas de pacote nas tabelas matriculas e pagamentos_plano
-- Data: 2026-02-19

-- Adicionar valor_rateado em matriculas (se não existir)
SET @exist_valor_rateado := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'matriculas'
      AND COLUMN_NAME = 'valor_rateado'
);

SET @sql_add_valor_rateado = IF(@exist_valor_rateado = 0,
    'ALTER TABLE matriculas ADD COLUMN valor_rateado DECIMAL(10,2) NULL COMMENT "Valor rateado quando matrícula faz parte de um pacote" AFTER valor',
    'SELECT "valor_rateado já existe em matriculas" AS info'
);

PREPARE stmt FROM @sql_add_valor_rateado;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Adicionar índice em valor_rateado (se não existir)
SET @exist_idx_valor_rateado := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'matriculas'
      AND INDEX_NAME = 'idx_matriculas_valor_rateado'
);

SET @sql_idx_valor_rateado = IF(@exist_idx_valor_rateado = 0,
    'CREATE INDEX idx_matriculas_valor_rateado ON matriculas(valor_rateado)',
    'SELECT "índice valor_rateado já existe" AS info'
);

PREPARE stmt FROM @sql_idx_valor_rateado;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Adicionar pacote_contrato_id em pagamentos_plano (se não existir)
SET @exist_pacote_contrato_pp := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'pagamentos_plano'
      AND COLUMN_NAME = 'pacote_contrato_id'
);

SET @sql_add_pacote_pp = IF(@exist_pacote_contrato_pp = 0,
    'ALTER TABLE pagamentos_plano ADD COLUMN pacote_contrato_id INT NULL COMMENT "ID do contrato de pacote quando pagamento é parte de um pacote" AFTER matricula_id',
    'SELECT "pacote_contrato_id já existe em pagamentos_plano" AS info'
);

PREPARE stmt FROM @sql_add_pacote_pp;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Adicionar índice em pacote_contrato_id de pagamentos_plano (se não existir)
SET @exist_idx_pacote_pp := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'pagamentos_plano'
      AND INDEX_NAME = 'idx_pagamentos_plano_pacote_contrato_id'
);

SET @sql_idx_pacote_pp = IF(@exist_idx_pacote_pp = 0,
    'CREATE INDEX idx_pagamentos_plano_pacote_contrato_id ON pagamentos_plano(pacote_contrato_id)',
    'SELECT "índice pacote_contrato_id já existe em pagamentos_plano" AS info'
);

PREPARE stmt FROM @sql_idx_pacote_pp;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Adicionar coluna status em pacote_beneficiarios (se não existir)
SET @exist_status_beneficiarios := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'pacote_beneficiarios'
      AND COLUMN_NAME = 'status'
);

SET @sql_add_status_beneficiarios = IF(@exist_status_beneficiarios = 0,
    'ALTER TABLE pacote_beneficiarios ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT "pendente" COMMENT "Status do beneficiário no pacote (pendente/ativo)" AFTER matricula_id',
    'SELECT "status já existe em pacote_beneficiarios" AS info'
);

PREPARE stmt FROM @sql_add_status_beneficiarios;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Adicionar coluna valor_rateado em pacote_beneficiarios (se não existir)
SET @exist_valor_benef := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'pacote_beneficiarios'
      AND COLUMN_NAME = 'valor_rateado'
);

SET @sql_add_valor_benef = IF(@exist_valor_benef = 0,
    'ALTER TABLE pacote_beneficiarios ADD COLUMN valor_rateado DECIMAL(10,2) NULL COMMENT "Valor rateado efetivamente pago para este beneficiário" AFTER valor_rateado',
    'SELECT "valor_rateado já existe em pacote_beneficiarios" AS info'
);

PREPARE stmt FROM @sql_add_valor_benef;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Log de sucesso
SELECT CONCAT(
    'Migration 2026_02_19_add_pacote_columns completed at ',
    NOW(),
    ' - Todas as colunas foram verificadas e adicionadas se necessário'
) AS migration_status;
