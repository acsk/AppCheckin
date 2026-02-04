-- =====================================================
-- REMOVER TRIGGERS: matriculas com coluna status inexistente
-- Data: 04/02/2026
-- Problema: Triggers tentam acessar coluna 'status' que não existe
-- Erro: SQLSTATE[42S22]: Column not found: 1054 Unknown column 'status' in 'NEW'
-- =====================================================

-- Remover todos os triggers problemáticos da tabela matriculas
DROP TRIGGER IF EXISTS validar_matricula_ativa_unica_insert;
DROP TRIGGER IF EXISTS validar_matricula_ativa_unica_update;
DROP TRIGGER IF EXISTS update_matricula_vencida;
DROP TRIGGER IF EXISTS validar_matricula_ativa_unica;

-- Verificar se os triggers foram removidos
SELECT 'Triggers removidos com sucesso' AS resultado;
SELECT '' AS '';

SELECT 'Triggers restantes na tabela matriculas:' AS info;
SELECT TRIGGER_NAME, EVENT_OBJECT_TABLE, ACTION_TIMING, EVENT_MANIPULATION 
FROM information_schema.triggers 
WHERE TRIGGER_SCHEMA = DATABASE() 
AND EVENT_OBJECT_TABLE = 'matriculas';

SELECT '' AS '';
SELECT '✅ Agora tente criar a matrícula novamente' AS proximo_passo;
