-- Adicionar campo max_alunos na tabela planos
ALTER TABLE planos ADD COLUMN max_alunos INT NULL COMMENT 'Capacidade máxima de alunos (NULL = ilimitado)' AFTER checkins_mensais;

-- Atualizar planos existentes com capacidade de alunos
UPDATE planos SET max_alunos = 50 WHERE nome LIKE '%Básico%';
UPDATE planos SET max_alunos = 100 WHERE nome LIKE '%Ilimitado%' OR nome LIKE '%Trimestral%';
UPDATE planos SET max_alunos = NULL WHERE nome LIKE '%Anual%';
