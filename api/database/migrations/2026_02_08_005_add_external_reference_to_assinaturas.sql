-- Migration: Adicionar campo external_reference à tabela assinaturas
-- Data: 2026-02-08
-- Descrição: Armazena o external_reference (MAT-xxx) gerado para pagamentos no MercadoPago

-- Adicionar coluna external_reference
ALTER TABLE assinaturas
ADD COLUMN external_reference VARCHAR(100) NULL COMMENT 'External reference do pagamento (MAT-xxx)' AFTER gateway_preference_id;

-- Índice para facilitar buscas por external_reference
CREATE INDEX idx_assinaturas_external_reference ON assinaturas(external_reference);
