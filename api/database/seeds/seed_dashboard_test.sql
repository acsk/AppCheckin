-- Script para criar massa de dados para testar o Dashboard Admin
-- Data base: 24 de novembro de 2025

USE appcheckin;

-- Garantir que temos planos cadastrados
INSERT INTO planos (tenant_id, nome, descricao, valor, duracao_dias, checkins_mensais, ativo) VALUES
(1, 'FREE', 'Plano gratuito - acesso limitado', 0.00, 30, 4, 1),
(1, 'Plano Mensal Básico', 'Acesso ilimitado por 30 dias', 99.90, 30, NULL, 1),
(1, 'Plano Trimestral', 'Acesso ilimitado por 90 dias com desconto', 259.90, 90, NULL, 1),
(1, 'Plano Semestral', 'Acesso ilimitado por 180 dias - melhor custo/benefício', 479.90, 180, NULL, 1),
(1, 'Plano Anual', 'Acesso ilimitado por 365 dias - super desconto', 899.90, 365, NULL, 1),
(1, 'Plano Semanal', 'Acesso por 7 dias - ideal para experimentar', 39.90, 7, NULL, 1)
ON DUPLICATE KEY UPDATE nome = VALUES(nome);

-- Inserir alunos com diferentes status
-- Total: 150 alunos conforme mockado

-- 1. Alunos ATIVOS com planos válidos (120 alunos)
-- 100 alunos com plano mensal ativo
INSERT INTO usuarios (tenant_id, nome, email, senha, role_id, plano_id, data_vencimento_plano, created_at, updated_at) VALUES
(1, 'Ana Silva Santos', 'ana.silva@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1, DATE_ADD(CURDATE(), INTERVAL 15 DAY), '2025-10-15 10:00:00', NOW()),
(1, 'Carlos Eduardo Lima', 'carlos.lima@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1, DATE_ADD(CURDATE(), INTERVAL 20 DAY), '2025-09-20 11:30:00', NOW()),
(1, 'Beatriz Costa Oliveira', 'beatriz.costa@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1, DATE_ADD(CURDATE(), INTERVAL 25 DAY), '2025-08-10 14:00:00', NOW()),
(1, 'Diego Fernandes Souza', 'diego.souza@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 2, DATE_ADD(CURDATE(), INTERVAL 45 DAY), '2025-07-05 09:00:00', NOW()),
(1, 'Eliana Martins Rocha', 'eliana.rocha@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 2, DATE_ADD(CURDATE(), INTERVAL 50 DAY), '2025-06-15 16:00:00', NOW()),
(1, 'Fernando Alves Pereira', 'fernando.pereira@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1, DATE_ADD(CURDATE(), INTERVAL 10 DAY), '2025-10-01 08:30:00', NOW()),
(1, 'Gabriela Santos Lima', 'gabriela.lima@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 3, DATE_ADD(CURDATE(), INTERVAL 90 DAY), '2025-05-20 10:00:00', NOW()),
(1, 'Henrique Costa Silva', 'henrique.silva@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1, DATE_ADD(CURDATE(), INTERVAL 12 DAY), '2025-09-10 15:00:00', NOW()),
(1, 'Isabella Oliveira Santos', 'isabella.santos@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1, DATE_ADD(CURDATE(), INTERVAL 18 DAY), '2025-08-25 11:00:00', NOW()),
(1, 'João Pedro Martins', 'joao.martins@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1, DATE_ADD(CURDATE(), INTERVAL 22 DAY), '2025-07-30 09:30:00', NOW()),
(1, 'Karina Souza Fernandes', 'karina.fernandes@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 4, DATE_ADD(CURDATE(), INTERVAL 180 DAY), '2025-11-01 07:00:00', NOW()),
(1, 'Leonardo Rocha Alves', 'leonardo.alves@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1, DATE_ADD(CURDATE(), INTERVAL 28 DAY), '2025-10-20 13:00:00', NOW()),
(1, 'Mariana Lima Costa', 'mariana.costa@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1, DATE_ADD(CURDATE(), INTERVAL 14 DAY), '2025-09-05 10:30:00', NOW()),
(1, 'Nicolas Pereira Santos', 'nicolas.santos@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 2, DATE_ADD(CURDATE(), INTERVAL 60 DAY), '2025-08-01 12:00:00', NOW()),
(1, 'Olivia Silva Oliveira', 'olivia.oliveira@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1, DATE_ADD(CURDATE(), INTERVAL 16 DAY), '2025-10-10 14:30:00', NOW());

-- Mais 85 alunos ativos distribuídos
INSERT INTO usuarios (tenant_id, nome, email, senha, role_id, plano_id, data_vencimento_plano, created_at, updated_at)
SELECT 
    1,
    CONCAT('Aluno Teste ', n),
    CONCAT('aluno', n, '@teste.com'),
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    1,
    CASE 
        WHEN n % 4 = 0 THEN 1
        WHEN n % 4 = 1 THEN 2
        WHEN n % 4 = 2 THEN 3
        ELSE 4
    END,
    DATE_ADD(CURDATE(), INTERVAL (10 + (n % 50)) DAY),
    DATE_SUB(NOW(), INTERVAL (n % 150) DAY),
    NOW()
FROM (
    SELECT @row := @row + 1 AS n
    FROM (SELECT 0 UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) t1,
         (SELECT 0 UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) t2,
         (SELECT @row := 15) r
    LIMIT 85
) numbers;

-- 2. Alunos INATIVOS sem plano ou com plano vencido (20 alunos)
INSERT INTO usuarios (tenant_id, nome, email, senha, role_id, plano_id, data_vencimento_plano, created_at, updated_at) VALUES
(1, 'Pedro Inativo Silva', 'pedro.inativo@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, NULL, NULL, '2025-05-10 10:00:00', NOW()),
(1, 'Rita Sem Plano Costa', 'rita.costa@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, NULL, NULL, '2025-06-15 11:00:00', NOW()),
(1, 'Samuel Vencido Lima', 'samuel.lima@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1, DATE_SUB(CURDATE(), INTERVAL 5 DAY), '2025-04-20 09:00:00', NOW()),
(1, 'Tatiana Expirado Santos', 'tatiana.santos@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1, DATE_SUB(CURDATE(), INTERVAL 10 DAY), '2025-03-15 14:00:00', NOW()),
(1, 'Ubiratan Pausado Souza', 'ubiratan.souza@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, NULL, NULL, '2025-02-10 08:00:00', NOW());

-- Mais 15 alunos inativos
INSERT INTO usuarios (tenant_id, nome, email, senha, role_id, plano_id, data_vencimento_plano, created_at, updated_at)
SELECT 
    1,
    CONCAT('Inativo Teste ', n),
    CONCAT('inativo', n, '@teste.com'),
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    1,
    IF(n % 2 = 0, NULL, 1),
    IF(n % 2 = 0, NULL, DATE_SUB(CURDATE(), INTERVAL (5 + (n % 15)) DAY)),
    DATE_SUB(NOW(), INTERVAL (120 + n) DAY),
    NOW()
FROM (
    SELECT @row2 := @row2 + 1 AS n
    FROM (SELECT 0 UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4) t1,
         (SELECT 0 UNION SELECT 1 UNION SELECT 2) t2,
         (SELECT @row2 := 5) r
    LIMIT 15
) numbers2;

-- 3. Alunos NOVOS deste mês (12 alunos) - criados em novembro/2025
INSERT INTO usuarios (tenant_id, nome, email, senha, role_id, plano_id, data_vencimento_plano, created_at, updated_at) VALUES
(1, 'Victor Novo Silva', 'victor.novo@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1, DATE_ADD(CURDATE(), INTERVAL 25 DAY), '2025-11-01 09:00:00', NOW()),
(1, 'Wanda Novata Costa', 'wanda.costa@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1, DATE_ADD(CURDATE(), INTERVAL 28 DAY), '2025-11-03 10:30:00', NOW()),
(1, 'Xavier Recente Lima', 'xavier.lima@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 5, DATE_ADD(CURDATE(), INTERVAL 5 DAY), '2025-11-05 11:00:00', NOW()),
(1, 'Yasmin Fresh Santos', 'yasmin.santos@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1, DATE_ADD(CURDATE(), INTERVAL 30 DAY), '2025-11-08 14:00:00', NOW()),
(1, 'Zeca Novissimo Souza', 'zeca.souza@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 2, DATE_ADD(CURDATE(), INTERVAL 85 DAY), '2025-11-10 15:30:00', NOW()),
(1, 'Amanda Estreante Rocha', 'amanda.rocha@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1, DATE_ADD(CURDATE(), INTERVAL 27 DAY), '2025-11-12 08:00:00', NOW()),
(1, 'Bruno Iniciante Alves', 'bruno.alves@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1, DATE_ADD(CURDATE(), INTERVAL 29 DAY), '2025-11-15 09:30:00', NOW()),
(1, 'Camila Primeira Vez', 'camila.primeira@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 3, DATE_ADD(CURDATE(), INTERVAL 175 DAY), '2025-11-17 10:00:00', NOW()),
(1, 'Daniel Começando Pereira', 'daniel.pereira@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1, DATE_ADD(CURDATE(), INTERVAL 26 DAY), '2025-11-19 11:30:00', NOW()),
(1, 'Eduarda Estreia Silva', 'eduarda.silva@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1, DATE_ADD(CURDATE(), INTERVAL 24 DAY), '2025-11-20 13:00:00', NOW()),
(1, 'Fabio Novato Costa', 'fabio.costa@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 2, DATE_ADD(CURDATE(), INTERVAL 88 DAY), '2025-11-22 14:30:00', NOW()),
(1, 'Giovana Acabou Entrar', 'giovana.entrar@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1, DATE_ADD(CURDATE(), INTERVAL 23 DAY), '2025-11-23 16:00:00', NOW());

-- 4. Alunos com planos VENCENDO nos próximos 7 dias (8 alunos)
INSERT INTO usuarios (tenant_id, nome, email, senha, role_id, plano_id, data_vencimento_plano, created_at, updated_at) VALUES
(1, 'Hugo Vencendo Logo', 'hugo.vencendo@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '2025-09-01 10:00:00', NOW()),
(1, 'Iris Expirando Breve', 'iris.expirando@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1, DATE_ADD(CURDATE(), INTERVAL 2 DAY), '2025-08-15 11:00:00', NOW()),
(1, 'Jorge Quase Fim', 'jorge.quase@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1, DATE_ADD(CURDATE(), INTERVAL 3 DAY), '2025-07-20 09:00:00', NOW()),
(1, 'Kelly Terminando Plano', 'kelly.terminando@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1, DATE_ADD(CURDATE(), INTERVAL 4 DAY), '2025-06-10 14:00:00', NOW()),
(1, 'Lucas Ultimo Dia', 'lucas.ultimo@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1, DATE_ADD(CURDATE(), INTERVAL 5 DAY), '2025-05-25 08:00:00', NOW()),
(1, 'Monica Proximo Vencer', 'monica.proximo@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1, DATE_ADD(CURDATE(), INTERVAL 6 DAY), '2025-04-30 15:00:00', NOW()),
(1, 'Nathan Acabando Logo', 'nathan.acabando@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1, DATE_ADD(CURDATE(), INTERVAL 7 DAY), '2025-03-15 10:30:00', NOW()),
(1, 'Olga Vence Semana', 'olga.vence@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 5, DATE_ADD(CURDATE(), INTERVAL 3 DAY), '2025-11-18 12:00:00', NOW());

-- Criar dias e horários para check-ins
INSERT INTO dias (tenant_id, data, ativo) VALUES
(1, CURDATE(), 1),
(1, DATE_SUB(CURDATE(), INTERVAL 1 DAY), 1),
(1, DATE_SUB(CURDATE(), INTERVAL 2 DAY), 1),
(1, DATE_SUB(CURDATE(), INTERVAL 3 DAY), 1),
(1, DATE_SUB(CURDATE(), INTERVAL 4 DAY), 1),
(1, DATE_SUB(CURDATE(), INTERVAL 5 DAY), 1),
(1, DATE_SUB(CURDATE(), INTERVAL 6 DAY), 1)
ON DUPLICATE KEY UPDATE ativo = 1;

-- Criar horários para os dias
INSERT INTO horarios (dia_id, hora, vagas, ativo)
SELECT d.id, '06:00:00', 20, 1 FROM dias d WHERE d.tenant_id = 1 AND d.data >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
UNION ALL
SELECT d.id, '07:00:00', 25, 1 FROM dias d WHERE d.tenant_id = 1 AND d.data >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
UNION ALL
SELECT d.id, '08:00:00', 25, 1 FROM dias d WHERE d.tenant_id = 1 AND d.data >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
UNION ALL
SELECT d.id, '09:00:00', 20, 1 FROM dias d WHERE d.tenant_id = 1 AND d.data >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
UNION ALL
SELECT d.id, '17:00:00', 30, 1 FROM dias d WHERE d.tenant_id = 1 AND d.data >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
UNION ALL
SELECT d.id, '18:00:00', 35, 1 FROM dias d WHERE d.tenant_id = 1 AND d.data >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
UNION ALL
SELECT d.id, '19:00:00', 30, 1 FROM dias d WHERE d.tenant_id = 1 AND d.data >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
ON DUPLICATE KEY UPDATE vagas = VALUES(vagas);

-- Criar check-ins HOJE (45 check-ins)
INSERT INTO checkins (usuario_id, horario_id, created_at)
SELECT 
    u.id,
    h.id,
    CONCAT(CURDATE(), ' ', TIME(h.hora))
FROM usuarios u
CROSS JOIN horarios h
INNER JOIN dias d ON h.dia_id = d.id
WHERE u.tenant_id = 1 
  AND u.role_id = 1
  AND u.plano_id IS NOT NULL
  AND d.data = CURDATE()
  AND RAND() < 0.3
LIMIT 45
ON DUPLICATE KEY UPDATE created_at = VALUES(created_at);

-- Criar check-ins do MÊS ATUAL (novembro) distribuídos nos dias anteriores
-- Total deve chegar a ~890 check-ins no mês
INSERT INTO checkins (usuario_id, horario_id, created_at)
SELECT 
    u.id,
    h.id,
    CONCAT(d.data, ' ', TIME(h.hora))
FROM usuarios u
CROSS JOIN horarios h
INNER JOIN dias d ON h.dia_id = d.id
WHERE u.tenant_id = 1 
  AND u.role_id = 1
  AND u.plano_id IS NOT NULL
  AND d.data >= '2025-11-01'
  AND d.data < CURDATE()
  AND RAND() < 0.8
LIMIT 845
ON DUPLICATE KEY UPDATE created_at = VALUES(created_at);

-- Resumo dos dados criados
SELECT 'Resumo da Massa de Dados Criada' as Info;

SELECT 
    COUNT(*) as total_alunos,
    SUM(CASE WHEN plano_id IS NOT NULL AND (data_vencimento_plano IS NULL OR data_vencimento_plano >= CURDATE()) THEN 1 ELSE 0 END) as alunos_ativos,
    SUM(CASE WHEN plano_id IS NULL OR (data_vencimento_plano IS NOT NULL AND data_vencimento_plano < CURDATE()) THEN 1 ELSE 0 END) as alunos_inativos,
    SUM(CASE WHEN YEAR(created_at) = 2025 AND MONTH(created_at) = 11 THEN 1 ELSE 0 END) as novos_mes,
    SUM(CASE WHEN data_vencimento_plano BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as vencendo_7_dias
FROM usuarios 
WHERE tenant_id = 1 AND role_id = 1;

SELECT 
    COUNT(*) as total_checkins_hoje
FROM checkins c
INNER JOIN usuarios u ON c.usuario_id = u.id
WHERE u.tenant_id = 1 AND DATE(c.created_at) = CURDATE();

SELECT 
    COUNT(*) as total_checkins_mes
FROM checkins c
INNER JOIN usuarios u ON c.usuario_id = u.id
WHERE u.tenant_id = 1 
  AND YEAR(c.created_at) = 2025 
  AND MONTH(c.created_at) = 11;

SELECT 
    SUM(p.valor) as receita_mensal_estimada
FROM usuarios u
INNER JOIN planos p ON u.plano_id = p.id
WHERE u.tenant_id = 1
  AND u.role_id = 1
  AND (u.data_vencimento_plano IS NULL OR u.data_vencimento_plano >= CURDATE());

SELECT '✅ Massa de dados criada com sucesso!' as Status;
