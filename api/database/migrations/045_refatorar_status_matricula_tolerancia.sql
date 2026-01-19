-- =====================================================
-- Migration: 045_refatorar_status_matricula_tolerancia.sql
-- Descrição: Refatora status_matricula com tolerância de dias
-- Data: 2026-01-08
-- =====================================================

-- 1. Adicionar campo dias_tolerancia na tabela status_matricula (se não existir)
SET @dbname = DATABASE();
SET @tablename = 'status_matricula';
SET @columnname = 'dias_tolerancia';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
   WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @columnname) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN ', @columnname, ' INT DEFAULT 0 COMMENT "Dias de tolerancia apos vencimento para mudar para este status"')
));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2. Adicionar campo automatico (se não existir)
SET @columnname = 'automatico';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
   WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @columnname) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN ', @columnname, ' TINYINT(1) DEFAULT 0 COMMENT "Se 1, o status e aplicado automaticamente pelo sistema"')
));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3. Limpar e recriar os status corretos
DELETE FROM status_matricula;

INSERT INTO status_matricula (id, codigo, nome, descricao, cor, icone, ordem, permite_checkin, ativo, dias_tolerancia, automatico) VALUES
(1, 'ativa', 'Ativa', 'Matrícula ativa - pagamento em dia', '#10b981', 'check-circle', 1, 1, 1, 0, 1),
(2, 'vencida', 'Vencida', 'Pagamento vencido - aguardando regularização', '#f59e0b', 'clock-alert', 2, 0, 1, 1, 1),
(3, 'cancelada', 'Cancelada', 'Matrícula cancelada por inadimplência', '#ef4444', 'close-circle', 3, 0, 1, 5, 1),
(4, 'finalizada', 'Finalizada', 'Matrícula encerrada pelo cliente ou academia', '#6b7280', 'flag-checkered', 4, 0, 1, NULL, 0);

-- 4. Resetar auto_increment
ALTER TABLE status_matricula AUTO_INCREMENT = 5;

-- =====================================================
-- Resultado esperado:
-- 
-- | id | codigo     | nome       | dias_tolerancia | automatico | permite_checkin |
-- |----|------------|------------|-----------------|------------|-----------------|
-- | 1  | ativa      | Ativa      | 0               | 1          | 1               |
-- | 2  | vencida    | Vencida    | 1               | 1          | 0               |
-- | 3  | cancelada  | Cancelada  | 5               | 1          | 0               |
-- | 4  | finalizada | Finalizada | NULL            | 0          | 0               |
--
-- Lógica de negócio:
-- - Ativa: Pagamento em dia (0 dias após vencimento)
-- - Vencida: 1+ dia após vencimento (ainda pode pagar e voltar a Ativa)
-- - Cancelada: 5+ dias após vencimento (sistema cancela automaticamente)
-- - Finalizada: Manual - cliente/academia encerrou o contrato
-- =====================================================

SELECT 'Migration 045 aplicada com sucesso!' as resultado;
