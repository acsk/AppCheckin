-- ========================================
-- DADOS DE TESTE PARA ACADEMIA FITNESS PRO
-- tenant_id = 2
-- ========================================

-- Criar planos para a academia
INSERT INTO planos (tenant_id, nome, descricao, valor, duracao_dias) VALUES
(2, 'Plano Mensal', 'Acesso ilimitado por 30 dias', 99.90, 30),
(2, 'Plano Trimestral', 'Acesso ilimitado por 90 dias', 269.90, 90),
(2, 'Plano Semestral', 'Acesso ilimitado por 180 dias', 499.90, 180);

-- Criar dias
INSERT INTO dias (tenant_id, data, ativo) VALUES
(2, CURDATE(), 1),
(2, DATE_ADD(CURDATE(), INTERVAL 1 DAY), 1),
(2, DATE_ADD(CURDATE(), INTERVAL 2 DAY), 1),
(2, DATE_ADD(CURDATE(), INTERVAL 3 DAY), 1),
(2, DATE_ADD(CURDATE(), INTERVAL 4 DAY), 1),
(2, DATE_ADD(CURDATE(), INTERVAL 5 DAY), 1),
(2, DATE_ADD(CURDATE(), INTERVAL 6 DAY), 1);

-- Criar horários para cada dia
-- Pegar IDs dos dias criados
SET @dia1 = (SELECT id FROM dias WHERE tenant_id = 2 AND data = CURDATE());
SET @dia2 = (SELECT id FROM dias WHERE tenant_id = 2 AND data = DATE_ADD(CURDATE(), INTERVAL 1 DAY));
SET @dia3 = (SELECT id FROM dias WHERE tenant_id = 2 AND data = DATE_ADD(CURDATE(), INTERVAL 2 DAY));
SET @dia4 = (SELECT id FROM dias WHERE tenant_id = 2 AND data = DATE_ADD(CURDATE(), INTERVAL 3 DAY));
SET @dia5 = (SELECT id FROM dias WHERE tenant_id = 2 AND data = DATE_ADD(CURDATE(), INTERVAL 4 DAY));
SET @dia6 = (SELECT id FROM dias WHERE tenant_id = 2 AND data = DATE_ADD(CURDATE(), INTERVAL 5 DAY));
SET @dia7 = (SELECT id FROM dias WHERE tenant_id = 2 AND data = DATE_ADD(CURDATE(), INTERVAL 6 DAY));

-- Horários dia 1
INSERT INTO horarios (dia_id, hora, horario_inicio, horario_fim, limite_alunos, tolerancia_minutos, tolerancia_antes_minutos) VALUES
(@dia1, '06:00:00', '06:00:00', '07:00:00', 20, 10, 480),
(@dia1, '07:00:00', '07:00:00', '08:00:00', 20, 10, 480),
(@dia1, '08:00:00', '08:00:00', '09:00:00', 20, 10, 480),
(@dia1, '09:00:00', '09:00:00', '10:00:00', 15, 10, 480),
(@dia1, '18:00:00', '18:00:00', '19:00:00', 25, 10, 480),
(@dia1, '19:00:00', '19:00:00', '20:00:00', 25, 10, 480),
(@dia1, '20:00:00', '20:00:00', '21:00:00', 20, 10, 480);

-- Horários dia 2
INSERT INTO horarios (dia_id, hora, horario_inicio, horario_fim, limite_alunos, tolerancia_minutos, tolerancia_antes_minutos) VALUES
(@dia2, '06:00:00', '06:00:00', '07:00:00', 20, 10, 480),
(@dia2, '07:00:00', '07:00:00', '08:00:00', 20, 10, 480),
(@dia2, '08:00:00', '08:00:00', '09:00:00', 20, 10, 480),
(@dia2, '09:00:00', '09:00:00', '10:00:00', 15, 10, 480),
(@dia2, '18:00:00', '18:00:00', '19:00:00', 25, 10, 480),
(@dia2, '19:00:00', '19:00:00', '20:00:00', 25, 10, 480),
(@dia2, '20:00:00', '20:00:00', '21:00:00', 20, 10, 480);

-- Horários dia 3
INSERT INTO horarios (dia_id, hora, horario_inicio, horario_fim, limite_alunos, tolerancia_minutos, tolerancia_antes_minutos) VALUES
(@dia3, '06:00:00', '06:00:00', '07:00:00', 20, 10, 480),
(@dia3, '07:00:00', '07:00:00', '08:00:00', 20, 10, 480),
(@dia3, '08:00:00', '08:00:00', '09:00:00', 20, 10, 480),
(@dia3, '09:00:00', '09:00:00', '10:00:00', 15, 10, 480),
(@dia3, '18:00:00', '18:00:00', '19:00:00', 25, 10, 480),
(@dia3, '19:00:00', '19:00:00', '20:00:00', 25, 10, 480),
(@dia3, '20:00:00', '20:00:00', '21:00:00', 20, 10, 480);

-- Horários dia 4
INSERT INTO horarios (dia_id, hora, horario_inicio, horario_fim, limite_alunos, tolerancia_minutos, tolerancia_antes_minutos) VALUES
(@dia4, '06:00:00', '06:00:00', '07:00:00', 20, 10, 480),
(@dia4, '07:00:00', '07:00:00', '08:00:00', 20, 10, 480),
(@dia4, '08:00:00', '08:00:00', '09:00:00', 20, 10, 480),
(@dia4, '09:00:00', '09:00:00', '10:00:00', 15, 10, 480),
(@dia4, '18:00:00', '18:00:00', '19:00:00', 25, 10, 480),
(@dia4, '19:00:00', '19:00:00', '20:00:00', 25, 10, 480),
(@dia4, '20:00:00', '20:00:00', '21:00:00', 20, 10, 480);

-- Horários dia 5
INSERT INTO horarios (dia_id, hora, horario_inicio, horario_fim, limite_alunos, tolerancia_minutos, tolerancia_antes_minutos) VALUES
(@dia5, '06:00:00', '06:00:00', '07:00:00', 20, 10, 480),
(@dia5, '07:00:00', '07:00:00', '08:00:00', 20, 10, 480),
(@dia5, '08:00:00', '08:00:00', '09:00:00', 20, 10, 480),
(@dia5, '09:00:00', '09:00:00', '10:00:00', 15, 10, 480),
(@dia5, '18:00:00', '18:00:00', '19:00:00', 25, 10, 480),
(@dia5, '19:00:00', '19:00:00', '20:00:00', 25, 10, 480),
(@dia5, '20:00:00', '20:00:00', '21:00:00', 20, 10, 480);

-- Horários dia 6 (Sábado - só manhã)
INSERT INTO horarios (dia_id, hora, horario_inicio, horario_fim, limite_alunos, tolerancia_minutos, tolerancia_antes_minutos) VALUES
(@dia6, '08:00:00', '08:00:00', '09:00:00', 15, 10, 480),
(@dia6, '09:00:00', '09:00:00', '10:00:00', 15, 10, 480),
(@dia6, '10:00:00', '10:00:00', '11:00:00', 15, 10, 480),
(@dia6, '11:00:00', '11:00:00', '12:00:00', 15, 10, 480);

-- Horários dia 7
INSERT INTO horarios (dia_id, hora, horario_inicio, horario_fim, limite_alunos, tolerancia_minutos, tolerancia_antes_minutos) VALUES
(@dia7, '06:00:00', '06:00:00', '07:00:00', 20, 10, 480),
(@dia7, '07:00:00', '07:00:00', '08:00:00', 20, 10, 480),
(@dia7, '08:00:00', '08:00:00', '09:00:00', 20, 10, 480),
(@dia7, '09:00:00', '09:00:00', '10:00:00', 15, 10, 480),
(@dia7, '18:00:00', '18:00:00', '19:00:00', 25, 10, 480),
(@dia7, '19:00:00', '19:00:00', '20:00:00', 25, 10, 480),
(@dia7, '20:00:00', '20:00:00', '21:00:00', 20, 10, 480);

-- Criar alguns alunos de exemplo
SET @plano_mensal = (SELECT id FROM planos WHERE tenant_id = 2 AND nome = 'Plano Mensal' LIMIT 1);
SET @plano_trimestral = (SELECT id FROM planos WHERE tenant_id = 2 AND nome = 'Plano Trimestral' LIMIT 1);
SET @plano_semestral = (SELECT id FROM planos WHERE tenant_id = 2 AND nome = 'Plano Semestral' LIMIT 1);

INSERT INTO usuarios (tenant_id, nome, email, email_global, role_id, plano_id, data_vencimento_plano, senha_hash) VALUES
(2, 'João Silva', 'joao.silva@email.com', 'joao.silva@email.com', 1, @plano_mensal, DATE_ADD(CURDATE(), INTERVAL 30 DAY), '$2y$10$gHLUPI9VrE60CMi.TTj4cujkftMVWZe2RLC9JZCCGYHwCv73iaGmW'),
(2, 'Maria Santos', 'maria.santos@email.com', 'maria.santos@email.com', 1, @plano_trimestral, DATE_ADD(CURDATE(), INTERVAL 90 DAY), '$2y$10$gHLUPI9VrE60CMi.TTj4cujkftMVWZe2RLC9JZCCGYHwCv73iaGmW'),
(2, 'Pedro Costa', 'pedro.costa@email.com', 'pedro.costa@email.com', 1, @plano_mensal, DATE_ADD(CURDATE(), INTERVAL 15 DAY), '$2y$10$gHLUPI9VrE60CMi.TTj4cujkftMVWZe2RLC9JZCCGYHwCv73iaGmW'),
(2, 'Ana Oliveira', 'ana.oliveira@email.com', 'ana.oliveira@email.com', 1, @plano_semestral, DATE_ADD(CURDATE(), INTERVAL 180 DAY), '$2y$10$gHLUPI9VrE60CMi.TTj4cujkftMVWZe2RLC9JZCCGYHwCv73iaGmW'),
(2, 'Lucas Ferreira', 'lucas.ferreira@email.com', 'lucas.ferreira@email.com', 1, @plano_mensal, DATE_ADD(CURDATE(), INTERVAL 5 DAY), '$2y$10$gHLUPI9VrE60CMi.TTj4cujkftMVWZe2RLC9JZCCGYHwCv73iaGmW');

-- Vincular alunos ao tenant
INSERT INTO usuario_tenant (usuario_id, tenant_id, plano_id, status, data_inicio)
SELECT u.id, 2, u.plano_id, 'ativo', CURDATE()
FROM usuarios u
WHERE u.tenant_id = 2 AND u.role_id = 1 AND u.email LIKE '%@email.com';

SELECT '✓ Dados criados para Academia Fitness Pro!' as status;
SELECT CONCAT('Total de planos: ', COUNT(*)) as info FROM planos WHERE tenant_id = 2;
SELECT CONCAT('Total de dias: ', COUNT(*)) as info FROM dias WHERE tenant_id = 2;
SELECT CONCAT('Total de horários: ', COUNT(*)) as info FROM horarios h INNER JOIN dias d ON h.dia_id = d.id WHERE d.tenant_id = 2;
SELECT CONCAT('Total de alunos: ', COUNT(*)) as info FROM usuarios WHERE tenant_id = 2 AND role_id = 1;

-- Criar dias da semana com horários
-- Hoje
INSERT INTO dias (tenant_id, data, ativo) VALUES
(2, CURDATE(), 1);

SET @dia_hoje_id = LAST_INSERT_ID();

INSERT INTO horarios (tenant_id, dia_id, horario, vagas_totais, vagas_disponiveis) VALUES
(2, @dia_hoje_id, '06:00:00', 20, 20),
(2, @dia_hoje_id, '07:00:00', 20, 20),
(2, @dia_hoje_id, '08:00:00', 20, 20),
(2, @dia_hoje_id, '09:00:00', 15, 15),
(2, @dia_hoje_id, '10:00:00', 15, 15),
(2, @dia_hoje_id, '18:00:00', 25, 25),
(2, @dia_hoje_id, '19:00:00', 25, 25),
(2, @dia_hoje_id, '20:00:00', 20, 20);

-- Amanhã
INSERT INTO dias (tenant_id, data, ativo) VALUES
(2, DATE_ADD(CURDATE(), INTERVAL 1 DAY), 1);

SET @dia_amanha_id = LAST_INSERT_ID();

INSERT INTO horarios (tenant_id, dia_id, horario, vagas_totais, vagas_disponiveis) VALUES
(2, @dia_amanha_id, '06:00:00', 20, 20),
(2, @dia_amanha_id, '07:00:00', 20, 20),
(2, @dia_amanha_id, '08:00:00', 20, 20),
(2, @dia_amanha_id, '09:00:00', 15, 15),
(2, @dia_amanha_id, '10:00:00', 15, 15),
(2, @dia_amanha_id, '18:00:00', 25, 25),
(2, @dia_amanha_id, '19:00:00', 25, 25),
(2, @dia_amanha_id, '20:00:00', 20, 20);

-- Dia +2
INSERT INTO dias (tenant_id, data, ativo) VALUES
(2, DATE_ADD(CURDATE(), INTERVAL 2 DAY), 1);

SET @dia_2_id = LAST_INSERT_ID();

INSERT INTO horarios (tenant_id, dia_id, horario, vagas_totais, vagas_disponiveis) VALUES
(2, @dia_2_id, '06:00:00', 20, 20),
(2, @dia_2_id, '07:00:00', 20, 20),
(2, @dia_2_id, '08:00:00', 20, 20),
(2, @dia_2_id, '09:00:00', 15, 15),
(2, @dia_2_id, '10:00:00', 15, 15),
(2, @dia_2_id, '18:00:00', 25, 25),
(2, @dia_2_id, '19:00:00', 25, 25),
(2, @dia_2_id, '20:00:00', 20, 20);

-- Dia +3
INSERT INTO dias (tenant_id, data, ativo) VALUES
(2, DATE_ADD(CURDATE(), INTERVAL 3 DAY), 1);

SET @dia_3_id = LAST_INSERT_ID();

INSERT INTO horarios (tenant_id, dia_id, horario, vagas_totais, vagas_disponiveis) VALUES
(2, @dia_3_id, '06:00:00', 20, 20),
(2, @dia_3_id, '07:00:00', 20, 20),
(2, @dia_3_id, '08:00:00', 20, 20),
(2, @dia_3_id, '09:00:00', 15, 15),
(2, @dia_3_id, '10:00:00', 15, 15),
(2, @dia_3_id, '18:00:00', 25, 25),
(2, @dia_3_id, '19:00:00', 25, 25),
(2, @dia_3_id, '20:00:00', 20, 20);

-- Dia +4
INSERT INTO dias (tenant_id, data, ativo) VALUES
(2, DATE_ADD(CURDATE(), INTERVAL 4 DAY), 1);

SET @dia_4_id = LAST_INSERT_ID();

INSERT INTO horarios (tenant_id, dia_id, horario, vagas_totais, vagas_disponiveis) VALUES
(2, @dia_4_id, '06:00:00', 20, 20),
(2, @dia_4_id, '07:00:00', 20, 20),
(2, @dia_4_id, '08:00:00', 20, 20),
(2, @dia_4_id, '09:00:00', 15, 15),
(2, @dia_4_id, '10:00:00', 15, 15),
(2, @dia_4_id, '18:00:00', 25, 25),
(2, @dia_4_id, '19:00:00', 25, 25),
(2, @dia_4_id, '20:00:00', 20, 20);

-- Dia +5
INSERT INTO dias (tenant_id, data, ativo) VALUES
(2, DATE_ADD(CURDATE(), INTERVAL 5 DAY), 1);

SET @dia_5_id = LAST_INSERT_ID();

INSERT INTO horarios (tenant_id, dia_id, horario, vagas_totais, vagas_disponiveis) VALUES
(2, @dia_5_id, '08:00:00', 15, 15),
(2, @dia_5_id, '09:00:00', 15, 15),
(2, @dia_5_id, '10:00:00', 15, 15),
(2, @dia_5_id, '11:00:00', 15, 15);

-- Dia +6
INSERT INTO dias (tenant_id, data, ativo) VALUES
(2, DATE_ADD(CURDATE(), INTERVAL 6 DAY), 1);

SET @dia_6_id = LAST_INSERT_ID();

INSERT INTO horarios (tenant_id, dia_id, horario, vagas_totais, vagas_disponiveis) VALUES
(2, @dia_6_id, '06:00:00', 20, 20),
(2, @dia_6_id, '07:00:00', 20, 20),
(2, @dia_6_id, '08:00:00', 20, 20),
(2, @dia_6_id, '09:00:00', 15, 15),
(2, @dia_6_id, '10:00:00', 15, 15),
(2, @dia_6_id, '18:00:00', 25, 25),
(2, @dia_6_id, '19:00:00', 25, 25),
(2, @dia_6_id, '20:00:00', 20, 20);

-- Criar alguns alunos de exemplo
INSERT INTO usuarios (tenant_id, nome, email, email_global, role_id, plano_id, data_vencimento_plano, senha_hash) VALUES
(2, 'João Silva', 'joao.silva@email.com', 'joao.silva@email.com', 1, (SELECT id FROM planos WHERE tenant_id = 2 AND nome = 'Plano Mensal' LIMIT 1), DATE_ADD(CURDATE(), INTERVAL 30 DAY), '$2y$10$gHLUPI9VrE60CMi.TTj4cujkftMVWZe2RLC9JZCCGYHwCv73iaGmW'),
(2, 'Maria Santos', 'maria.santos@email.com', 'maria.santos@email.com', 1, (SELECT id FROM planos WHERE tenant_id = 2 AND nome = 'Plano Trimestral' LIMIT 1), DATE_ADD(CURDATE(), INTERVAL 90 DAY), '$2y$10$gHLUPI9VrE60CMi.TTj4cujkftMVWZe2RLC9JZCCGYHwCv73iaGmW'),
(2, 'Pedro Costa', 'pedro.costa@email.com', 'pedro.costa@email.com', 1, (SELECT id FROM planos WHERE tenant_id = 2 AND nome = 'Plano Mensal' LIMIT 1), DATE_ADD(CURDATE(), INTERVAL 15 DAY), '$2y$10$gHLUPI9VrE60CMi.TTj4cujkftMVWZe2RLC9JZCCGYHwCv73iaGmW'),
(2, 'Ana Oliveira', 'ana.oliveira@email.com', 'ana.oliveira@email.com', 1, (SELECT id FROM planos WHERE tenant_id = 2 AND nome = 'Plano Semestral' LIMIT 1), DATE_ADD(CURDATE(), INTERVAL 180 DAY), '$2y$10$gHLUPI9VrE60CMi.TTj4cujkftMVWZe2RLC9JZCCGYHwCv73iaGmW'),
(2, 'Lucas Ferreira', 'lucas.ferreira@email.com', 'lucas.ferreira@email.com', 1, (SELECT id FROM planos WHERE tenant_id = 2 AND nome = 'Plano Mensal' LIMIT 1), DATE_ADD(CURDATE(), INTERVAL 5 DAY), '$2y$10$gHLUPI9VrE60CMi.TTj4cujkftMVWZe2RLC9JZCCGYHwCv73iaGmW');

-- Vincular alunos ao tenant
INSERT INTO usuario_tenant (usuario_id, tenant_id, plano_id, status, data_inicio)
SELECT u.id, 2, u.plano_id, 'ativo', CURDATE()
FROM usuarios u
WHERE u.tenant_id = 2 AND u.role_id = 1;

-- Criar alguns check-ins de exemplo para hoje
INSERT INTO checkins (usuario_id, horario_id, data_hora, status)
SELECT 
    u.id,
    h.id,
    CONCAT(CURDATE(), ' ', h.horario),
    'presente'
FROM usuarios u
CROSS JOIN horarios h
INNER JOIN dias d ON h.dia_id = d.id
WHERE u.tenant_id = 2 
  AND u.role_id = 1 
  AND d.tenant_id = 2
  AND d.data = CURDATE()
  AND h.horario IN ('06:00:00', '07:00:00', '18:00:00')
LIMIT 8;

-- Atualizar vagas disponíveis nos horários com check-ins
UPDATE horarios h
INNER JOIN dias d ON h.dia_id = d.id
SET h.vagas_disponiveis = h.vagas_disponiveis - (
    SELECT COUNT(*) 
    FROM checkins c 
    WHERE c.horario_id = h.id
)
WHERE d.tenant_id = 2 AND d.data = CURDATE();

SELECT '✓ Dados criados para Academia Fitness Pro!' as status;
SELECT CONCAT('Total de planos: ', COUNT(*)) as info FROM planos WHERE tenant_id = 2;
SELECT CONCAT('Total de dias: ', COUNT(*)) as info FROM dias WHERE tenant_id = 2;
SELECT CONCAT('Total de horários: ', COUNT(*)) as info FROM horarios h INNER JOIN dias d ON h.dia_id = d.id WHERE d.tenant_id = 2;
SELECT CONCAT('Total de alunos: ', COUNT(*)) as info FROM usuarios WHERE tenant_id = 2 AND role_id = 1;
SELECT CONCAT('Total de check-ins: ', COUNT(*)) as info FROM checkins c INNER JOIN usuarios u ON c.usuario_id = u.id WHERE u.tenant_id = 2;
