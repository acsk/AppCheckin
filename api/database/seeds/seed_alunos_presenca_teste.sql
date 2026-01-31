-- =========================================
-- Seed para testar funcionalidade de Presença
-- Cria alunos com planos e checkins para o tenant 2 (Escola de Natação)
-- Professor Carlos Mendes (usuario_id: 5, professor_id: 1)
-- =========================================

-- Senha padrão: 123456 (hash bcrypt)
SET @senha_hash = '$2y$10$D/9bvlBKp8DXqT/6EeH1XOCl5d4/Ah8xS7EcJ5t9zXDPvGK5qJqnG';
SET @tenant_id = 2;
SET @plano_3x = 3; -- Plano 3x por Semana
SET @plano_2x = 2; -- Plano 2x por Semana
SET @papel_aluno = 1; -- papel_id para aluno

-- =========================================
-- 1. CRIAR USUÁRIOS (alunos de teste)
-- =========================================
INSERT INTO usuarios (nome, email, senha_hash, ativo) VALUES
('Ana Paula Silva', 'ana.paula@teste.com', @senha_hash, 1),
('Bruno Costa Santos', 'bruno.costa@teste.com', @senha_hash, 1),
('Carla Fernandes', 'carla.fernandes@teste.com', @senha_hash, 1),
('Diego Oliveira', 'diego.oliveira@teste.com', @senha_hash, 1),
('Elena Rodrigues', 'elena.rodrigues@teste.com', @senha_hash, 1),
('Felipe Martins', 'felipe.martins@teste.com', @senha_hash, 1),
('Gabriela Lima', 'gabriela.lima@teste.com', @senha_hash, 1),
('Henrique Almeida', 'henrique.almeida@teste.com', @senha_hash, 1)
ON DUPLICATE KEY UPDATE nome = VALUES(nome);

-- =========================================
-- 2. CRIAR ALUNOS (tabela alunos)
-- =========================================
INSERT INTO alunos (usuario_id, nome, telefone, cpf, cidade, estado, ativo)
SELECT u.id, u.nome, '21999999999', CONCAT('999.999.99', LPAD(u.id, 1, '0'), '-99'), 'Rio de Janeiro', 'RJ', 1
FROM usuarios u
WHERE u.email IN (
    'ana.paula@teste.com',
    'bruno.costa@teste.com',
    'carla.fernandes@teste.com',
    'diego.oliveira@teste.com',
    'elena.rodrigues@teste.com',
    'felipe.martins@teste.com',
    'gabriela.lima@teste.com',
    'henrique.almeida@teste.com'
)
ON DUPLICATE KEY UPDATE nome = VALUES(nome);

-- =========================================
-- 3. VINCULAR USUÁRIOS AO TENANT COM PLANOS
-- =========================================
-- Primeiros 4 alunos com plano 3x
INSERT INTO usuario_tenant (usuario_id, tenant_id, papel_id, plano_id, status, data_inicio)
SELECT u.id, @tenant_id, @papel_aluno, @plano_3x, 'ativo', CURDATE()
FROM usuarios u
WHERE u.email IN (
    'ana.paula@teste.com',
    'bruno.costa@teste.com',
    'carla.fernandes@teste.com',
    'diego.oliveira@teste.com'
)
ON DUPLICATE KEY UPDATE plano_id = VALUES(plano_id), status = 'ativo';

-- Próximos 4 alunos com plano 2x
INSERT INTO usuario_tenant (usuario_id, tenant_id, papel_id, plano_id, status, data_inicio)
SELECT u.id, @tenant_id, @papel_aluno, @plano_2x, 'ativo', CURDATE()
FROM usuarios u
WHERE u.email IN (
    'elena.rodrigues@teste.com',
    'felipe.martins@teste.com',
    'gabriela.lima@teste.com',
    'henrique.almeida@teste.com'
)
ON DUPLICATE KEY UPDATE plano_id = VALUES(plano_id), status = 'ativo';

-- =========================================
-- 4. CRIAR CHECKINS PARA HOJE (dia_id = 30, 2026-01-30)
-- Turma 47: Natação 05:00 - Carlos Mendes
-- Turma 68: Natação 06:00 - Carlos Mendes
-- =========================================

-- Checkins na turma 47 (05:00) - 5 alunos
INSERT INTO checkins (tenant_id, aluno_id, turma_id, data_checkin, presente)
SELECT @tenant_id, a.id, 47, NOW() - INTERVAL 2 HOUR, NULL
FROM alunos a
INNER JOIN usuarios u ON a.usuario_id = u.id
WHERE u.email IN (
    'ana.paula@teste.com',
    'bruno.costa@teste.com',
    'carla.fernandes@teste.com',
    'diego.oliveira@teste.com',
    'elena.rodrigues@teste.com'
);

-- Checkins na turma 68 (06:00) - 3 alunos
INSERT INTO checkins (tenant_id, aluno_id, turma_id, data_checkin, presente)
SELECT @tenant_id, a.id, 68, NOW() - INTERVAL 1 HOUR, NULL
FROM alunos a
INNER JOIN usuarios u ON a.usuario_id = u.id
WHERE u.email IN (
    'felipe.martins@teste.com',
    'gabriela.lima@teste.com',
    'henrique.almeida@teste.com'
);

-- =========================================
-- 5. CRIAR CHECKINS PARA AMANHÃ (dia_id = 31, 2026-01-31)
-- Turma 48: Natação 05:00 - Carlos Mendes
-- Turma 69: Natação 06:00 - Carlos Mendes
-- =========================================

-- Checkins na turma 48 (05:00) - 4 alunos
INSERT INTO checkins (tenant_id, aluno_id, turma_id, data_checkin, presente)
SELECT @tenant_id, a.id, 48, CURDATE() + INTERVAL 1 DAY + INTERVAL 5 HOUR, NULL
FROM alunos a
INNER JOIN usuarios u ON a.usuario_id = u.id
WHERE u.email IN (
    'ana.paula@teste.com',
    'diego.oliveira@teste.com',
    'felipe.martins@teste.com',
    'henrique.almeida@teste.com'
);

-- Checkins na turma 69 (06:00) - 4 alunos
INSERT INTO checkins (tenant_id, aluno_id, turma_id, data_checkin, presente)
SELECT @tenant_id, a.id, 69, CURDATE() + INTERVAL 1 DAY + INTERVAL 6 HOUR, NULL
FROM alunos a
INNER JOIN usuarios u ON a.usuario_id = u.id
WHERE u.email IN (
    'bruno.costa@teste.com',
    'carla.fernandes@teste.com',
    'elena.rodrigues@teste.com',
    'gabriela.lima@teste.com'
);

-- =========================================
-- RESUMO:
-- 8 alunos criados com planos ativos
-- 8 checkins nas turmas de hoje (pendentes de presença)
-- 8 checkins nas turmas de amanhã (pendentes de presença)
-- Professor Carlos pode acessar /mobile/turma/47/detalhes e confirmar presença
-- =========================================

SELECT 'Seed executado com sucesso!' as status;
SELECT CONCAT('Alunos criados: ', COUNT(*)) as info FROM alunos WHERE nome IN ('Ana Paula Silva', 'Bruno Costa Santos', 'Carla Fernandes', 'Diego Oliveira', 'Elena Rodrigues', 'Felipe Martins', 'Gabriela Lima', 'Henrique Almeida');
SELECT CONCAT('Checkins criados: ', COUNT(*)) as info FROM checkins WHERE tenant_id = 2 AND presente IS NULL;
