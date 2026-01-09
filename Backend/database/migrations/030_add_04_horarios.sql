-- Adicionar horários 04:00-04:30 para todos os dias
INSERT INTO horarios (dia_id, hora, horario_inicio, horario_fim, limite_alunos, tolerancia_minutos, tolerancia_antes_minutos, ativo, created_at, updated_at)
SELECT 
    d.id,
    '04:00:00',
    '04:00:00',
    '04:30:00',
    15,
    10,
    480,
    1,
    NOW(),
    NOW()
FROM dias d
WHERE d.ativo = 1 AND d.id NOT IN (
    SELECT DISTINCT dia_id FROM horarios WHERE horario_inicio = '04:00:00'
);
