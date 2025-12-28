-- Script para popular dados de teste no sistema de contratos
-- Execute este script após as migrations estarem rodando

-- Limpar dados de teste anteriores (opcional)
-- DELETE FROM tenant_planos WHERE observacoes LIKE '%teste%' OR observacoes LIKE '%Contrato%';

-- Academia 1 (Sistema AppCheckin) - Contrato ATIVO
INSERT INTO tenant_planos (tenant_id, plano_id, data_inicio, data_vencimento, forma_pagamento, status, observacoes)
VALUES 
(1, 14, '2025-12-01', '2026-01-01', 'pix', 'ativo', 'Contrato mensal - Plano Ilimitado');

-- Academia 1 - Histórico (contratos anteriores)
INSERT INTO tenant_planos (tenant_id, plano_id, data_inicio, data_vencimento, forma_pagamento, status, observacoes)
VALUES 
(1, 13, '2025-10-01', '2025-11-01', 'cartao', 'inativo', 'Primeiro contrato - Plano Básico'),
(1, 13, '2025-11-01', '2025-12-01', 'cartao', 'inativo', 'Renovação automática');

-- Academia 2 (Academia Fitness Pro) - Contrato ATIVO próximo do vencimento
INSERT INTO tenant_planos (tenant_id, plano_id, data_inicio, data_vencimento, forma_pagamento, status, observacoes)
VALUES 
(2, 16, '2025-12-05', '2026-01-05', 'operadora', 'ativo', 'Plano Anual - Pagamento via operadora');

-- Academia 2 - Histórico
INSERT INTO tenant_planos (tenant_id, plano_id, data_inicio, data_vencimento, forma_pagamento, status, observacoes)
VALUES 
(2, 14, '2025-09-05', '2025-10-05', 'pix', 'inativo', 'Contrato inicial'),
(2, 14, '2025-10-05', '2025-11-05', 'pix', 'inativo', 'Renovação 1'),
(2, 14, '2025-11-05', '2025-12-05', 'operadora', 'inativo', 'Mudança de forma de pagamento'),
(2, 15, '2025-11-01', '2025-12-01', 'operadora', 'cancelado', 'Cancelado - cliente não utilizou');

-- Academia 3 (Gym Test 2) - Contrato VENCIDO (para teste de alertas)
INSERT INTO tenant_planos (tenant_id, plano_id, data_inicio, data_vencimento, forma_pagamento, status, observacoes)
VALUES 
(3, 13, '2025-11-15', '2025-12-15', 'pix', 'ativo', 'Contrato vencido - aguardando renovação');

-- Academia 3 - Histórico
INSERT INTO tenant_planos (tenant_id, plano_id, data_inicio, data_vencimento, forma_pagamento, status, observacoes)
VALUES 
(3, 13, '2025-08-15', '2025-09-15', 'pix', 'inativo', 'Contrato inicial'),
(3, 13, '2025-09-15', '2025-10-15', 'pix', 'inativo', 'Renovação 1'),
(3, 13, '2025-10-15', '2025-11-15', 'pix', 'inativo', 'Renovação 2');

-- Verificar os dados inseridos
SELECT 
    tp.id,
    t.nome as academia,
    p.nome as plano,
    p.valor,
    tp.data_inicio,
    tp.data_vencimento,
    tp.forma_pagamento,
    tp.status,
    DATEDIFF(tp.data_vencimento, CURDATE()) as dias_restantes,
    tp.observacoes
FROM tenant_planos tp
INNER JOIN tenants t ON tp.tenant_id = t.id
INNER JOIN planos p ON tp.plano_id = p.id
ORDER BY tp.tenant_id, tp.created_at DESC;

-- Estatísticas
SELECT 
    status,
    COUNT(*) as total,
    SUM(p.valor) as valor_total
FROM tenant_planos tp
INNER JOIN planos p ON tp.plano_id = p.id
GROUP BY status;

-- Contratos vencidos
SELECT 
    t.nome as academia,
    p.nome as plano,
    tp.data_vencimento,
    DATEDIFF(CURDATE(), tp.data_vencimento) as dias_atrasado
FROM tenant_planos tp
INNER JOIN tenants t ON tp.tenant_id = t.id
INNER JOIN planos p ON tp.plano_id = p.id
WHERE tp.status = 'ativo' 
AND tp.data_vencimento < CURDATE()
ORDER BY dias_atrasado DESC;


-- Verificar os dados inseridos
SELECT 
    tp.id,
    t.nome as academia,
    p.nome as plano,
    p.valor,
    tp.data_inicio,
    tp.data_vencimento,
    tp.forma_pagamento,
    tp.status,
    DATEDIFF(tp.data_vencimento, CURDATE()) as dias_restantes,
    tp.observacoes
FROM tenant_planos tp
INNER JOIN tenants t ON tp.tenant_id = t.id
INNER JOIN planos p ON tp.plano_id = p.id
ORDER BY tp.tenant_id, tp.created_at DESC;

-- Estatísticas
SELECT 
    status,
    COUNT(*) as total,
    SUM(p.valor) as valor_total
FROM tenant_planos tp
INNER JOIN planos p ON tp.plano_id = p.id
GROUP BY status;

-- Contratos vencidos
SELECT 
    t.nome as academia,
    p.nome as plano,
    tp.data_vencimento,
    DATEDIFF(CURDATE(), tp.data_vencimento) as dias_atrasado
FROM tenant_planos tp
INNER JOIN tenants t ON tp.tenant_id = t.id
INNER JOIN planos p ON tp.plano_id = p.id
WHERE tp.status = 'ativo' 
AND tp.data_vencimento < CURDATE()
ORDER BY dias_atrasado DESC;
