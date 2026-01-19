-- Migration: Ajustar estrutura de planos para checkins semanais
-- Uma modalidade pode ter múltiplos planos baseados em qtd de checkins/semana

-- 1. Adicionar coluna checkins_semanais aos planos
ALTER TABLE planos 
ADD COLUMN checkins_semanais INT NOT NULL DEFAULT 3 
COMMENT 'Quantidade de checkins permitidos por semana' 
AFTER modalidade_id;

-- 2. Remover/ajustar campos antigos que não fazem mais sentido
-- Manter duracao_dias para período de validade do plano
-- Remover checkins_mensais já que agora é semanal (se existir)
-- ALTER TABLE planos DROP COLUMN checkins_mensais; -- Descomentado se a coluna existir

-- 3. Atualizar planos existentes com valores padrão de checkins semanais
UPDATE planos SET checkins_semanais = 3 WHERE checkins_semanais IS NULL;

-- 4. Adicionar índice composto para buscar planos por modalidade
CREATE INDEX idx_modalidade_ativo ON planos(modalidade_id, ativo);

-- Comentários explicativos sobre a nova estrutura:
-- 
-- ESTRUTURA DE PLANOS:
-- - Uma MODALIDADE pode ter VÁRIOS PLANOS (ex: Musculação 2x, 3x, 5x semana)
-- - Cada plano define:
--   * checkins_semanais: quantos checkins por semana (2, 3, 5, ilimitado=999)
--   * valor: preço mensal do plano
--   * duracao_dias: validade (30, 90, 365 dias)
--
-- EXEMPLOS:
-- Modalidade "Musculação":
--   - Plano "2x por semana" (checkins_semanais=2, valor=99.90)
--   - Plano "3x por semana" (checkins_semanais=3, valor=129.90)
--   - Plano "5x por semana" (checkins_semanais=5, valor=169.90)
--   - Plano "Ilimitado" (checkins_semanais=999, valor=199.90)
--
-- Modalidade "Natação":
--   - Plano "2x por semana" (checkins_semanais=2, valor=149.90)
--   - Plano "3x por semana" (checkins_semanais=3, valor=189.90)

SELECT 'Migration executada: Planos agora baseados em checkins semanais' as status;
