-- ============================================
-- SEED COMPLETO E PRONTO - WODs
-- ============================================
-- Execute este arquivo para preencher TODAS as tabelas
-- ⚠️ IMPORTANTE: Mude @TENANT_ID para o seu tenant_id ANTES de executar

SET @TENANT_ID = 5;  -- ← MUDE PARA SEU TENANT_ID

-- ============================================
-- LIMPAR DADOS ANTERIORES
-- ============================================
DELETE FROM wod_resultados WHERE tenant_id = @TENANT_ID;
DELETE FROM wod_variacoes WHERE wod_id IN (SELECT id FROM wods WHERE tenant_id = @TENANT_ID);
DELETE FROM wod_blocos WHERE wod_id IN (SELECT id FROM wods WHERE tenant_id = @TENANT_ID);
DELETE FROM wods WHERE tenant_id = @TENANT_ID;

-- ============================================
-- INSERIR WODs
-- ============================================
INSERT INTO wods (tenant_id, data, titulo, descricao, status, criado_por, created_at, updated_at) VALUES
(@TENANT_ID, '2026-01-15', 'WOD 15 de Janeiro', 'Força + Metcon com Thrusters e Box Jumps', 'published', 1, NOW(), NOW()),
(@TENANT_ID, '2026-01-16', 'WOD 16 de Janeiro', 'Dia de Acessório - Força de Ombro e Perna', 'published', 1, NOW(), NOW()),
(@TENANT_ID, '2026-01-17', 'WOD 17 de Janeiro', 'Teste de Força - 1RM Back Squat', 'draft', 1, NOW(), NOW()),
(@TENANT_ID, '2026-01-18', 'WOD 18 de Janeiro', 'Chipper - Muitos Movimentos, Pouco Repouso', 'published', 1, NOW(), NOW()),
(@TENANT_ID, '2026-01-19', 'WOD 19 de Janeiro', 'Double Under Challenge - 3 Rounds', 'published', 1, NOW(), NOW());

SET @WOD_1 = (SELECT id FROM wods WHERE tenant_id = @TENANT_ID AND data = '2026-01-15' LIMIT 1);
SET @WOD_2 = (SELECT id FROM wods WHERE tenant_id = @TENANT_ID AND data = '2026-01-16' LIMIT 1);
SET @WOD_3 = (SELECT id FROM wods WHERE tenant_id = @TENANT_ID AND data = '2026-01-17' LIMIT 1);
SET @WOD_4 = (SELECT id FROM wods WHERE tenant_id = @TENANT_ID AND data = '2026-01-18' LIMIT 1);
SET @WOD_5 = (SELECT id FROM wods WHERE tenant_id = @TENANT_ID AND data = '2026-01-19' LIMIT 1);

-- ============================================
-- INSERIR BLOCOS
-- ============================================
INSERT INTO wod_blocos (wod_id, ordem, tipo, titulo, conteudo, tempo_cap, created_at, updated_at) VALUES
(@WOD_1, 1, 'warmup', 'Aquecimento', '5 min easy bike\n10 air squats\n10 inchworms\n10 scapular pull-ups\n10 empty bar thrusters', '5 min', NOW(), NOW()),
(@WOD_1, 2, 'strength', 'Back Squat', 'Find 1RM for the day\nThen 3x3 @ 85%', '20 min', NOW(), NOW()),
(@WOD_1, 3, 'metcon', 'WOD', '20 min AMRAP:\n10 thrusters (65/95 lb)\n10 box jumps (20/24 inch)\n10 cal row', '20 min', NOW(), NOW()),
(@WOD_1, 4, 'cooldown', 'Resfriamento', '2 min foam roll quads\n2 min foam roll hamstrings\n1 min pigeon pose each side', '5 min', NOW(), NOW()),
(@WOD_2, 1, 'warmup', 'Aquecimento', '5 min bike\n2 min arm circles\n10 PVC passes', '5 min', NOW(), NOW()),
(@WOD_2, 2, 'accessory', 'Acessório A', 'Incline Dumbbell Press\n3x8 each arm\nRest 60 sec', '15 min', NOW(), NOW()),
(@WOD_2, 3, 'accessory', 'Acessório B', 'Banded Good Mornings\n3x15\nRest 45 sec', '12 min', NOW(), NOW()),
(@WOD_2, 4, 'accessory', 'Acessório C', 'Sled Push\n3x40m\nRest 90 sec', '15 min', NOW(), NOW()),
(@WOD_2, 5, 'cooldown', 'Mobilidade', '10 min stretching routine\nFocus: shoulders, hips, hamstrings', '10 min', NOW(), NOW()),
(@WOD_3, 1, 'warmup', 'Aquecimento', '5 min rower\n20 air squats\n15 PVC back squats\n10 empty bar back squats', '7 min', NOW(), NOW()),
(@WOD_3, 2, 'strength', 'Test Day - 1RM Back Squat', 'Find 1 Rep Max Back Squat\n\nStarting weight: 135 lb\nIncrement: 10-20 lb\nNote: Full depth required for every rep', '35 min', NOW(), NOW()),
(@WOD_3, 3, 'cooldown', 'Recovery', '5 min easy walking\nStatic stretching', '5 min', NOW(), NOW()),
(@WOD_4, 1, 'warmup', 'Aquecimento Dinâmico', '1 min bike\n10 arm circles\n10 air squats\n5 ring dips\n5 power cleans (empty bar)', '5 min', NOW(), NOW()),
(@WOD_4, 2, 'metcon', 'Chipper - 30 min time cap', 'For time:\n50 cal bike\n40 power cleans (65/95 lb)\n30 ring dips\n20 power cleans\n10 ring dips\n50 cal bike\n\nNote: No break between rounds', '30 min', NOW(), NOW()),
(@WOD_4, 3, 'cooldown', 'Cool Down', 'Walk and breathe\nStatic stretching as needed', '5 min', NOW(), NOW()),
(@WOD_5, 1, 'warmup', 'Aquecimento', '2 min easy run or bike\n10 burpees\n20 double unders (or 60 singles)\n10 push-ups', '5 min', NOW(), NOW()),
(@WOD_5, 2, 'metcon', 'Double Under Challenge', '3 rounds for time:\n30 double unders\n20 burpees\n10 power snatches (95/135 lb)\nRest 2 min between rounds', '18 min', NOW(), NOW()),
(@WOD_5, 3, 'cooldown', 'Resfriamento', '5 min walking\nScalp massage and breathing exercises', '5 min', NOW(), NOW());

-- ============================================
-- INSERIR VARIAÇÕES
-- ============================================
INSERT INTO wod_variacoes (wod_id, nome, descricao, created_at, updated_at) VALUES
(@WOD_1, 'RX', '65/95 lb, sem modificações', NOW(), NOW()),
(@WOD_1, 'Scaled', '45/65 lb, step-ups', NOW(), NOW()),
(@WOD_1, 'Beginner', '35/45 lb, box step-ups', NOW(), NOW()),
(@WOD_2, 'RX', 'As prescribed', NOW(), NOW()),
(@WOD_2, 'Modified', 'Reduce weight 15 lb', NOW(), NOW()),
(@WOD_3, 'RX', 'Full depth back squats', NOW(), NOW()),
(@WOD_3, 'Scaled', 'Front squat instead', NOW(), NOW()),
(@WOD_4, 'RX', '65/95 lb power cleans, ring dips', NOW(), NOW()),
(@WOD_4, 'Scaled', '45/65 lb, box dips', NOW(), NOW()),
(@WOD_4, 'Beginner', '35/45 lb, 15 box dips', NOW(), NOW()),
(@WOD_5, 'RX', '30 double unders, 95/135 lb', NOW(), NOW()),
(@WOD_5, 'Scaled', '60 single unders, 75/115 lb', NOW(), NOW()),
(@WOD_5, 'Beginner', '90 single unders, 55/85 lb', NOW(), NOW());

SET @VAR_1_1 = (SELECT id FROM wod_variacoes WHERE wod_id = @WOD_1 AND nome = 'RX' LIMIT 1);
SET @VAR_1_2 = (SELECT id FROM wod_variacoes WHERE wod_id = @WOD_1 AND nome = 'Scaled' LIMIT 1);
SET @VAR_2_1 = (SELECT id FROM wod_variacoes WHERE wod_id = @WOD_2 AND nome = 'RX' LIMIT 1);
SET @VAR_2_2 = (SELECT id FROM wod_variacoes WHERE wod_id = @WOD_2 AND nome = 'Modified' LIMIT 1);
SET @VAR_3_1 = (SELECT id FROM wod_variacoes WHERE wod_id = @WOD_3 AND nome = 'RX' LIMIT 1);
SET @VAR_4_1 = (SELECT id FROM wod_variacoes WHERE wod_id = @WOD_4 AND nome = 'RX' LIMIT 1);
SET @VAR_4_2 = (SELECT id FROM wod_variacoes WHERE wod_id = @WOD_4 AND nome = 'Scaled' LIMIT 1);
SET @VAR_5_1 = (SELECT id FROM wod_variacoes WHERE wod_id = @WOD_5 AND nome = 'RX' LIMIT 1);
SET @VAR_5_2 = (SELECT id FROM wod_variacoes WHERE wod_id = @WOD_5 AND nome = 'Scaled' LIMIT 1);

-- ============================================
-- INSERIR RESULTADOS
-- ============================================
INSERT INTO wod_resultados (tenant_id, wod_id, usuario_id, variacao_id, resultado, tempo_total, repeticoes, peso, nota, created_at, updated_at) VALUES
(@TENANT_ID, @WOD_1, 2, @VAR_1_1, '12+6', NULL, NULL, NULL, 'Great effort!', NOW(), NOW()),
(@TENANT_ID, @WOD_1, 3, @VAR_1_2, '10+4', NULL, NULL, NULL, 'First time!', NOW(), NOW()),
(@TENANT_ID, @WOD_1, 4, @VAR_1_1, '13+2', NULL, NULL, NULL, 'Personal best!', NOW(), NOW()),
(@TENANT_ID, @WOD_2, 2, @VAR_2_1, NULL, '38:45', NULL, NULL, 'Good pump', NOW(), NOW()),
(@TENANT_ID, @WOD_2, 3, @VAR_2_2, NULL, '41:20', NULL, NULL, 'Still challenging', NOW(), NOW()),
(@TENANT_ID, @WOD_3, 2, @VAR_3_1, NULL, NULL, NULL, 295.00, 'New PR! +10 lb', NOW(), NOW()),
(@TENANT_ID, @WOD_3, 4, @VAR_3_1, NULL, NULL, NULL, 315.00, 'Strong day', NOW(), NOW()),
(@TENANT_ID, @WOD_4, 2, @VAR_4_1, NULL, '24:13', NULL, NULL, 'Ring dips tough', NOW(), NOW()),
(@TENANT_ID, @WOD_4, 3, @VAR_4_2, NULL, '28:45', NULL, NULL, 'Box dips helped', NOW(), NOW()),
(@TENANT_ID, @WOD_4, 4, @VAR_4_1, NULL, '22:30', NULL, NULL, 'Fast!', NOW(), NOW()),
(@TENANT_ID, @WOD_5, 2, @VAR_5_1, '3', NULL, NULL, NULL, 'Improving', NOW(), NOW()),
(@TENANT_ID, @WOD_5, 3, @VAR_5_2, '3', NULL, NULL, NULL, 'Good effort', NOW(), NOW()),
(@TENANT_ID, @WOD_5, 4, @VAR_5_1, '3', NULL, NULL, NULL, 'Smooth', NOW(), NOW());

-- ============================================
-- VERIFICAÇÃO
-- ============================================
SELECT '✅ WODs inseridos' as status;
SELECT COUNT(*) as total FROM wods WHERE tenant_id = @TENANT_ID;

SELECT '✅ Blocos inseridos' as status;
SELECT COUNT(*) as total FROM wod_blocos WHERE wod_id IN (SELECT id FROM wods WHERE tenant_id = @TENANT_ID);

SELECT '✅ Variações inseridas' as status;
SELECT COUNT(*) as total FROM wod_variacoes WHERE wod_id IN (SELECT id FROM wods WHERE tenant_id = @TENANT_ID);

SELECT '✅ Resultados inseridos' as status;
SELECT COUNT(*) as total FROM wod_resultados WHERE wod_id IN (SELECT id FROM wods WHERE tenant_id = @TENANT_ID);

SELECT '✅ Pronto!' as status;
