-- Adicionar coluna modalidade_id à tabela wods
ALTER TABLE wods 
ADD COLUMN modalidade_id INT NULL AFTER tenant_id,
ADD CONSTRAINT fk_wods_modalidade FOREIGN KEY (modalidade_id) REFERENCES modalidades(id) ON DELETE SET NULL,
ADD INDEX idx_wods_modalidade (modalidade_id);
