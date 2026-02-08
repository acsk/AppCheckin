-- Migration: Ajustar constraint única em plano_ciclos
-- Permite múltiplos ciclos da mesma frequência: 1 com recorrência e 1 sem (avulso)
--
-- Execução:
-- mysql -u user -p database < 2026_02_08_003_adjust_plano_ciclos_unique_constraint.sql

-- =====================================================================
-- 1. Remover constraint antiga (plano_id + assinatura_frequencia_id)
-- =====================================================================

-- Tentar remover a constraint existente (pode ter nomes diferentes)
SET @constraint_exists = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS 
    WHERE TABLE_NAME = 'plano_ciclos' 
    AND CONSTRAINT_NAME = 'uk_plano_frequencia'
    AND TABLE_SCHEMA = DATABASE()
);

SET @sql = IF(@constraint_exists > 0, 
    'ALTER TABLE plano_ciclos DROP INDEX uk_plano_frequencia',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Tentar remover constraint com nome alternativo (uk_plano_tipo_ciclo)
SET @constraint_exists2 = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS 
    WHERE TABLE_NAME = 'plano_ciclos' 
    AND CONSTRAINT_NAME = 'uk_plano_tipo_ciclo'
    AND TABLE_SCHEMA = DATABASE()
);

SET @sql2 = IF(@constraint_exists2 > 0, 
    'ALTER TABLE plano_ciclos DROP INDEX uk_plano_tipo_ciclo',
    'SELECT 1'
);
PREPARE stmt2 FROM @sql2;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;

-- =====================================================================
-- 2. Criar nova constraint (plano_id + assinatura_frequencia_id + permite_recorrencia)
-- =====================================================================

-- Verificar se a nova constraint já existe
SET @new_constraint_exists = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS 
    WHERE TABLE_NAME = 'plano_ciclos' 
    AND CONSTRAINT_NAME = 'uk_plano_frequencia_recorrencia'
    AND TABLE_SCHEMA = DATABASE()
);

SET @sql3 = IF(@new_constraint_exists = 0, 
    'ALTER TABLE plano_ciclos ADD UNIQUE KEY uk_plano_frequencia_recorrencia (plano_id, assinatura_frequencia_id, permite_recorrencia)',
    'SELECT 1'
);
PREPARE stmt3 FROM @sql3;
EXECUTE stmt3;
DEALLOCATE PREPARE stmt3;

-- =====================================================================
-- 3. Adicionar comentário na coluna permite_recorrencia
-- =====================================================================

ALTER TABLE plano_ciclos 
MODIFY COLUMN permite_recorrencia TINYINT(1) DEFAULT 1 
COMMENT '1=assinatura recorrente, 0=pagamento avulso. Permite 1 de cada tipo por frequência.';
