-- =====================================================
-- MIGRATION 058: Ajustar Constraint de Check-in para Turma ID
-- =====================================================
-- Problema: UNIQUE (usuario_id, horario_id, data_checkin_date) 
--           não funciona quando horario_id é NULL
--           MySQL permite múltiplos NULL em colunas UNIQUE
--
-- Solução: Usar turma_id no lugar de horario_id
--          Tornar turma_id NOT NULL
--          Deixar horario_id como NULL (legacy/compatibilidade)
-- =====================================================

-- 1. Verificar se turma_id existe, se não adicionar
SET @col_exists = (
    SELECT COUNT(1) 
    FROM information_schema.columns 
    WHERE table_schema = DATABASE() 
    AND table_name = 'checkins' 
    AND column_name = 'turma_id'
);

SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE checkins ADD COLUMN turma_id INT NULL AFTER horario_id', 
    'SELECT "Coluna turma_id já existe" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2. Preencher turma_id baseado em horario_id (se existir)
-- Buscar turma relacionada ao horário do check-in
UPDATE checkins c
SET c.turma_id = (
    SELECT t.id 
    FROM turmas t
    WHERE t.horario_id = c.horario_id
    LIMIT 1
)
WHERE c.turma_id IS NULL AND c.horario_id IS NOT NULL;

-- 3. Remover constraint antiga que usa horario_id
ALTER TABLE checkins 
DROP INDEX IF EXISTS unique_usuario_horario_data;

-- 4. Tornar turma_id NOT NULL (pois será obrigatório)
-- Primeiro, verificar quantos registros têm turma_id NULL
-- Se houver muitos, pode ser necessário popular antes

-- Adicionar constraint NOT NULL com UPDATE prévia se necessário
UPDATE checkins SET turma_id = 1 WHERE turma_id IS NULL;

ALTER TABLE checkins 
MODIFY turma_id INT NOT NULL;

-- 5. Deixar horario_id como nullable (compatibilidade com sistema antigo)
-- Já é NULL, apenas documentamos
-- ALTER TABLE checkins MODIFY horario_id INT NULL;

-- 6. Criar nova constraint: 1 check-in por usuário por turma PER DIA
-- Isso garante que mesmo com turma_id, só haja 1 check-in por dia
ALTER TABLE checkins
ADD CONSTRAINT unique_usuario_turma_data 
UNIQUE (usuario_id, turma_id, data_checkin_date);

-- 7. Criar FK para turma_id se não existir
SET @fk_exists = (
    SELECT COUNT(1)
    FROM information_schema.referential_constraints
    WHERE constraint_schema = DATABASE()
    AND table_name = 'checkins'
    AND constraint_name = 'fk_checkins_turma'
);

SET @sql = IF(@fk_exists = 0,
    'ALTER TABLE checkins ADD CONSTRAINT fk_checkins_turma FOREIGN KEY (turma_id) REFERENCES turmas(id) ON DELETE CASCADE',
    'SELECT "FK já existe" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 8. Criar índice para performance em queries por turma
CREATE INDEX IF NOT EXISTS idx_checkins_turma ON checkins(turma_id);

-- =====================================================
-- REGRA DE NEGÓCIO IMPLEMENTADA:
-- Um usuário pode fazer check-in em turmas DIFERENTES no mesmo dia
-- MAS não pode fazer 2x check-in na MESMA turma no mesmo dia
-- =====================================================

-- =====================================================
-- VALIDAÇÃO SEMANAL (implementada no código da aplicação):
-- Um usuário está limitado a N check-ins por semana conforme seu plano
-- Isso é validado no nivel da aplicação via:
-- - Checkin::contarCheckinsNaSemana()
-- - Checkin::obterLimiteCheckinsPlano()
-- =====================================================

-- =====================================================
-- TESTE: Verificar a estrutura final
-- SELECT COLUMN_NAME, IS_NULLABLE, COLUMN_KEY, CONSTRAINT_NAME
-- FROM information_schema.columns
-- WHERE TABLE_NAME = 'checkins' AND TABLE_SCHEMA = DATABASE();
-- =====================================================
