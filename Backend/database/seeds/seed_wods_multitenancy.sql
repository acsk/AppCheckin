-- ============================================
-- SEED para WODs com TENANT_ID variável
-- ============================================
-- Use este arquivo se o seed anterior não funcionou
-- Ele cria WODs para TODOS os tenants existentes

-- Antes de executar, substitua:
-- @TENANT_ID pelos tenant_ids que quer popularconda
-- Use o comando abaixo para descobrir seus tenants:
-- SELECT DISTINCT tenant_id FROM usuarios;

-- ============================================
-- OPÇÃO 1: Ver quais tenants existem
-- ============================================
SELECT DISTINCT tenant_id FROM usuarios ORDER BY tenant_id;

-- ============================================
-- OPÇÃO 2: Limpar dados anteriores (opcional)
-- ============================================
-- DELETE FROM wod_resultados;
-- DELETE FROM wod_variacoes;
-- DELETE FROM wod_blocos;
-- DELETE FROM wods;

-- ============================================
-- OPÇÃO 3: Inserir WODs para TODOS os tenants
-- ============================================

-- Para cada tenant_id encontrado acima, execute:
-- Substitua "1" pelo seu tenant_id

-- Exemplo: Se seus tenants são 1, 2, 3:
-- Execute este bloco 3 vezes, mudando @TENANT_ID cada vez

SET @TENANT_ID = 1;  -- ← MUDE PARA SEU TENANT_ID

-- Inserir 5 WODs
INSERT INTO wods (tenant_id, data, titulo, descricao, status, criado_por, criado_em, atualizado_em) VALUES
(@TENANT_ID, '2026-01-15', 'WOD 15 de Janeiro', 'WOD com foco em força e resistência', 'published', 1, NOW(), NOW()),
(@TENANT_ID, '2026-01-16', 'WOD 16 de Janeiro', 'Dia de acessório e mobilidade', 'published', 1, NOW(), NOW()),
(@TENANT_ID, '2026-01-17', 'WOD 17 de Janeiro', 'Teste de Força - 1RM Back Squat', 'draft', 1, NOW(), NOW()),
(@TENANT_ID, '2026-01-18', 'WOD 18 de Janeiro', 'Chipper - Muitos movimentos', 'published', 1, NOW(), NOW()),
(@TENANT_ID, '2026-01-19', 'WOD 19 de Janeiro', 'Double Under Challenge', 'published', 1, NOW(), NOW());

-- Inserir Blocos do WOD 1
INSERT INTO wod_blocos (wod_id, ordem, tipo, titulo, conteudo, tempo_cap, criado_em, atualizado_em) VALUES
((SELECT id FROM wods WHERE tenant_id = @TENANT_ID AND data = '2026-01-15' LIMIT 1), 1, 'warmup', 'Aquecimento', '5 min easy bike\n10 air squats\n10 inchworms', '5 min', NOW(), NOW()),
((SELECT id FROM wods WHERE tenant_id = @TENANT_ID AND data = '2026-01-15' LIMIT 1), 2, 'strength', 'Back Squat', 'Find 1RM for the day\nThen 3x3 @ 85%', '20 min', NOW(), NOW()),
((SELECT id FROM wods WHERE tenant_id = @TENANT_ID AND data = '2026-01-15' LIMIT 1), 3, 'metcon', 'WOD', '20 min AMRAP:\n10 thrusters\n10 box jumps', '20 min', NOW(), NOW()),
((SELECT id FROM wods WHERE tenant_id = @TENANT_ID AND data = '2026-01-15' LIMIT 1), 4, 'cooldown', 'Resfriamento', 'Foam roll e alongamento', '5 min', NOW(), NOW());

-- Inserir Variações do WOD 1
INSERT INTO wod_variacoes (wod_id, nome, descricao, criado_em, atualizado_em) VALUES
((SELECT id FROM wods WHERE tenant_id = @TENANT_ID AND data = '2026-01-15' LIMIT 1), 'RX', '65/95 lb, sem modificações', NOW(), NOW()),
((SELECT id FROM wods WHERE tenant_id = @TENANT_ID AND data = '2026-01-15' LIMIT 1), 'Scaled', '45/65 lb, step-ups', NOW(), NOW()),
((SELECT id FROM wods WHERE tenant_id = @TENANT_ID AND data = '2026-01-15' LIMIT 1), 'Beginner', '35/45 lb, box step-ups', NOW(), NOW());

-- Inserir Blocos do WOD 2
INSERT INTO wod_blocos (wod_id, ordem, tipo, titulo, conteudo, tempo_cap, criado_em, atualizado_em) VALUES
((SELECT id FROM wods WHERE tenant_id = @TENANT_ID AND data = '2026-01-16' LIMIT 1), 1, 'warmup', 'Aquecimento', '5 min bike\n10 PVC passes', '5 min', NOW(), NOW()),
((SELECT id FROM wods WHERE tenant_id = @TENANT_ID AND data = '2026-01-16' LIMIT 1), 2, 'accessory', 'Acessório A', 'Incline Dumbbell Press 3x8', '15 min', NOW(), NOW()),
((SELECT id FROM wods WHERE tenant_id = @TENANT_ID AND data = '2026-01-16' LIMIT 1), 3, 'accessory', 'Acessório B', 'Banded Good Mornings 3x15', '12 min', NOW(), NOW()),
((SELECT id FROM wods WHERE tenant_id = @TENANT_ID AND data = '2026-01-16' LIMIT 1), 4, 'cooldown', 'Mobilidade', '10 min stretching routine', '10 min', NOW(), NOW());

-- Inserir Variações do WOD 2
INSERT INTO wod_variacoes (wod_id, nome, descricao, criado_em, atualizado_em) VALUES
((SELECT id FROM wods WHERE tenant_id = @TENANT_ID AND data = '2026-01-16' LIMIT 1), 'RX', 'As prescribed', NOW(), NOW()),
((SELECT id FROM wods WHERE tenant_id = @TENANT_ID AND data = '2026-01-16' LIMIT 1), 'Modified', 'Reduce weight 15 lb', NOW(), NOW());

-- Verificar dados inseridos
SELECT '=== WODs para tenant ' AS info, @TENANT_ID AS tenant_id;
SELECT id, titulo, data FROM wods WHERE tenant_id = @TENANT_ID;

SELECT '=== Blocos ===' AS info;
SELECT COUNT(*) FROM wod_blocos WHERE wod_id IN (SELECT id FROM wods WHERE tenant_id = @TENANT_ID);

SELECT '=== Variações ===' AS info;
SELECT COUNT(*) FROM wod_variacoes WHERE wod_id IN (SELECT id FROM wods WHERE tenant_id = @TENANT_ID);

-- ============================================
-- PARA USAR COM MÚLTIPLOS TENANTS:
-- ============================================
-- 1. Descubra seus tenant_ids:
--    SELECT DISTINCT tenant_id FROM usuarios;
--
-- 2. Para cada tenant_id, mude a variável @TENANT_ID e execute o bloco acima
--
-- 3. Exemplo com tenants 1, 2, 3:
--    Execute este arquivo 3 vezes, mudando @TENANT_ID = 1; para 2; e 3;
