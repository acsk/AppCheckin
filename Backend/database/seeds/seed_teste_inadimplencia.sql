-- =====================================================
-- SEED: Teste de Inadimplência
-- Tenant: 5
-- CPF: 06435877050
-- =====================================================
-- Este seed cria um cenário para testar o comportamento
-- do sistema quando há parcelas em atraso
-- =====================================================

SET @tenant_id = 5;
SET @cpf = '06435877050';
SET @email = 'teste.inadimplencia@teste.com';

-- =====================================================
-- 1. CRIAR USUÁRIO (dados em MAIÚSCULO)
-- =====================================================

INSERT INTO usuarios (
    nome, 
    email, 
    email_global,
    senha_hash, 
    role_id, 
    cpf, 
    telefone,
    ativo
) VALUES (
    'MARIA SILVA TESTE',
    @email,
    @email,
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- password = 'password'
    1, -- Aluno
    @cpf,
    '81999998888',
    1
);

SET @usuario_id = LAST_INSERT_ID();

-- Vincular ao tenant
INSERT INTO usuario_tenant (usuario_id, tenant_id, status, data_inicio) 
VALUES (@usuario_id, @tenant_id, 'ativo', CURDATE());

SELECT CONCAT('✅ Usuário criado: ID = ', @usuario_id, ' | Nome: MARIA SILVA TESTE | CPF: ', @cpf) AS resultado;

-- =====================================================
-- 2. BUSCAR PLANO ATIVO DA MODALIDADE CROSSFIT (ID 5)
-- =====================================================

SET @plano_id = (
    SELECT id FROM planos 
    WHERE modalidade_id = 5 AND ativo = 1 
    LIMIT 1
);

SET @plano_valor = (
    SELECT valor FROM planos WHERE id = @plano_id
);

SELECT CONCAT('✅ Plano selecionado: ID = ', @plano_id, ' | Valor: R$ ', @plano_valor) AS resultado;

-- =====================================================
-- 3. CRIAR MATRÍCULA (iniciada há 2 meses)
-- =====================================================

INSERT INTO matriculas (
    tenant_id,
    usuario_id,
    plano_id,
    data_matricula,
    data_inicio,
    data_vencimento,
    valor,
    status,
    motivo,
    observacoes
) VALUES (
    @tenant_id,
    @usuario_id,
    @plano_id,
    DATE_SUB(CURDATE(), INTERVAL 60 DAY),  -- Matriculou há 60 dias
    DATE_SUB(CURDATE(), INTERVAL 60 DAY),  -- Início há 60 dias
    DATE_ADD(DATE_SUB(CURDATE(), INTERVAL 60 DAY), INTERVAL 30 DAY), -- Vencimento do 1º ciclo
    @plano_valor,
    'ativa',
    'nova',
    'MATRÍCULA DE TESTE - CENÁRIO INADIMPLÊNCIA'
);

SET @matricula_id = LAST_INSERT_ID();

SELECT CONCAT('✅ Matrícula criada: ID = ', @matricula_id) AS resultado;

-- =====================================================
-- 4. CRIAR PAGAMENTOS (1 pago + 1 vencido)
-- =====================================================

-- Pagamento 1: PAGO (referente ao 1º mês - há 60 dias)
INSERT INTO pagamentos_plano (
    tenant_id,
    matricula_id,
    usuario_id,
    plano_id,
    valor,
    data_vencimento,
    data_pagamento,
    status_pagamento_id,  -- 2 = Pago
    forma_pagamento_id,
    observacoes
) VALUES (
    @tenant_id,
    @matricula_id,
    @usuario_id,
    @plano_id,
    @plano_valor,
    DATE_SUB(CURDATE(), INTERVAL 60 DAY),  -- Venceu há 60 dias
    DATE_SUB(CURDATE(), INTERVAL 58 DAY),  -- Pagou 2 dias depois
    2,  -- Pago
    1,  -- Forma de pagamento (assumindo ID 1)
    'PAGAMENTO INICIAL - TESTE'
);

SELECT '✅ Pagamento 1 criado: PAGO' AS resultado;

-- Pagamento 2: VENCIDO/ATRASADO (referente ao 2º mês - há 30 dias)
INSERT INTO pagamentos_plano (
    tenant_id,
    matricula_id,
    usuario_id,
    plano_id,
    valor,
    data_vencimento,
    data_pagamento,
    status_pagamento_id,  -- 3 = Atrasado
    observacoes
) VALUES (
    @tenant_id,
    @matricula_id,
    @usuario_id,
    @plano_id,
    @plano_valor,
    DATE_SUB(CURDATE(), INTERVAL 30 DAY),  -- Venceu há 30 dias
    NULL,  -- NÃO PAGO
    3,  -- Atrasado
    'PARCELA EM ATRASO - TESTE DE INADIMPLÊNCIA'
);

SELECT '✅ Pagamento 2 criado: ATRASADO (30 dias)' AS resultado;

-- Pagamento 3: PENDENTE (referente ao mês atual)
INSERT INTO pagamentos_plano (
    tenant_id,
    matricula_id,
    usuario_id,
    plano_id,
    valor,
    data_vencimento,
    data_pagamento,
    status_pagamento_id,  -- 1 = Aguardando
    observacoes
) VALUES (
    @tenant_id,
    @matricula_id,
    @usuario_id,
    @plano_id,
    @plano_valor,
    CURDATE(),  -- Vence hoje
    NULL,  -- Ainda não pago
    1,  -- Aguardando
    'PARCELA DO MÊS ATUAL - TESTE'
);

SELECT '✅ Pagamento 3 criado: AGUARDANDO (vence hoje)' AS resultado;

-- =====================================================
-- 5. RESUMO FINAL
-- =====================================================

SELECT '========================================' AS '';
SELECT 'RESUMO DO SEED DE INADIMPLÊNCIA' AS '';
SELECT '========================================' AS '';

SELECT 
    u.id AS usuario_id,
    u.nome,
    u.cpf,
    u.email
FROM usuarios u 
WHERE u.id = @usuario_id;

SELECT 
    m.id AS matricula_id,
    m.status,
    m.data_matricula,
    m.data_vencimento,
    p.nome AS plano_nome,
    m.valor
FROM matriculas m
JOIN planos p ON m.plano_id = p.id
WHERE m.id = @matricula_id;

SELECT 
    pp.id AS pagamento_id,
    pp.valor,
    pp.data_vencimento,
    pp.data_pagamento,
    sp.nome AS status,
    CASE 
        WHEN pp.data_pagamento IS NOT NULL THEN 'OK'
        WHEN pp.data_vencimento < CURDATE() THEN CONCAT(DATEDIFF(CURDATE(), pp.data_vencimento), ' dias em atraso')
        ELSE 'No prazo'
    END AS situacao
FROM pagamentos_plano pp
JOIN status_pagamento sp ON pp.status_pagamento_id = sp.id
WHERE pp.matricula_id = @matricula_id
ORDER BY pp.data_vencimento;

SELECT '========================================' AS '';
SELECT '✅ SEED EXECUTADO COM SUCESSO!' AS '';
SELECT 'Login: teste.inadimplencia@teste.com' AS '';
SELECT 'Senha: password' AS '';
SELECT '========================================' AS '';
