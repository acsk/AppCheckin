-- =====================================================
-- CORRIGIR FOREIGN KEYS: Tabela matriculas
-- Data: 04/02/2026
-- Problema: FK para usuario_id existe mas não deveria
-- A tabela deve usar apenas aluno_id como FK
-- =====================================================

-- 1. Remover FK matriculas_ibfk_2 (usuario_id)
ALTER TABLE matriculas DROP FOREIGN KEY matriculas_ibfk_2;

-- 2. Remover coluna usuario_id
ALTER TABLE matriculas DROP COLUMN usuario_id;

-- 3. Verificar resultado
SELECT '✅ FK e coluna usuario_id removidas com sucesso!' AS resultado;
