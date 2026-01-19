-- Script para popular dados de teste de contratos entre academias e planos do sistema
-- Execute este script após as migrations estarem rodando

-- Limpar dados de teste anteriores (opcional)
-- DELETE FROM tenant_planos_sistema WHERE observacoes LIKE '%teste%';

-- =====================================================
-- ASSOCIAR ACADEMIAS AOS PLANOS DO SISTEMA
-- =====================================================

-- Academia 1 (Sistema AppCheckin) - Plano Professional ATIVO
INSERT INTO tenant_planos_sistema (tenant_id, plano_id, plano_sistema_id, data_inicio, data_vencimento, forma_pagamento, status, observacoes)
VALUES 
(1, 10, 2, '2025-12-01', '2026-01-01', 'pix', 'ativo', 'Contrato mensal - Plano Professional');

-- Academia 1 - Histórico (contratos anteriores)
INSERT INTO tenant_planos_sistema (tenant_id, plano_id, plano_sistema_id, data_inicio, data_vencimento, forma_pagamento, status, observacoes)
VALUES 
(1, 10, 1, '2025-10-01', '2025-11-01', 'cartao', 'inativo', 'Primeiro contrato - Plano Starter'),
(1, 10, 1, '2025-11-01', '2025-12-01', 'cartao', 'inativo', 'Renovação automática - Plano Starter');

-- Academia 2 (Academia Fitness Pro) - Plano Enterprise ATIVO
INSERT INTO tenant_planos_sistema (tenant_id, plano_id, plano_sistema_id, data_inicio, data_vencimento, forma_pagamento, status, observacoes)
VALUES 
(2, 10, 3, '2025-12-05', '2026-01-05', 'operadora', 'ativo', 'Plano Enterprise - Grande porte');

-- Academia 2 - Histórico
INSERT INTO tenant_planos_sistema (tenant_id, plano_id, plano_sistema_id, data_inicio, data_vencimento, forma_pagamento, status, observacoes)
VALUES 
(2, 10, 2, '2025-09-05', '2025-10-05', 'pix', 'inativo', 'Contrato inicial - Professional'),
(2, 10, 2, '2025-10-05', '2025-11-05', 'pix', 'inativo', 'Renovação 1'),
(2, 10, 2, '2025-11-05', '2025-12-05', 'operadora', 'inativo', 'Mudança de forma de pagamento');

-- Academia 3 (Gym Test 2) - Plano Starter ATIVO (vencendo em breve)
INSERT INTO tenant_planos_sistema (tenant_id, plano_id, plano_sistema_id, data_inicio, data_vencimento, forma_pagamento, status, observacoes)
VALUES 
(3, 10, 1, '2025-12-20', '2026-01-20', 'pix', 'ativo', 'Plano básico para academia pequena');

-- Academia 3 - Histórico
INSERT INTO tenant_planos_sistema (tenant_id, plano_id, plano_sistema_id, data_inicio, data_vencimento, forma_pagamento, status, observacoes)
VALUES 
(3, 10, 1, '2025-08-15', '2025-09-15', 'pix', 'inativo', 'Contrato inicial'),
(3, 10, 1, '2025-09-15', '2025-10-15', 'pix', 'inativo', 'Renovação 1'),
(3, 10, 1, '2025-10-15', '2025-11-15', 'pix', 'inativo', 'Renovação 2'),
(3, 10, 1, '2025-11-15', '2025-12-20', 'pix', 'cancelado', 'Contrato cancelado - migrando para novo');

-- Verificar os tenants cadastrados para adicionar mais associações se existirem
-- SELECT * FROM tenants;

-- =====================================================
-- VERIFICAÇÕES E RELATÓRIOS
-- =====================================================

-- Verificar os contratos inseridos
SELECT 
    tp.id,
    t.nome as academia,
    t.email,
    ps.nome as plano,
    ps.valor,
    tp.data_inicio,
    tp.data_vencimento,
    tp.forma_pagamento,
    tp.status,
    DATEDIFF(tp.data_vencimento, CURDATE()) as dias_restantes,
    tp.observacoes
FROM tenant_planos_sistema tp
INNER JOIN tenants t ON tp.tenant_id = t.id
INNER JOIN planos_sistema ps ON tp.plano_sistema_id = ps.id
ORDER BY tp.tenant_id, tp.created_at DESC;

-- Estatísticas por status
SELECT 
    tp.status,
    COUNT(*) as total_contratos,
    SUM(ps.valor) as valor_total_mensal
FROM tenant_planos_sistema tp
INNER JOIN planos_sistema ps ON tp.plano_sistema_id = ps.id
GROUP BY tp.status
ORDER BY tp.status;

-- Contratos ativos por plano
SELECT 
    ps.nome as plano,
    ps.valor,
    COUNT(*) as academias_ativas,
    SUM(ps.valor) as receita_mensal
FROM tenant_planos_sistema tp
INNER JOIN planos_sistema ps ON tp.plano_sistema_id = ps.id
WHERE tp.status = 'ativo'
GROUP BY ps.id, ps.nome, ps.valor
ORDER BY academias_ativas DESC;

-- Contratos próximos do vencimento (próximos 15 dias)
SELECT 
    t.nome as academia,
    t.email,
    ps.nome as plano,
    tp.data_vencimento,
    DATEDIFF(tp.data_vencimento, CURDATE()) as dias_restantes
FROM tenant_planos_sistema tp
INNER JOIN tenants t ON tp.tenant_id = t.id
INNER JOIN planos_sistema ps ON tp.plano_sistema_id = ps.id
WHERE tp.status = 'ativo' 
AND tp.data_vencimento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 15 DAY)
ORDER BY tp.data_vencimento ASC;

-- Contratos vencidos
SELECT 
    t.nome as academia,
    t.email,
    ps.nome as plano,
    tp.data_vencimento,
    DATEDIFF(CURDATE(), tp.data_vencimento) as dias_atrasado
FROM tenant_planos_sistema tp
INNER JOIN tenants t ON tp.tenant_id = t.id
INNER JOIN planos_sistema ps ON tp.plano_sistema_id = ps.id
WHERE tp.status = 'ativo' 
AND tp.data_vencimento < CURDATE()
ORDER BY dias_atrasado DESC;

-- Receita total e projeção
SELECT 
    COUNT(CASE WHEN status = 'ativo' THEN 1 END) as contratos_ativos,
    SUM(CASE WHEN status = 'ativo' THEN ps.valor ELSE 0 END) as receita_mensal_atual,
    SUM(CASE WHEN status = 'ativo' THEN ps.valor * 12 ELSE 0 END) as projecao_anual
FROM tenant_planos_sistema tp
INNER JOIN planos_sistema ps ON tp.plano_sistema_id = ps.id;

-- Academias e seus planos atuais
SELECT 
    t.id,
    t.nome as academia,
    t.ativo as academia_ativa,
    ps.nome as plano_atual,
    ps.valor,
    tp.status as status_contrato,
    tp.data_vencimento,
    CASE 
        WHEN tp.data_vencimento < CURDATE() THEN 'VENCIDO'
        WHEN DATEDIFF(tp.data_vencimento, CURDATE()) <= 7 THEN 'VENCE EM BREVE'
        ELSE 'OK'
    END as alerta
FROM tenants t
LEFT JOIN tenant_planos_sistema tp ON t.id = tp.tenant_id AND tp.status = 'ativo'
LEFT JOIN planos_sistema ps ON tp.plano_sistema_id = ps.id
ORDER BY t.id;
