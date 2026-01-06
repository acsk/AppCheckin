-- Adicionar constraint para garantir apenas um contrato ativo por academia
-- Uma academia só pode ter um plano ativo por vez

-- Primeiro, vamos verificar se há violações existentes
-- Removendo contratos ativos duplicados, mantendo apenas o mais recente
DELETE t1 FROM tenant_planos_sistema t1
INNER JOIN tenant_planos_sistema t2 
WHERE t1.tenant_id = t2.tenant_id 
  AND t1.status = 'ativo' 
  AND t2.status = 'ativo'
  AND t1.id < t2.id;

-- Adicionar coluna virtual que é NULL para status não-ativo e tenant_id para status ativo
-- Esta coluna virtual permite criar um índice UNIQUE que só afeta registros ativos
ALTER TABLE tenant_planos_sistema 
ADD COLUMN status_ativo_check INT GENERATED ALWAYS AS (
  CASE WHEN status = 'ativo' THEN tenant_id ELSE NULL END
) VIRTUAL;

-- Criar índice UNIQUE na coluna virtual (NULL não conta para UNIQUE)
-- Isso garante que apenas um registro por tenant pode ter status = 'ativo'
CREATE UNIQUE INDEX uk_tenant_ativo 
ON tenant_planos_sistema (status_ativo_check);

-- Comentário explicativo
ALTER TABLE tenant_planos_sistema 
COMMENT = 'Contratos das academias com planos do sistema. Cada academia pode ter apenas um contrato ativo por vez, mas mantém histórico de contratos anteriores.';
