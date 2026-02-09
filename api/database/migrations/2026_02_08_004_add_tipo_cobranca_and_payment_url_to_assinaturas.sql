-- ============================================
-- Migration: Adicionar tipo_cobranca e payment_url na tabela assinaturas
-- Data: 2026-02-08
-- Descrição: 
--   - Permite registrar tanto pagamentos avulsos quanto recorrentes
--   - Armazena URL de pagamento para recuperação pelo aluno
-- ============================================

-- 1. Adicionar campo tipo_cobranca (se não existir)
SET @exist_tipo_cobranca = (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'assinaturas' AND COLUMN_NAME = 'tipo_cobranca');
SET @sql_tipo_cobranca = IF(@exist_tipo_cobranca = 0, 
    "ALTER TABLE assinaturas ADD COLUMN tipo_cobranca ENUM('avulso', 'recorrente') NOT NULL DEFAULT 'recorrente' AFTER metodo_pagamento_id",
    "SELECT 1");
PREPARE stmt FROM @sql_tipo_cobranca;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2. Adicionar campo payment_url (se não existir)
SET @exist_payment_url = (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'assinaturas' AND COLUMN_NAME = 'payment_url');
SET @sql_payment_url = IF(@exist_payment_url = 0, 
    "ALTER TABLE assinaturas ADD COLUMN payment_url VARCHAR(500) NULL AFTER gateway_assinatura_id",
    "SELECT 1");
PREPARE stmt FROM @sql_payment_url;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3. Adicionar campo gateway_preference_id (se não existir)
SET @exist_preference_id = (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'assinaturas' AND COLUMN_NAME = 'gateway_preference_id');
SET @sql_preference_id = IF(@exist_preference_id = 0, 
    "ALTER TABLE assinaturas ADD COLUMN gateway_preference_id VARCHAR(100) NULL AFTER gateway_assinatura_id",
    "SELECT 1");
PREPARE stmt FROM @sql_preference_id;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 4. Adicionar índice (se não existir)
SET @exist_idx = (SELECT COUNT(*) FROM information_schema.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'assinaturas' AND INDEX_NAME = 'idx_tipo_cobranca');
SET @sql_idx = IF(@exist_idx = 0, 
    "ALTER TABLE assinaturas ADD INDEX idx_tipo_cobranca (tipo_cobranca)",
    "SELECT 1");
PREPARE stmt FROM @sql_idx;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 5. Atualizar registros existentes
UPDATE assinaturas SET tipo_cobranca = 'recorrente' WHERE tipo_cobranca IS NULL;

-- ============================================
-- Verificação
-- ============================================
-- SELECT COLUMN_NAME, COLUMN_TYPE FROM information_schema.COLUMNS 
-- WHERE TABLE_NAME = 'assinaturas' 
-- AND COLUMN_NAME IN ('tipo_cobranca', 'payment_url', 'gateway_preference_id');
