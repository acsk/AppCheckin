-- =====================================================
-- MIGRATION 040: Corrigir Constraint de Check-in
-- Problema: UNIQUE (usuario_id, horario_id) impede checkins recorrentes
-- Solução: Permitir 1 checkin por usuário por horário POR DIA
-- =====================================================

-- 1. Remover constraint antiga que impede checkins recorrentes
ALTER TABLE checkins DROP INDEX unique_usuario_horario;

-- 2. Adicionar coluna de data (apenas data, sem hora) para facilitar a constraint
ALTER TABLE checkins 
ADD COLUMN data_checkin_date DATE 
GENERATED ALWAYS AS (DATE(data_checkin)) STORED
COMMENT 'Data do checkin (sem hora) - gerada automaticamente'
AFTER data_checkin;

-- 3. Criar nova constraint: 1 checkin por usuário por horário POR DIA
ALTER TABLE checkins
ADD CONSTRAINT unique_usuario_horario_data 
UNIQUE (usuario_id, horario_id, data_checkin_date);

-- 4. Criar índice para performance em queries por data
CREATE INDEX idx_checkins_data ON checkins(data_checkin_date);

-- =====================================================
-- REGRA DE NEGÓCIO IMPLEMENTADA:
-- Um usuário pode fazer checkin no mesmo horário em dias diferentes
-- Exemplo: Pode fazer checkin às 18h toda segunda-feira
-- =====================================================

-- =====================================================
-- ALTERNATIVA (comentada): 1 checkin por dia independente do horário
-- Se preferir que o usuário só possa fazer 1 checkin por dia:
-- 
-- ALTER TABLE checkins DROP INDEX unique_usuario_horario_data;
-- ALTER TABLE checkins
-- ADD CONSTRAINT unique_usuario_data 
-- UNIQUE (usuario_id, data_checkin_date);
-- =====================================================
