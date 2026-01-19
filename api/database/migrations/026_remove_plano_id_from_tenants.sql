-- Migration: Remover plano_id da tabela tenants
-- Data: 2026-01-05
-- Descrição: Remover relação direta entre Academia e Plano
--           A relação agora é feita através da tabela tenant_planos_sistema (contratos)

-- Verificar se existe foreign key antes de remover
-- Se houver FK, remover primeiro
ALTER TABLE tenants DROP FOREIGN KEY IF EXISTS fk_tenants_planos;

-- Remover colunas relacionadas a planos
ALTER TABLE tenants 
DROP COLUMN IF EXISTS plano_id,
DROP COLUMN IF EXISTS data_inicio_plano,
DROP COLUMN IF EXISTS data_fim_plano;
