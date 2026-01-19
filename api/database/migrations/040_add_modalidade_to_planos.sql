-- Adiciona associação entre planos e modalidades
-- Migration 040: Cada plano pertence a UMA modalidade específica

-- Adicionar coluna modalidade_id na tabela planos
ALTER TABLE planos 
ADD COLUMN modalidade_id INT NOT NULL AFTER tenant_id;

-- Associar planos existentes à primeira modalidade ativa de cada tenant
UPDATE planos p
INNER JOIN (
    SELECT tenant_id, MIN(id) as primeira_modalidade
    FROM modalidades
    WHERE ativo = 1
    GROUP BY tenant_id
) m ON m.tenant_id = p.tenant_id
SET p.modalidade_id = m.primeira_modalidade;

-- Adicionar a constraint de FK
ALTER TABLE planos
ADD CONSTRAINT fk_plano_modalidade 
    FOREIGN KEY (modalidade_id) 
    REFERENCES modalidades(id) 
    ON DELETE RESTRICT;

-- Criar índice para melhorar performance nas consultas
CREATE INDEX idx_plano_modalidade ON planos(modalidade_id);
