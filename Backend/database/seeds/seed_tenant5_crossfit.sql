-- Seed de Professores para o Tenant 5 (Academia de Testes)
INSERT IGNORE INTO professores (tenant_id, nome, email, cpf, foto_url, ativo, created_at, updated_at) VALUES
(5, 'João Pedro', 'joao.pedro@academiateste.com', '98765432101', 'https://via.placeholder.com/150?text=Joao', 1, NOW(), NOW()),
(5, 'Maria Silva', 'maria.silva@academiateste.com', '98765432102', 'https://via.placeholder.com/150?text=Maria', 1, NOW(), NOW()),
(5, 'Fernando Costa', 'fernando.costa@academiateste.com', '98765432103', 'https://via.placeholder.com/150?text=Fernando', 1, NOW(), NOW()),
(5, 'Beatriz Oliveira', 'beatriz.oliveira@academiateste.com', '98765432104', 'https://via.placeholder.com/150?text=Beatriz', 1, NOW(), NOW()),
(5, 'Lucas Santos', 'lucas.santos@academiateste.com', '98765432105', 'https://via.placeholder.com/150?text=Lucas', 1, NOW(), NOW());

-- Seed de Turmas de Crossfit para Tenant 5
INSERT INTO turmas (tenant_id, professor_id, modalidade_id, dia_id, horario_id, nome, limite_alunos, ativo, created_at, updated_at) 
SELECT 5, 
  (SELECT id FROM professores WHERE tenant_id = 5 AND nome = 'João Pedro'),
  1, h.dia_id, h.id, 
  CONCAT('CrossFit - 6:00 - João'), 15, 1, NOW(), NOW()
FROM horarios h
WHERE h.hora = '06:00:00' AND h.dia_id <= 70
LIMIT 12;

INSERT INTO turmas (tenant_id, professor_id, modalidade_id, dia_id, horario_id, nome, limite_alunos, ativo, created_at, updated_at) 
SELECT 5, 
  (SELECT id FROM professores WHERE tenant_id = 5 AND nome = 'Maria Silva'),
  1, h.dia_id, h.id, 
  CONCAT('CrossFit - 7:00 - Maria'), 15, 1, NOW(), NOW()
FROM horarios h
WHERE h.hora = '07:00:00' AND h.dia_id > 50 AND h.dia_id <= 120
LIMIT 12;

INSERT INTO turmas (tenant_id, professor_id, modalidade_id, dia_id, horario_id, nome, limite_alunos, ativo, created_at, updated_at) 
SELECT 5, 
  (SELECT id FROM professores WHERE tenant_id = 5 AND nome = 'Fernando Costa'),
  1, h.dia_id, h.id, 
  CONCAT('CrossFit - 8:00 - Fernando'), 15, 1, NOW(), NOW()
FROM horarios h
WHERE h.hora = '08:00:00' AND h.dia_id > 100 AND h.dia_id <= 170
LIMIT 12;

INSERT INTO turmas (tenant_id, professor_id, modalidade_id, dia_id, horario_id, nome, limite_alunos, ativo, created_at, updated_at) 
SELECT 5, 
  (SELECT id FROM professores WHERE tenant_id = 5 AND nome = 'Beatriz Oliveira'),
  1, h.dia_id, h.id, 
  CONCAT('CrossFit - 17:00 - Beatriz'), 15, 1, NOW(), NOW()
FROM horarios h
WHERE h.hora = '17:00:00' AND h.dia_id > 150 AND h.dia_id <= 220
LIMIT 12;

INSERT INTO turmas (tenant_id, professor_id, modalidade_id, dia_id, horario_id, nome, limite_alunos, ativo, created_at, updated_at) 
SELECT 5, 
  (SELECT id FROM professores WHERE tenant_id = 5 AND nome = 'Lucas Santos'),
  1, h.dia_id, h.id, 
  CONCAT('CrossFit - 18:00 - Lucas'), 15, 1, NOW(), NOW()
FROM horarios h
WHERE h.hora = '18:00:00' AND h.dia_id > 200 AND h.dia_id <= 270
LIMIT 12;

INSERT INTO turmas (tenant_id, professor_id, modalidade_id, dia_id, horario_id, nome, limite_alunos, ativo, created_at, updated_at) 
SELECT 5, 
  (SELECT id FROM professores WHERE tenant_id = 5 AND nome = 'João Pedro'),
  1, h.dia_id, h.id, 
  CONCAT('CrossFit - 19:00 - João'), 15, 1, NOW(), NOW()
FROM horarios h
WHERE h.hora = '19:00:00' AND h.dia_id > 250
LIMIT 12;
