-- Seed de dados para teste de Contas a Receber
-- Este script gera contas variadas para testar o relatório

-- Obter ID de um usuário admin existente para usar como criador
SET @admin_id = (SELECT id FROM usuarios WHERE tenant_id = 1 AND role_id IN (2,3) LIMIT 1);

-- Se não houver admin, usar o primeiro usuário
SET @admin_id = IFNULL(@admin_id, (SELECT id FROM usuarios WHERE tenant_id = 1 LIMIT 1));

-- Limpar dados existentes (opcional - descomente se quiser resetar)
-- DELETE FROM contas_receber WHERE tenant_id = 1;

-- Inserir contas PAGAS (com valor_liquido e valor_desconto)
INSERT INTO contas_receber (
    tenant_id, usuario_id, plano_id, historico_plano_id, 
    valor, valor_liquido, valor_desconto,
    data_vencimento, data_pagamento, 
    status, forma_pagamento_id,
    referencia_mes, recorrente, intervalo_dias,
    criado_por, baixa_por,
    created_at, updated_at
) 
SELECT 
    1 as tenant_id, 
    u.id as usuario_id, 
    p.id as plano_id,
    NULL as historico_plano_id,
    p.valor, 
    p.valor as valor_liquido, 
    '0.00' as valor_desconto,
    DATE_ADD(CURDATE(), INTERVAL seq.n DAY) as data_vencimento,
    DATE_ADD(CURDATE(), INTERVAL seq.n DAY) as data_pagamento,
    'pago' as status,
    2 as forma_pagamento_id,
    DATE_FORMAT(DATE_ADD(CURDATE(), INTERVAL seq.n DAY), '%Y-%m') as referencia_mes,
    1 as recorrente,
    p.duracao_dias as intervalo_dias,
    @admin_id as criado_por,
    @admin_id as baixa_por,
    NOW() as created_at,
    NOW() as updated_at
FROM 
    (SELECT id, valor, duracao_dias FROM planos WHERE tenant_id = 1 LIMIT 3) p
    CROSS JOIN usuarios u
    CROSS JOIN (SELECT -30 as n UNION SELECT -20 UNION SELECT -10 UNION SELECT -5) seq
WHERE 
    u.tenant_id = 1 
    AND u.role_id = 1
LIMIT 15;

-- Inserir contas PENDENTES (sem valor_liquido e valor_desconto)
INSERT INTO contas_receber (
    tenant_id, usuario_id, plano_id, historico_plano_id, 
    valor, valor_liquido, valor_desconto,
    data_vencimento, data_pagamento, 
    status, forma_pagamento_id,
    referencia_mes, recorrente, intervalo_dias,
    criado_por, baixa_por,
    created_at, updated_at
) 
SELECT 
    1 as tenant_id, 
    u.id as usuario_id, 
    p.id as plano_id,
    NULL as historico_plano_id,
    p.valor, 
    NULL as valor_liquido, 
    NULL as valor_desconto,
    DATE_ADD(CURDATE(), INTERVAL seq.n DAY) as data_vencimento,
    NULL as data_pagamento,
    'pendente' as status,
    NULL as forma_pagamento_id,
    DATE_FORMAT(DATE_ADD(CURDATE(), INTERVAL seq.n DAY), '%Y-%m') as referencia_mes,
    1 as recorrente,
    p.duracao_dias as intervalo_dias,
    @admin_id as criado_por,
    NULL as baixa_por,
    NOW() as created_at,
    NOW() as updated_at
FROM 
    (SELECT id, valor, duracao_dias FROM planos WHERE tenant_id = 1 LIMIT 3) p
    CROSS JOIN usuarios u
    CROSS JOIN (SELECT 5 as n UNION SELECT 10 UNION SELECT 15) seq
WHERE 
    u.tenant_id = 1 
    AND u.role_id = 1
LIMIT 10;

-- Verificar dados inseridos
SELECT 
    COUNT(*) as total_contas,
    SUM(CASE WHEN status = 'pago' THEN 1 ELSE 0 END) as pagas,
    SUM(CASE WHEN status = 'pendente' THEN 1 ELSE 0 END) as pendentes,
    SUM(CASE WHEN status = 'pago' THEN COALESCE(valor_liquido, 0) ELSE 0 END) as total_pago,
    SUM(CASE WHEN status = 'pendente' THEN valor ELSE 0 END) as total_pendente
FROM contas_receber 
WHERE tenant_id = 1;
