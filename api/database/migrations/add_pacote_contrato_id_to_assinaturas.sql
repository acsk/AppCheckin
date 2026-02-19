-- ============================================
-- MIGRATION: Adicionar pacote_contrato_id à tabela assinaturas
-- ============================================
-- 
-- Purpose: Armazenar ID do pacote na assinatura para recuperar
--          o contrato quando webhook de pagamento chega sem metadata
--
-- Date: 2026-02-18
-- ============================================

-- Verificar se coluna já existe (comentado, execute manualmente se quiser)
-- SELECT COUNT(*) as existe FROM information_schema.COLUMNS
-- WHERE TABLE_SCHEMA = DATABASE()
-- AND TABLE_NAME = 'assinaturas'
-- AND COLUMN_NAME = 'pacote_contrato_id';

-- Adicionar coluna (se não existir)
ALTER TABLE assinaturas 
ADD COLUMN pacote_contrato_id INT NULL DEFAULT NULL 
COMMENT 'ID do pacote para assinaturas recorrentes de pacotes' 
AFTER gateway_assinatura_id;

-- Criar índice para melhorar performance
CREATE INDEX idx_assinaturas_pacote_contrato_id 
ON assinaturas(pacote_contrato_id);

-- Verificar coluna adicionada
DESC assinaturas;

-- Confirmar sucesso
SELECT 'Migration concluída com sucesso!' as status;
