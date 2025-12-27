-- Migration: Adicionar plano_id à tabela tenants
-- Data: 2025
-- Descrição: Associar academias a planos/contratos

-- Adicionar coluna plano_id na tabela tenants
ALTER TABLE tenants 
ADD COLUMN plano_id INT NULL AFTER endereco,
ADD COLUMN data_inicio_plano DATE NULL AFTER plano_id,
ADD COLUMN data_fim_plano DATE NULL AFTER data_inicio_plano;

-- Adicionar foreign key para planos (se a tabela planos existir)
-- Se a tabela planos ainda não existe, comente esta linha
-- ALTER TABLE tenants 
-- ADD CONSTRAINT fk_tenants_planos 
-- FOREIGN KEY (plano_id) REFERENCES planos(id) ON DELETE SET NULL;

-- Atualizar tenants existentes com um plano padrão (opcional)
-- UPDATE tenants SET plano_id = 1 WHERE plano_id IS NULL;
