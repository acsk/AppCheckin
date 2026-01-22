-- Migration: Add password recovery fields to usuarios table
-- Descrição: Adiciona campos para recuperação de senha por email

ALTER TABLE usuarios 
ADD COLUMN password_reset_token VARCHAR(255) NULL DEFAULT NULL AFTER senha_hash,
ADD COLUMN password_reset_expires_at DATETIME NULL DEFAULT NULL AFTER password_reset_token,
ADD KEY idx_password_reset_token (password_reset_token);

-- Descrição do índice: Facilita busca rápida de tokens de reset

-- Verificar
SELECT COLUMN_NAME, DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'usuarios' AND TABLE_SCHEMA = DATABASE() ORDER BY ORDINAL_POSITION;
