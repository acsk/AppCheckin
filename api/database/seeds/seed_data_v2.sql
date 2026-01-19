-- Seed com turmas específicas (06h, 07h, 08h, 16h, 17h, 18h, 19h)

-- Limpar dados anteriores
DELETE FROM checkins;
DELETE FROM horarios;
DELETE FROM dias;
DELETE FROM usuarios WHERE email = 'teste@exemplo.com';

-- Inserir dias (próximos 7 dias)
INSERT INTO dias (data, ativo) VALUES
    (CURDATE() + INTERVAL 1 DAY, TRUE),
    (CURDATE() + INTERVAL 2 DAY, TRUE),
    (CURDATE() + INTERVAL 3 DAY, TRUE),
    (CURDATE() + INTERVAL 4 DAY, TRUE),
    (CURDATE() + INTERVAL 5 DAY, TRUE),
    (CURDATE() + INTERVAL 6 DAY, TRUE),
    (CURDATE() + INTERVAL 7 DAY, TRUE);

-- Inserir horários/turmas para cada dia
-- Turmas da manhã: 06h, 07h, 08h
-- Turmas da tarde/noite: 16h, 17h, 18h, 19h
INSERT INTO horarios (dia_id, hora, horario_inicio, horario_fim, limite_alunos, tolerancia_minutos, ativo)
SELECT 
    d.id,
    h.hora,
    h.inicio,
    h.fim,
    h.limite,
    h.tolerancia,
    TRUE as ativo
FROM dias d
CROSS JOIN (
    -- Turmas da manhã (1 hora de duração)
    SELECT '06:00:00' as hora, '06:00:00' as inicio, '07:00:00' as fim, 30 as limite, 10 as tolerancia UNION ALL
    SELECT '07:00:00' as hora, '07:00:00' as inicio, '08:00:00' as fim, 30 as limite, 10 as tolerancia UNION ALL
    SELECT '08:00:00' as hora, '08:00:00' as inicio, '09:00:00' as fim, 30 as limite, 10 as tolerancia UNION ALL
    -- Turmas da tarde/noite (1 hora de duração)
    SELECT '16:00:00' as hora, '16:00:00' as inicio, '17:00:00' as fim, 30 as limite, 10 as tolerancia UNION ALL
    SELECT '17:00:00' as hora, '17:00:00' as inicio, '18:00:00' as fim, 30 as limite, 10 as tolerancia UNION ALL
    SELECT '18:00:00' as hora, '18:00:00' as inicio, '19:00:00' as fim, 30 as limite, 10 as tolerancia UNION ALL
    SELECT '19:00:00' as hora, '19:00:00' as inicio, '20:00:00' as fim, 30 as limite, 10 as tolerancia
) h;
