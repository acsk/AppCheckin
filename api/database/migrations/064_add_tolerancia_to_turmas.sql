-- Migration: Adicionar tolerâncias em turmas e remover horario_id
-- Data: 2026-01-20
-- Descrição: Move campos de tolerância de horarios para turmas e remove redundância

-- 1. Adicionar coluna tolerancia_minutos
ALTER TABLE turmas ADD COLUMN tolerancia_minutos INT NOT NULL DEFAULT 10 COMMENT 'Tolerância em minutos após o horário';

-- 2. Adicionar coluna tolerancia_antes_minutos
ALTER TABLE turmas ADD COLUMN tolerancia_antes_minutos INT NOT NULL DEFAULT 480 COMMENT 'Tolerância em minutos antes do horário (8 horas = 480 min)';

-- 3. Remover coluna horario_id (se existir)
ALTER TABLE turmas DROP COLUMN IF EXISTS horario_id;

-- 4. Criar índice para melhor performance nas buscas
CREATE INDEX idx_turmas_tolerancia ON turmas(tolerancia_minutos, tolerancia_antes_minutos);

SELECT 'Migration executada com sucesso! Tabela turmas atualizada com campos de tolerância.' as status;
