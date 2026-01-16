-- Alterar a constraint única de wods para incluir modalidade_id
-- Permite múltiplos WODs na mesma data desde que sejam de modalidades diferentes

-- Remover constraint antiga (apenas tenant_id, data)
ALTER TABLE wods DROP KEY uq_tenant_data;

-- Adicionar nova constraint (tenant_id, data, modalidade_id)
ALTER TABLE wods ADD UNIQUE KEY uq_tenant_data_modalidade (tenant_id, data, modalidade_id);
