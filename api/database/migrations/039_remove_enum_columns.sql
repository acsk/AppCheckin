-- =====================================================
-- MIGRATION 039: Remover colunas ENUM antigas
-- ⚠️ EXECUTAR SOMENTE APÓS VALIDAR QUE TUDO FUNCIONA
-- =====================================================

-- Remover tabelas de status não utilizadas antigas
DROP TABLE IF EXISTS status_conta;

-- Depois de confirmar que status_id funciona perfeitamente,
-- descomente as linhas abaixo para remover as colunas ENUM antigas:

-- ALTER TABLE contas_receber DROP COLUMN status;
-- ALTER TABLE matriculas DROP COLUMN status;
-- ALTER TABLE pagamentos DROP COLUMN status;
