-- Seed de Horários - Criando horários base para cada dia
-- Os horários já deveriam estar associados aos dias, então vamos inserir alguns para os primeiros dias
INSERT INTO horarios (dia_id, hora, horario_inicio, horario_fim, limite_alunos, tolerancia_minutos, tolerancia_antes_minutos, ativo)
SELECT d.id, '06:00:00', '06:00:00', '07:00:00', 15, 10, 480, 1
FROM dias d
WHERE d.ativo = 1
LIMIT 50;

INSERT INTO horarios (dia_id, hora, horario_inicio, horario_fim, limite_alunos, tolerancia_minutos, tolerancia_antes_minutos, ativo)
SELECT d.id, '07:00:00', '07:00:00', '08:00:00', 15, 10, 480, 1
FROM dias d
WHERE d.ativo = 1
LIMIT 50
OFFSET 50;

INSERT INTO horarios (dia_id, hora, horario_inicio, horario_fim, limite_alunos, tolerancia_minutos, tolerancia_antes_minutos, ativo)
SELECT d.id, '08:00:00', '08:00:00', '09:00:00', 15, 10, 480, 1
FROM dias d
WHERE d.ativo = 1
LIMIT 50
OFFSET 100;

INSERT INTO horarios (dia_id, hora, horario_inicio, horario_fim, limite_alunos, tolerancia_minutos, tolerancia_antes_minutos, ativo)
SELECT d.id, '17:00:00', '17:00:00', '18:00:00', 15, 10, 480, 1
FROM dias d
WHERE d.ativo = 1
LIMIT 50
OFFSET 150;

INSERT INTO horarios (dia_id, hora, horario_inicio, horario_fim, limite_alunos, tolerancia_minutos, tolerancia_antes_minutos, ativo)
SELECT d.id, '18:00:00', '18:00:00', '19:00:00', 15, 10, 480, 1
FROM dias d
WHERE d.ativo = 1
LIMIT 50
OFFSET 200;

INSERT INTO horarios (dia_id, hora, horario_inicio, horario_fim, limite_alunos, tolerancia_minutos, tolerancia_antes_minutos, ativo)
SELECT d.id, '19:00:00', '19:00:00', '20:00:00', 15, 10, 480, 1
FROM dias d
WHERE d.ativo = 1
LIMIT 50
OFFSET 250;

-- Seed de Professores para o Tenant 1 (Academia Padrão)
INSERT IGNORE INTO professores (tenant_id, nome, email, cpf, foto_url, ativo, created_at, updated_at) VALUES
(1, 'Carlos Silva', 'carlos.silva@crossfit.com', '12345678901', 'https://via.placeholder.com/150?text=Carlos', 1, NOW(), NOW()),
(1, 'Ana Costa', 'ana.costa@crossfit.com', '12345678902', 'https://via.placeholder.com/150?text=Ana', 1, NOW(), NOW()),
(1, 'Roberto Santos', 'roberto.santos@crossfit.com', '12345678903', 'https://via.placeholder.com/150?text=Roberto', 1, NOW(), NOW()),
(1, 'Juliana Pereira', 'juliana.pereira@crossfit.com', '12345678904', 'https://via.placeholder.com/150?text=Juliana', 1, NOW(), NOW()),
(1, 'Marcus Oliveira', 'marcus.oliveira@crossfit.com', '12345678905', 'https://via.placeholder.com/150?text=Marcus', 1, NOW(), NOW());

-- Seed de Turmas de Crossfit - Modalidade ID 1 (Crossfit)
-- Criamos turmas base vinculadas aos dias com horários diretos (horario_inicio, horario_fim)

-- Segunda a Sexta de manhã (06:00, 07:00, 08:00)
INSERT INTO turmas (tenant_id, professor_id, modalidade_id, dia_id, horario_inicio, horario_fim, nome, limite_alunos, ativo, created_at, updated_at) 
SELECT 1, 1, 1, d.id, '06:00:00', '07:00:00', CONCAT('CrossFit - 6:00 - Prof. Carlos'), 15, 1, NOW(), NOW()
FROM dias d
WHERE d.ativo = 1 AND d.id <= 70
LIMIT 12;

INSERT INTO turmas (tenant_id, professor_id, modalidade_id, dia_id, horario_inicio, horario_fim, nome, limite_alunos, ativo, created_at, updated_at) 
SELECT 1, 2, 1, d.id, '07:00:00', '08:00:00', CONCAT('CrossFit - 7:00 - Prof. Ana'), 15, 1, NOW(), NOW()
FROM dias d
WHERE d.ativo = 1 AND d.id > 50 AND d.id <= 120
LIMIT 12;

INSERT INTO turmas (tenant_id, professor_id, modalidade_id, dia_id, horario_inicio, horario_fim, nome, limite_alunos, ativo, created_at, updated_at) 
SELECT 1, 3, 1, d.id, '08:00:00', '09:00:00', CONCAT('CrossFit - 8:00 - Prof. Roberto'), 15, 1, NOW(), NOW()
FROM dias d
WHERE d.ativo = 1 AND d.id > 100 AND d.id <= 170
LIMIT 12;

-- Noite Segunda a Sexta (17:00, 18:00, 19:00)
INSERT INTO turmas (tenant_id, professor_id, modalidade_id, dia_id, horario_inicio, horario_fim, nome, limite_alunos, ativo, created_at, updated_at) 
SELECT 1, 4, 1, d.id, '17:00:00', '18:00:00', CONCAT('CrossFit - 17:00 - Prof. Juliana'), 15, 1, NOW(), NOW()
FROM dias d
WHERE d.ativo = 1 AND d.id > 150 AND d.id <= 220
LIMIT 12;

INSERT INTO turmas (tenant_id, professor_id, modalidade_id, dia_id, horario_inicio, horario_fim, nome, limite_alunos, ativo, created_at, updated_at) 
SELECT 1, 5, 1, d.id, '18:00:00', '19:00:00', CONCAT('CrossFit - 18:00 - Prof. Marcus'), 15, 1, NOW(), NOW()
FROM dias d
WHERE d.ativo = 1 AND d.id > 200 AND d.id <= 270
LIMIT 12;

INSERT INTO turmas (tenant_id, professor_id, modalidade_id, dia_id, horario_inicio, horario_fim, nome, limite_alunos, ativo, created_at, updated_at) 
SELECT 1, 1, 1, d.id, '19:00:00', '20:00:00', CONCAT('CrossFit - 19:00 - Prof. Carlos'), 15, 1, NOW(), NOW()
FROM dias d
WHERE d.ativo = 1 AND d.id > 250
LIMIT 12;
