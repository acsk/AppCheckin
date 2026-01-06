-- Corrigir migração de status
-- A coluna status_id já existe mas precisa ser populada e a coluna status precisa ser removida

-- Passo 1: Popular status_id com base no status atual
UPDATE tenant_planos_sistema 
SET status_id = CASE 
    WHEN status = 'ativo' THEN 1
    WHEN status = 'pendente' THEN 2
    WHEN status = 'cancelado' THEN 3
    WHEN status = 'inativo' THEN 3
    ELSE 2
END;

-- Passo 2: Remover a coluna status_ativo_check antiga
ALTER TABLE tenant_planos_sistema DROP COLUMN status_ativo_check;

-- Passo 3: Remover índices da coluna status antiga
ALTER TABLE tenant_planos_sistema DROP INDEX idx_tenant_status;
ALTER TABLE tenant_planos_sistema DROP INDEX idx_status;
ALTER TABLE tenant_planos_sistema DROP INDEX uk_tenant_ativo;

-- Passo 4: Remover coluna status
ALTER TABLE tenant_planos_sistema DROP COLUMN status;

-- Passo 5: Adicionar foreign key para status_id
ALTER TABLE tenant_planos_sistema 
ADD CONSTRAINT fk_tenant_planos_status_contrato 
FOREIGN KEY (status_id) REFERENCES status_contrato(id) ON DELETE RESTRICT;

-- Passo 6: Criar nova coluna calculada para garantir apenas um contrato ativo
ALTER TABLE tenant_planos_sistema 
ADD COLUMN status_ativo_check INT GENERATED ALWAYS AS (IF(status_id = 1, tenant_id, NULL)) STORED;

-- Passo 7: Criar índice único
CREATE UNIQUE INDEX idx_tenant_ativo_unico ON tenant_planos_sistema (status_ativo_check);

-- Passo 8: Adicionar novos índices
CREATE INDEX idx_status_id ON tenant_planos_sistema (status_id);
CREATE INDEX idx_tenant_status_id ON tenant_planos_sistema (tenant_id, status_id);
