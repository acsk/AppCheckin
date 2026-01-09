-- Seed com mais turmas para o dia 09/01/2026 (Tenant 5)
-- Dia ID 17 = 2026-01-09
-- Horários IDs: 47=06:00, 425=07:00, 426=08:00, 427=09:00, 428=17:00, 429=18:00, 430=19:00

-- Primeiramente, garantir que o charset está correto
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- Turmas de MANHÃ (6:00, 7:00, 8:00, 9:00)
INSERT INTO turmas (tenant_id, professor_id, modalidade_id, dia_id, horario_id, nome, limite_alunos, ativo, created_at, updated_at) 
VALUES
(5, 6, 1, 17, 47, 'CrossFit - 06:00 - João Pedro', 15, 1, NOW(), NOW()),
(5, 7, 1, 17, 425, 'CrossFit - 07:00 - Maria Silva', 15, 1, NOW(), NOW()),
(5, 8, 1, 17, 426, 'CrossFit - 08:00 - Fernando Costa', 15, 1, NOW(), NOW()),
(5, 9, 1, 17, 427, 'CrossFit - 09:00 - Beatriz Oliveira', 15, 1, NOW(), NOW()),
(5, 10, 1, 17, 428, 'CrossFit - 17:00 - Lucas Santos', 15, 1, NOW(), NOW()),
(5, 6, 1, 17, 429, 'CrossFit - 18:00 - João Pedro', 15, 1, NOW(), NOW()),
(5, 7, 1, 17, 430, 'CrossFit - 19:00 - Maria Silva', 15, 1, NOW(), NOW());
