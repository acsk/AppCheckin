-- Migration: 047 - Remove campos legados da tabela planos
-- Descrição: Remove campos checkins_mensais e max_alunos que não são mais utilizados

-- Remove coluna checkins_mensais
ALTER TABLE planos DROP COLUMN checkins_mensais;

-- Remove coluna max_alunos
ALTER TABLE planos DROP COLUMN max_alunos;
