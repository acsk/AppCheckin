-- Remove tenant_id da tabela dias
-- Dias são compartilhados entre todos os tenants, não devem ter tenant_id

ALTER TABLE dias DROP FOREIGN KEY fk_dias_tenant;
ALTER TABLE dias DROP COLUMN tenant_id;
