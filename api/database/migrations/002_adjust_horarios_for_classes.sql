-- Ajustar estrutura para sistema de turmas por hora

-- Adicionar colunas na tabela horarios
ALTER TABLE horarios 
ADD COLUMN limite_alunos INT NOT NULL DEFAULT 30 AFTER vagas,
ADD COLUMN tolerancia_minutos INT NOT NULL DEFAULT 10 AFTER limite_alunos,
ADD COLUMN horario_inicio TIME NOT NULL DEFAULT '06:00:00' AFTER hora,
ADD COLUMN horario_fim TIME NOT NULL DEFAULT '07:00:00' AFTER horario_inicio;

-- Adicionar índice para melhor performance
CREATE INDEX idx_horarios_dia_ativo ON horarios(dia_id, ativo);
CREATE INDEX idx_checkins_horario_usuario ON checkins(horario_id, usuario_id);

-- Adicionar coluna de timestamp real do check-in (momento exato que o aluno fez check-in)
ALTER TABLE checkins 
MODIFY COLUMN data_checkin DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP;

-- Remover coluna 'vagas' pois agora usaremos 'limite_alunos'
ALTER TABLE horarios DROP COLUMN vagas;
