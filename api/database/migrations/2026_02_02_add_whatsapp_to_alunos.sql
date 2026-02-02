-- Adiciona coluna whatsapp à tabela alunos
ALTER TABLE alunos
  ADD COLUMN whatsapp VARCHAR(32) NULL AFTER telefone;
