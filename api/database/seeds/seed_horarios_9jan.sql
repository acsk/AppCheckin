-- Criar mais horários para o dia 17 (09/01/2026)
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- Inserir horários de manhã
INSERT INTO horarios (dia_id, hora, horario_inicio, horario_fim, limite_alunos, tolerancia_minutos, tolerancia_antes_minutos, ativo)
VALUES
(17, '07:00:00', '07:00:00', '08:00:00', 15, 10, 480, 1),
(17, '08:00:00', '08:00:00', '09:00:00', 15, 10, 480, 1),
(17, '09:00:00', '09:00:00', '10:00:00', 15, 10, 480, 1);

-- Inserir horários de noite
INSERT INTO horarios (dia_id, hora, horario_inicio, horario_fim, limite_alunos, tolerancia_minutos, tolerancia_antes_minutos, ativo)
VALUES
(17, '17:00:00', '17:00:00', '18:00:00', 15, 10, 480, 1),
(17, '18:00:00', '18:00:00', '19:00:00', 15, 10, 480, 1),
(17, '19:00:00', '19:00:00', '20:00:00', 15, 10, 480, 1);
