-- =====================================================
-- CORRIGIR FOREIGN KEYS: Tabela pagamentos_plano
-- Data: 04/02/2026
-- Problema: FK para usuario_id existe mas não deveria
-- A tabela deve usar apenas aluno_id como FK
-- =====================================================

-- 1. Remover FK pagamentos_plano_ibfk_3 (usuario_id)
ALTER TABLE pagamentos_plano DROP FOREIGN KEY pagamentos_plano_ibfk_3;

-- 2. Remover coluna usuario_id se existir
ALTER TABLE pagamentos_plano DROP COLUMN IF EXISTS usuario_id;

-- 3. Verificar resultado
SELECT '✅ FK e coluna usuario_id removidas da tabela pagamentos_plano!' AS resultado;
