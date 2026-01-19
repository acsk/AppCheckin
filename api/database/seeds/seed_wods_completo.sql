-- ============================================
-- SEED COMPLETO: WODs + Blocos + Variações + Resultados
-- ============================================
-- ⚠️ IMPORTANTE: Mude @TENANT_ID para o seu tenant_id ANTES de executar!

SET @TENANT_ID = 1;  -- ← MUDE PARA SEU TENANT_ID

-- ============================================
-- 1. LIMPAR DADOS ANTERIORES (opcional)
-- ============================================
-- DELETE FROM wod_resultados WHERE wod_id IN (SELECT id FROM wods WHERE tenant_id = @TENANT_ID);
-- DELETE FROM wod_variacoes WHERE wod_id IN (SELECT id FROM wods WHERE tenant_id = @TENANT_ID);
-- DELETE FROM wod_blocos WHERE wod_id IN (SELECT id FROM wods WHERE tenant_id = @TENANT_ID);
-- DELETE FROM wods WHERE tenant_id = @TENANT_ID;

-- ============================================
-- 2. INSERIR WODs (Tabela Principal)
-- ============================================

INSERT INTO wods (tenant_id, data, titulo, descricao, status, criado_por, criado_em, atualizado_em) VALUES
(@TENANT_ID, '2026-01-15', 'WOD 15 de Janeiro', 'WOD com foco em força e resistência cardiovascular', 'published', 1, NOW(), NOW()),
(@TENANT_ID, '2026-01-16', 'WOD 16 de Janeiro', 'Dia de acessório e mobilidade', 'published', 1, NOW(), NOW()),
(@TENANT_ID, '2026-01-17', 'WOD 17 de Janeiro', 'Teste de Força - 1RM Back Squat', 'draft', 1, NOW(), NOW()),
(@TENANT_ID, '2026-01-18', 'WOD 18 de Janeiro', 'Chipper - Muitos movimentos, pouco repouso', 'published', 1, NOW(), NOW()),
(@TENANT_ID, '2026-01-19', 'WOD 19 de Janeiro', 'Double Under Challenge', 'published', 1, NOW(), NOW());

-- Guardar os IDs dos WODs criados
SET @WOD_1 = (SELECT id FROM wods WHERE tenant_id = @TENANT_ID AND data = '2026-01-15' LIMIT 1);
SET @WOD_2 = (SELECT id FROM wods WHERE tenant_id = @TENANT_ID AND data = '2026-01-16' LIMIT 1);
SET @WOD_3 = (SELECT id FROM wods WHERE tenant_id = @TENANT_ID AND data = '2026-01-17' LIMIT 1);
SET @WOD_4 = (SELECT id FROM wods WHERE tenant_id = @TENANT_ID AND data = '2026-01-18' LIMIT 1);
SET @WOD_5 = (SELECT id FROM wods WHERE tenant_id = @TENANT_ID AND data = '2026-01-19' LIMIT 1);

-- ============================================
-- 3. INSERIR BLOCOS DO WOD 1
-- ============================================

INSERT INTO wod_blocos (wod_id, ordem, tipo, titulo, conteudo, tempo_cap, criado_em, atualizado_em) VALUES
(@WOD_1, 1, 'warmup', 'Aquecimento', '5 min easy bike\n10 air squats\n10 inchworms\n10 scapular pull-ups\n10 empty bar thrusters', '5 min', NOW(), NOW()),
(@WOD_1, 2, 'strength', 'Back Squat', 'Find 1RM for the day\nThen 3x3 @ 85%', '20 min', NOW(), NOW()),
(@WOD_1, 3, 'metcon', 'WOD', '20 min AMRAP:\n10 thrusters (65/95 lb)\n10 box jumps (20/24 inch)\n10 cal row', '20 min', NOW(), NOW()),
(@WOD_1, 4, 'cooldown', 'Resfriamento', '2 min foam roll quads\n2 min foam roll hamstrings\n1 min pigeon pose each side', '5 min', NOW(), NOW());

-- ============================================
-- 4. INSERIR BLOCOS DO WOD 2
-- ============================================

INSERT INTO wod_blocos (wod_id, ordem, tipo, titulo, conteudo, tempo_cap, criado_em, atualizado_em) VALUES
(@WOD_2, 1, 'warmup', 'Aquecimento', '5 min bike\n2 min arm circles and shoulder dislocations\n10 PVC pipe pass throughs\n10 reverse snow angels', '5 min', NOW(), NOW()),
(@WOD_2, 2, 'accessory', 'Acessório A', 'Incline Dumbbell Press\n3x8 each arm\nRest 60 sec', '15 min', NOW(), NOW()),
(@WOD_2, 3, 'accessory', 'Acessório B', 'Banded Good Mornings\n3x15\nRest 45 sec', '12 min', NOW(), NOW()),
(@WOD_2, 4, 'accessory', 'Acessório C', 'Sled Push\n3x40m\nRest 90 sec', '15 min', NOW(), NOW()),
(@WOD_2, 5, 'cooldown', 'Mobilidade', '10 min stretching routine\nFocus: shoulders, hips, hamstrings', '10 min', NOW(), NOW());

-- ============================================
-- 5. INSERIR BLOCOS DO WOD 3
-- ============================================

INSERT INTO wod_blocos (wod_id, ordem, tipo, titulo, conteudo, tempo_cap, criado_em, atualizado_em) VALUES
(@WOD_3, 1, 'warmup', 'Aquecimento', '5 min rower\n20 air squats\n15 PVC back squats\n10 empty bar back squats', '7 min', NOW(), NOW()),
(@WOD_3, 2, 'strength', 'Test Day - 1RM Back Squat', 'Find 1 Rep Max Back Squat\n\nStarting weight: 135 lb\nIncrement: 10-20 lb\n\nNote: Full depth required for every rep', '35 min', NOW(), NOW()),
(@WOD_3, 3, 'cooldown', 'Recovery', '5 min easy walking\nStatic stretching', '5 min', NOW(), NOW());

-- ============================================
-- 6. INSERIR BLOCOS DO WOD 4
-- ============================================

INSERT INTO wod_blocos (wod_id, ordem, tipo, titulo, conteudo, tempo_cap, criado_em, atualizado_em) VALUES
(@WOD_4, 1, 'warmup', 'Aquecimento Dinâmico', '1 min bike\n10 arm circles\n10 air squats\n5 ring dips\n5 power cleans (empty bar)', '5 min', NOW(), NOW()),
(@WOD_4, 2, 'metcon', 'Chipper - 30 min time cap', 'For time:\n50 cal bike\n40 power cleans (65/95 lb)\n30 ring dips\n20 power cleans\n10 ring dips\n50 cal bike', '30 min', NOW(), NOW()),
(@WOD_4, 3, 'cooldown', 'Cool Down', 'Walk and breathe\nStatic stretching as needed', '5 min', NOW(), NOW());

-- ============================================
-- 7. INSERIR BLOCOS DO WOD 5
-- ============================================

INSERT INTO wod_blocos (wod_id, ordem, tipo, titulo, conteudo, tempo_cap, criado_em, atualizado_em) VALUES
(@WOD_5, 1, 'warmup', 'Aquecimento', '2 min easy run or bike\n10 burpees\n20 double unders (or 60 singles)\n10 push-ups', '5 min', NOW(), NOW()),
(@WOD_5, 2, 'metcon', 'Double Under Challenge', '3 rounds for time:\n30 double unders\n20 burpees\n10 power snatches (95/135 lb)\n\nRest 2 min between rounds', '18 min', NOW(), NOW()),
(@WOD_5, 3, 'cooldown', 'Resfriamento', '5 min walking\nScalp massage and breathing exercises', '5 min', NOW(), NOW());

-- ============================================
-- 8. INSERIR VARIAÇÕES DO WOD 1
-- ============================================

INSERT INTO wod_variacoes (wod_id, nome, descricao, criado_em, atualizado_em) VALUES
(@WOD_1, 'RX', '65/95 lb thrusters, 20/24 inch box jumps, no modifications', NOW(), NOW()),
(@WOD_1, 'Scaled', '45/65 lb thrusters, 18/20 inch box jumps or step ups', NOW(), NOW()),
(@WOD_1, 'Beginner', '35/45 lb, box step-ups instead of jumps', NOW(), NOW());

-- Guardar IDs das variações
SET @VAR_1_RX = (SELECT id FROM wod_variacoes WHERE wod_id = @WOD_1 AND nome = 'RX' LIMIT 1);
SET @VAR_1_SCALED = (SELECT id FROM wod_variacoes WHERE wod_id = @WOD_1 AND nome = 'Scaled' LIMIT 1);
SET @VAR_1_BEG = (SELECT id FROM wod_variacoes WHERE wod_id = @WOD_1 AND nome = 'Beginner' LIMIT 1);

-- ============================================
-- 9. INSERIR VARIAÇÕES DO WOD 2
-- ============================================

INSERT INTO wod_variacoes (wod_id, nome, descricao, criado_em, atualizado_em) VALUES
(@WOD_2, 'RX', 'As prescribed', NOW(), NOW()),
(@WOD_2, 'Modified', 'Reduce weight on dumbbells by 10-15 lb', NOW(), NOW());

SET @VAR_2_RX = (SELECT id FROM wod_variacoes WHERE wod_id = @WOD_2 AND nome = 'RX' LIMIT 1);
SET @VAR_2_MOD = (SELECT id FROM wod_variacoes WHERE wod_id = @WOD_2 AND nome = 'Modified' LIMIT 1);

-- ============================================
-- 10. INSERIR VARIAÇÕES DO WOD 3
-- ============================================

INSERT INTO wod_variacoes (wod_id, nome, descricao, criado_em, atualizado_em) VALUES
(@WOD_3, 'RX', 'Full depth back squats', NOW(), NOW()),
(@WOD_3, 'Scaled', 'Find 1RM Front Squat instead', NOW(), NOW());

SET @VAR_3_RX = (SELECT id FROM wod_variacoes WHERE wod_id = @WOD_3 AND nome = 'RX' LIMIT 1);
SET @VAR_3_SCALED = (SELECT id FROM wod_variacoes WHERE wod_id = @WOD_3 AND nome = 'Scaled' LIMIT 1);

-- ============================================
-- 11. INSERIR VARIAÇÕES DO WOD 4
-- ============================================

INSERT INTO wod_variacoes (wod_id, nome, descricao, criado_em, atualizado_em) VALUES
(@WOD_4, 'RX', '65/95 lb power cleans, ring dips', NOW(), NOW()),
(@WOD_4, 'Scaled', '45/65 lb power cleans, box-assisted ring dips', NOW(), NOW()),
(@WOD_4, 'Beginner', '35/45 lb, 15 box dips instead of ring dips', NOW(), NOW());

SET @VAR_4_RX = (SELECT id FROM wod_variacoes WHERE wod_id = @WOD_4 AND nome = 'RX' LIMIT 1);
SET @VAR_4_SCALED = (SELECT id FROM wod_variacoes WHERE wod_id = @WOD_4 AND nome = 'Scaled' LIMIT 1);
SET @VAR_4_BEG = (SELECT id FROM wod_variacoes WHERE wod_id = @WOD_4 AND nome = 'Beginner' LIMIT 1);

-- ============================================
-- 12. INSERIR VARIAÇÕES DO WOD 5
-- ============================================

INSERT INTO wod_variacoes (wod_id, nome, descricao, criado_em, atualizado_em) VALUES
(@WOD_5, 'RX', '30 double unders per round, 95/135 lb power snatches', NOW(), NOW()),
(@WOD_5, 'Scaled', '60 single unders per round, 75/115 lb power snatches', NOW(), NOW()),
(@WOD_5, 'Beginner', '90 single unders per round, 55/85 lb power snatches', NOW(), NOW());

SET @VAR_5_RX = (SELECT id FROM wod_variacoes WHERE wod_id = @WOD_5 AND nome = 'RX' LIMIT 1);
SET @VAR_5_SCALED = (SELECT id FROM wod_variacoes WHERE wod_id = @WOD_5 AND nome = 'Scaled' LIMIT 1);
SET @VAR_5_BEG = (SELECT id FROM wod_variacoes WHERE wod_id = @WOD_5 AND nome = 'Beginner' LIMIT 1);

-- ============================================
-- 13. INSERIR RESULTADOS DO WOD 1
-- ============================================

INSERT INTO wod_resultados (wod_id, usuario_id, variacao_id, resultado, tempo_total, repeticoes, peso, nota, criado_em, atualizado_em) VALUES
(@WOD_1, 2, @VAR_1_RX, '12 rounds + 6 reps', '20:00', 126, NULL, 'Great effort! Form stayed strong throughout', NOW(), NOW()),
(@WOD_1, 3, @VAR_1_SCALED, '10 rounds + 4 reps', '20:00', 104, NULL, 'First time doing this workout', NOW(), NOW()),
(@WOD_1, 4, @VAR_1_RX, '13 rounds + 2 reps', '20:00', 132, NULL, 'Personal best on this one!', NOW(), NOW());

-- ============================================
-- 14. INSERIR RESULTADOS DO WOD 2
-- ============================================

INSERT INTO wod_resultados (wod_id, usuario_id, variacao_id, resultado, tempo_total, repeticoes, peso, nota, criado_em, atualizado_em) VALUES
(@WOD_2, 2, @VAR_2_RX, 'Completed all rounds', '38:45', NULL, NULL, 'Good pump, shoulders felt it', NOW(), NOW()),
(@WOD_2, 3, @VAR_2_MOD, 'Completed all rounds', '41:20', NULL, NULL, 'Used lighter weight, still challenging', NOW(), NOW());

-- ============================================
-- 15. INSERIR RESULTADOS DO WOD 3 (Teste de Força)
-- ============================================

INSERT INTO wod_resultados (wod_id, usuario_id, variacao_id, resultado, tempo_total, repeticoes, peso, nota, criado_em, atualizado_em) VALUES
(@WOD_3, 2, @VAR_3_RX, '1RM achieved', NULL, 1, 295, 'New Personal Record! 10 lb improvement', NOW(), NOW()),
(@WOD_3, 4, @VAR_3_RX, '1RM achieved', NULL, 1, 315, 'Previous PR was 310, felt strong today', NOW(), NOW()),
(@WOD_3, 3, @VAR_3_SCALED, '1RM achieved', NULL, 1, 185, 'Front squat test day instead', NOW(), NOW());

-- ============================================
-- 16. INSERIR RESULTADOS DO WOD 4
-- ============================================

INSERT INTO wod_resultados (wod_id, usuario_id, variacao_id, resultado, tempo_total, repeticoes, peso, nota, criado_em, atualizado_em) VALUES
(@WOD_4, 2, @VAR_4_RX, 'Finished', '24:13', NULL, NULL, 'Ring dips got tough at the end', NOW(), NOW()),
(@WOD_4, 3, @VAR_4_SCALED, 'Finished', '28:45', NULL, NULL, 'Box dips helped me finish strong', NOW(), NOW()),
(@WOD_4, 4, @VAR_4_RX, 'Finished', '22:30', NULL, NULL, 'Fast finish today!', NOW(), NOW());

-- ============================================
-- 17. INSERIR RESULTADOS DO WOD 5
-- ============================================

INSERT INTO wod_resultados (wod_id, usuario_id, variacao_id, resultado, tempo_total, repeticoes, peso, nota, criado_em, atualizado_em) VALUES
(@WOD_5, 2, @VAR_5_RX, '3 rounds', '13:45', NULL, NULL, 'Double unders still challenging but improving', NOW(), NOW()),
(@WOD_5, 3, @VAR_5_SCALED, '3 rounds', '16:20', NULL, NULL, 'Single unders more comfortable for me', NOW(), NOW()),
(@WOD_5, 4, @VAR_5_RX, '3 rounds', '12:15', NULL, NULL, 'Snatches felt smooth today', NOW(), NOW());

-- ============================================
-- 18. VERIFICAÇÃO FINAL
-- ============================================

SELECT CONCAT('✅ WODs para tenant ', @TENANT_ID, ':') as status;
SELECT id, titulo, data, status FROM wods WHERE tenant_id = @TENANT_ID ORDER BY data;

SELECT '' as blank;
SELECT '✅ Total de Blocos:' as status;
SELECT COUNT(*) as total FROM wod_blocos WHERE wod_id IN (SELECT id FROM wods WHERE tenant_id = @TENANT_ID);

SELECT '' as blank;
SELECT '✅ Total de Variações:' as status;
SELECT COUNT(*) as total FROM wod_variacoes WHERE wod_id IN (SELECT id FROM wods WHERE tenant_id = @TENANT_ID);

SELECT '' as blank;
SELECT '✅ Total de Resultados:' as status;
SELECT COUNT(*) as total FROM wod_resultados WHERE wod_id IN (SELECT id FROM wods WHERE tenant_id = @TENANT_ID);

SELECT '' as blank;
SELECT '✅ WOD 1 - Blocos:' as info;
SELECT ordem, tipo, titulo FROM wod_blocos WHERE wod_id = @WOD_1 ORDER BY ordem;

SELECT '' as blank;
SELECT '✅ WOD 1 - Variações:' as info;
SELECT nome, descricao FROM wod_variacoes WHERE wod_id = @WOD_1;
