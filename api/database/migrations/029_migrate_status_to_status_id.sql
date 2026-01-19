-- Migrar coluna status de tenant_planos_sistema para usar tabela status_contrato
-- IMPORTANTE: Esta migração substitui o ENUM status por um relacionamento com status_contrato

-- Passo 1: Adicionar nova coluna status_id
ALTER TABLE tenant_planos_sistema 
ADD COLUMN status_id INT NULL COMMENT 'FK para status_contrato' AFTER plano_sistema_id;

-- Passo 2: Migrar dados existentes
-- Mapear valores do ENUM para IDs da tabela status_contrato
UPDATE tenant_planos_sistema 
SET status_id = CASE 
    WHEN status = 'ativo' THEN 1      -- Ativo
    WHEN status = 'pendente' THEN 2   -- Pendente
    WHEN status = 'cancelado' THEN 3  -- Cancelado
    WHEN status = 'inativo' THEN 3    -- Mapear 'inativo' para 'Cancelado'
    ELSE 2                            -- Default: Pendente
END;

-- Passo 3: Tornar a coluna obrigatória
ALTER TABLE tenant_planos_sistema 
MODIFY COLUMN status_id INT NOT NULL;

-- Passo 4: Adicionar foreign key
ALTER TABLE tenant_planos_sistema 
ADD CONSTRAINT fk_tenant_planos_status 
FOREIGN KEY (status_id) REFERENCES status_contrato(id) ON DELETE RESTRICT;

-- Passo 5: Remover índices que usam a coluna status antiga (ignorar erros se não existirem)
-- idx_status
SET @exist := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'tenant_planos_sistema' AND index_name = 'idx_status');
SET @sqlstmt := IF(@exist > 0, 'ALTER TABLE tenant_planos_sistema DROP INDEX idx_status', 'SELECT "Index idx_status does not exist"');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- idx_tenant_status
SET @exist := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'tenant_planos_sistema' AND index_name = 'idx_tenant_status');
SET @sqlstmt := IF(@exist > 0, 'ALTER TABLE tenant_planos_sistema DROP INDEX idx_tenant_status', 'SELECT "Index idx_tenant_status does not exist"');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Passo 6: Remover coluna status_ativo_check antiga (se existir)
-- idx_tenant_ativo_unico
SET @exist := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'tenant_planos_sistema' AND index_name = 'idx_tenant_ativo_unico');
SET @sqlstmt := IF(@exist > 0, 'ALTER TABLE tenant_planos_sistema DROP INDEX idx_tenant_ativo_unico', 'SELECT "Index idx_tenant_ativo_unico does not exist"');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- status_ativo_check column
SET @exist := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'tenant_planos_sistema' AND column_name = 'status_ativo_check');
SET @sqlstmt := IF(@exist > 0, 'ALTER TABLE tenant_planos_sistema DROP COLUMN status_ativo_check', 'SELECT "Column status_ativo_check does not exist"');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Passo 7: Remover coluna status antiga
ALTER TABLE tenant_planos_sistema DROP COLUMN status;

-- Passo 8: Recriar índices usando status_id
CREATE INDEX idx_status_id ON tenant_planos_sistema (status_id);
CREATE INDEX idx_tenant_status_id ON tenant_planos_sistema (tenant_id, status_id);

-- Passo 9: Criar índice funcional para garantir apenas um contrato ativo por tenant
-- Usa coluna calculada: se status_id = 1 (Ativo), armazena tenant_id, senão NULL
ALTER TABLE tenant_planos_sistema 
ADD COLUMN status_ativo_check INT GENERATED ALWAYS AS (IF(status_id = 1, tenant_id, NULL)) STORED;

CREATE UNIQUE INDEX idx_tenant_ativo_unico ON tenant_planos_sistema (status_ativo_check);
