-- Seed com mais turmas para o dia 09/01/2026 (Tenant 5)
-- Dia ID 17 = 2026-01-09
-- Horários: 06:00, 07:00, 08:00, 09:00, 17:00, 18:00, 19:00

-- Primeiramente, garantir que o charset está correto
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- Turmas com horários customizados (usando horario_inicio e horario_fim diretos)
INSERT INTO turmas (tenant_id, professor_id, modalidade_id, dia_id, horario_inicio, horario_fim, nome, limite_alunos, ativo, created_at, updated_at) 
VALUES
(5, 6, 1, 17, '06:00:00', '07:00:00', 'CrossFit - 06:00 - João Pedro', 15, 1, NOW(), NOW()),
(5, 7, 1, 17, '07:00:00', '08:00:00', 'CrossFit - 07:00 - Maria Silva', 15, 1, NOW(), NOW()),
(5, 8, 1, 17, '08:00:00', '09:00:00', 'CrossFit - 08:00 - Fernando Costa', 15, 1, NOW(), NOW()),
(5, 9, 1, 17, '09:00:00', '10:00:00', 'CrossFit - 09:00 - Beatriz Oliveira', 15, 1, NOW(), NOW()),
(5, 10, 1, 17, '17:00:00', '18:00:00', 'CrossFit - 17:00 - Lucas Santos', 15, 1, NOW(), NOW()),
(5, 6, 1, 17, '18:00:00', '19:00:00', 'CrossFit - 18:00 - João Pedro', 15, 1, NOW(), NOW()),
(5, 7, 1, 17, '19:00:00', '20:00:00', 'CrossFit - 19:00 - Maria Silva', 15, 1, NOW(), NOW());
