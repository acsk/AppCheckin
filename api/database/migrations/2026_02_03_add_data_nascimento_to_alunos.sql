-- Adiciona coluna data_nascimento à tabela alunos
ALTER TABLE alunos
  ADD COLUMN data_nascimento DATE NULL AFTER cpf;
