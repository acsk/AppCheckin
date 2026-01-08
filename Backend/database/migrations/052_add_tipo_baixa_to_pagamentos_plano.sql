-- Adicionar campo tipo_baixa_id à tabela pagamentos_plano
ALTER TABLE pagamentos_plano
ADD COLUMN tipo_baixa_id INT NULL COMMENT 'Tipo de baixa do pagamento' AFTER baixado_por,
ADD FOREIGN KEY fk_tipo_baixa (tipo_baixa_id) REFERENCES tipos_baixa(id) ON DELETE SET NULL,
ADD INDEX idx_tipo_baixa (tipo_baixa_id);
