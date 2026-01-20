-- =====================================================
-- FIX: Remover TRIGGER quebrado que referencia função deletada
-- =====================================================
-- O trigger checkins_before_insert_tenant estava tentando chamar
-- a função get_tenant_id_from_usuario() que foi removida do banco.
-- Isso estava causando erro ao inserir novos checkins.
-- 
-- Solução: Remover o trigger (tenant_id será preenchido pela aplicação PHP)
-- =====================================================

DROP TRIGGER IF EXISTS `checkins_before_insert_tenant`;

-- =====================================================
-- Verificação: Confirmar que o trigger foi removido
-- =====================================================
-- SELECT TRIGGER_NAME, TRIGGER_SCHEMA FROM INFORMATION_SCHEMA.TRIGGERS 
-- WHERE TRIGGER_NAME = 'checkins_before_insert_tenant';
