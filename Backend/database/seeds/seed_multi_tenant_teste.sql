-- Seed: Dados de teste para sistema multi-tenant
-- Cenário: Usuário com contratos em múltiplas academias
-- Data: 2025-12-26

-- Limpar dados de teste anteriores (cuidado em produção!)
-- DELETE FROM usuario_tenant WHERE tenant_id IN (2, 3, 4);
-- DELETE FROM tenants WHERE id IN (2, 3, 4);

-- Criar academias/tenants de teste
INSERT INTO tenants (id, nome, slug, email, telefone, endereco, ativo) VALUES
(2, 'CrossFit Elite', 'crossfit-elite', 'contato@crossfitelite.com', '(11) 98765-4321', 'Rua dos Atletas, 123 - São Paulo/SP', 1),
(3, 'Pilates Studio Premium', 'pilates-premium', 'contato@pilatespremium.com', '(11) 91234-5678', 'Av. das Flores, 456 - São Paulo/SP', 1),
(4, 'Yoga & Bem Estar', 'yoga-bem-estar', 'namaste@yogabemestar.com', '(11) 99876-5432', 'Praça da Paz, 789 - São Paulo/SP', 1);

-- Criar planos para cada academia
-- CrossFit Elite
INSERT INTO planos (tenant_id, nome, descricao, valor, periodo_dias, ativo) VALUES
(2, 'CrossFit Mensal', 'Acesso ilimitado às aulas de CrossFit', 250.00, 30, 1),
(2, 'CrossFit Trimestral', 'Plano de 3 meses com desconto', 650.00, 90, 1),
(2, 'CrossFit Anual', 'Plano anual com melhor custo-benefício', 2400.00, 365, 1);

-- Pilates Studio Premium
INSERT INTO planos (tenant_id, nome, descricao, valor, periodo_dias, ativo) VALUES
(3, 'Pilates Mensal', '8 aulas por mês', 280.00, 30, 1),
(3, 'Pilates Semestral', 'Plano de 6 meses', 1500.00, 180, 1),
(3, 'Pilates Ilimitado', 'Aulas ilimitadas por mês', 450.00, 30, 1);

-- Yoga & Bem Estar
INSERT INTO planos (tenant_id, nome, descricao, valor, periodo_dias, ativo) VALUES
(4, 'Yoga Mensal', 'Acesso a todas as aulas de Yoga', 200.00, 30, 1),
(4, 'Yoga + Meditação', 'Yoga e sessões de meditação guiada', 280.00, 30, 1),
(4, 'Yoga Anual', 'Plano anual com bônus de workshops', 2000.00, 365, 1);

-- Criar usuários de teste (se não existirem)
-- Usuário 1: João Silva - Tem contrato na Academia Principal e CrossFit
-- Usuário 2: Maria Santos - Tem contrato em todas as academias
-- Usuário 3: Pedro Costa - Tem contrato apenas no Pilates

-- Verificar se usuário teste já existe, senão criar
INSERT IGNORE INTO usuarios (tenant_id, nome, email, email_global, senha_hash, role_id, ativo) VALUES
(1, 'João Silva', 'joao@teste.com', 'joao@teste.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 2, 1), -- senha: password
(1, 'Maria Santos', 'maria@teste.com', 'maria@teste.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 2, 1),
(3, 'Pedro Costa', 'pedro@teste.com', 'pedro@teste.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 2, 1);

-- Obter IDs dos usuários criados
SET @joao_id = (SELECT id FROM usuarios WHERE email_global = 'joao@teste.com' LIMIT 1);
SET @maria_id = (SELECT id FROM usuarios WHERE email_global = 'maria@teste.com' LIMIT 1);
SET @pedro_id = (SELECT id FROM usuarios WHERE email_global = 'pedro@teste.com' LIMIT 1);

-- Obter IDs dos planos
SET @plano_academia_principal = (SELECT id FROM planos WHERE tenant_id = 1 AND nome LIKE '%Mensal%' LIMIT 1);
SET @plano_crossfit_mensal = (SELECT id FROM planos WHERE tenant_id = 2 AND nome = 'CrossFit Mensal' LIMIT 1);
SET @plano_crossfit_anual = (SELECT id FROM planos WHERE tenant_id = 2 AND nome = 'CrossFit Anual' LIMIT 1);
SET @plano_pilates_semestral = (SELECT id FROM planos WHERE tenant_id = 3 AND nome = 'Pilates Semestral' LIMIT 1);
SET @plano_yoga_mensal = (SELECT id FROM planos WHERE tenant_id = 4 AND nome = 'Yoga Mensal' LIMIT 1);

-- Criar vínculos multi-tenant

-- João Silva: Academia Principal + CrossFit Elite
INSERT INTO usuario_tenant (usuario_id, tenant_id, plano_id, status, data_inicio, data_fim) VALUES
(@joao_id, 1, @plano_academia_principal, 'ativo', DATE_SUB(CURRENT_DATE, INTERVAL 3 MONTH), DATE_ADD(CURRENT_DATE, INTERVAL 1 MONTH)),
(@joao_id, 2, @plano_crossfit_mensal, 'ativo', DATE_SUB(CURRENT_DATE, INTERVAL 1 MONTH), DATE_ADD(CURRENT_DATE, INTERVAL 1 MONTH))
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;

-- Maria Santos: Todas as academias (super usuária)
INSERT INTO usuario_tenant (usuario_id, tenant_id, plano_id, status, data_inicio, data_fim) VALUES
(@maria_id, 1, @plano_academia_principal, 'ativo', DATE_SUB(CURRENT_DATE, INTERVAL 6 MONTH), DATE_ADD(CURRENT_DATE, INTERVAL 6 MONTH)),
(@maria_id, 2, @plano_crossfit_anual, 'ativo', DATE_SUB(CURRENT_DATE, INTERVAL 2 MONTH), DATE_ADD(CURRENT_DATE, INTERVAL 10 MONTH)),
(@maria_id, 3, @plano_pilates_semestral, 'ativo', DATE_SUB(CURRENT_DATE, INTERVAL 1 MONTH), DATE_ADD(CURRENT_DATE, INTERVAL 5 MONTH)),
(@maria_id, 4, @plano_yoga_mensal, 'ativo', CURRENT_DATE, DATE_ADD(CURRENT_DATE, INTERVAL 1 MONTH))
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;

-- Pedro Costa: Apenas Pilates (contrato inativo na yoga - cancelado)
INSERT INTO usuario_tenant (usuario_id, tenant_id, plano_id, status, data_inicio, data_fim) VALUES
(@pedro_id, 3, @plano_pilates_semestral, 'ativo', DATE_SUB(CURRENT_DATE, INTERVAL 2 MONTH), DATE_ADD(CURRENT_DATE, INTERVAL 4 MONTH)),
(@pedro_id, 4, @plano_yoga_mensal, 'cancelado', DATE_SUB(CURRENT_DATE, INTERVAL 6 MONTH), DATE_SUB(CURRENT_DATE, INTERVAL 3 MONTH))
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;

-- Resumo dos dados criados
SELECT 'Dados de teste criados com sucesso!' as status;

SELECT 
    u.nome as usuario,
    u.email_global,
    COUNT(ut.id) as total_contratos,
    COUNT(CASE WHEN ut.status = 'ativo' THEN 1 END) as contratos_ativos
FROM usuarios u
LEFT JOIN usuario_tenant ut ON u.id = ut.usuario_id
WHERE u.email_global IN ('joao@teste.com', 'maria@teste.com', 'pedro@teste.com')
GROUP BY u.id, u.nome, u.email_global;

-- Detalhes dos contratos
SELECT 
    u.nome as usuario,
    t.nome as academia,
    p.nome as plano,
    ut.status,
    ut.data_inicio,
    ut.data_fim
FROM usuario_tenant ut
INNER JOIN usuarios u ON ut.usuario_id = u.id
INNER JOIN tenants t ON ut.tenant_id = t.id
LEFT JOIN planos p ON ut.plano_id = p.id
WHERE u.email_global IN ('joao@teste.com', 'maria@teste.com', 'pedro@teste.com')
ORDER BY u.nome, t.nome;
