-- Adicionar coluna 'atual' para distinguir planos atuais dos históricos
-- Planos com atual=true são oferecidos para novos contratos
-- Planos com atual=false são mantidos apenas para histórico de contratos antigos

ALTER TABLE planos 
ADD COLUMN atual BOOLEAN NOT NULL DEFAULT TRUE 
COMMENT 'Indica se o plano está disponível para novos contratos' 
AFTER ativo;

-- Criar índice para otimizar consultas de planos atuais e ativos
CREATE INDEX idx_planos_disponiveis ON planos(atual, ativo);

-- Atualizar todos os planos existentes para atual=true
UPDATE planos SET atual = TRUE WHERE ativo = TRUE;

-- Comentário explicativo
ALTER TABLE planos COMMENT = 'Planos de assinatura. Planos antigos são mantidos com atual=false para preservar histórico de contratos.';
