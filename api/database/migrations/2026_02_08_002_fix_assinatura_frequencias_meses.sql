-- Migration: Corrigir coluna meses na tabela assinatura_frequencias
-- Preenche valores baseado no código caso estejam nulos
--
-- Execução:
-- mysql -u user -p database < 2026_02_08_002_fix_assinatura_frequencias_meses.sql

-- Adicionar coluna meses se não existir (bancos antigos)
SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_NAME = 'assinatura_frequencias' 
    AND COLUMN_NAME = 'meses'
    AND TABLE_SCHEMA = DATABASE()
);

SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE assinatura_frequencias ADD COLUMN meses INT NOT NULL DEFAULT 1 COMMENT ''Quantidade de meses do ciclo'' AFTER codigo',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Atualizar meses baseado no código
UPDATE assinatura_frequencias SET meses = 1 WHERE codigo = 'mensal' AND (meses IS NULL OR meses = 0);
UPDATE assinatura_frequencias SET meses = 2 WHERE codigo = 'bimestral' AND (meses IS NULL OR meses = 0);
UPDATE assinatura_frequencias SET meses = 3 WHERE codigo = 'trimestral' AND (meses IS NULL OR meses = 0);
UPDATE assinatura_frequencias SET meses = 4 WHERE codigo = 'quadrimestral' AND (meses IS NULL OR meses = 0);
UPDATE assinatura_frequencias SET meses = 6 WHERE codigo = 'semestral' AND (meses IS NULL OR meses = 0);
UPDATE assinatura_frequencias SET meses = 12 WHERE codigo = 'anual' AND (meses IS NULL OR meses = 0);

-- Garantir que nenhum meses fique nulo
UPDATE assinatura_frequencias SET meses = 1 WHERE meses IS NULL OR meses = 0;
