-- Migration: Adicionar campos do responsável à tabela tenants
-- Data: 2026-01-05
-- Descrição: Adicionar campos para armazenar dados do responsável pela academia

ALTER TABLE tenants 
ADD COLUMN responsavel_nome VARCHAR(255) NULL AFTER telefone,
ADD COLUMN responsavel_cpf VARCHAR(14) NULL AFTER responsavel_nome,
ADD COLUMN responsavel_telefone VARCHAR(20) NULL AFTER responsavel_cpf,
ADD COLUMN responsavel_email VARCHAR(255) NULL AFTER responsavel_telefone;
