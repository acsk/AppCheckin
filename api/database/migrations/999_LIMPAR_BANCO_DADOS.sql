-- ========================================
-- SCRIPT DE LIMPEZA DO BANCO DE DADOS
-- ========================================
-- Preserva: SuperAdmin (id=1), FormasPagamento, PlanosSistema
-- Remove: Todos os dados operacionais (usuario_id > 1, tenant_id > 1)
-- Data: 2026-01-19
-- Testado em: MySQL 8.0.44 / MySQL 8.0.44
-- Ordem: Respeita foreign keys (RESTRICT)
-- ========================================

SET FOREIGN_KEY_CHECKS = 0;

-- ========================================
-- SEÇÃO 1: WOD E CONTEÚDO
-- ========================================
DELETE FROM wod_resultados;
DELETE FROM wod_variacoes;
DELETE FROM wod_blocos;
DELETE FROM wods;

-- ========================================
-- SEÇÃO 2: PAGAMENTOS E FINANCEIRO
-- ========================================
DELETE FROM pagamentos_plano;
DELETE FROM pagamentos_contrato;
DELETE FROM contas_receber;

-- ========================================
-- SEÇÃO 3: MATRÍCULAS E HISTÓRICO
-- ========================================
DELETE FROM matriculas;
DELETE FROM historico_planos;

-- ========================================
-- SEÇÃO 4: OPERAÇÕES (CHECK-IN E AULAS)
-- ========================================
DELETE FROM inscricoes_turmas;
DELETE FROM checkins;

-- ========================================
-- SEÇÃO 5: TURMAS (DEVE VIR ANTES DE HORÁRIOS/DIAS)
-- ========================================
DELETE FROM turmas;

-- ========================================
-- SEÇÃO 6: HORÁRIOS E DIAS
-- ========================================
DELETE FROM horarios;
DELETE FROM dias;

-- ========================================
-- SEÇÃO 7: PLANOS (ANTES DE MODALIDADES - constraint RESTRICT)
-- ========================================
DELETE FROM planos;

-- ========================================
-- SEÇÃO 8: CADASTROS OPERACIONAIS
-- ========================================
DELETE FROM modalidades;
DELETE FROM professores;

-- ========================================
-- SEÇÃO 9: USUÁRIOS (PRESERVA SUPERADMIN id=1)
-- ========================================
DELETE FROM usuario_tenant WHERE usuario_id != 1;
DELETE FROM usuarios WHERE id != 1;

-- ========================================
-- SEÇÃO 10: TENANTS E CONFIGURAÇÕES
-- ========================================
DELETE FROM tenant_formas_pagamento WHERE tenant_id != 1;
DELETE FROM tenant_planos_sistema WHERE tenant_id != 1;
DELETE FROM tenants WHERE id != 1;

-- ========================================
-- SEÇÃO 9: RESETAR AUTO_INCREMENT
-- ========================================
ALTER TABLE wod_resultados AUTO_INCREMENT = 1;
ALTER TABLE wod_variacoes AUTO_INCREMENT = 1;
ALTER TABLE wod_blocos AUTO_INCREMENT = 1;
ALTER TABLE wods AUTO_INCREMENT = 1;
ALTER TABLE pagamentos_plano AUTO_INCREMENT = 1;
ALTER TABLE pagamentos_contrato AUTO_INCREMENT = 1;
ALTER TABLE contas_receber AUTO_INCREMENT = 1;
ALTER TABLE matriculas AUTO_INCREMENT = 1;
ALTER TABLE historico_planos AUTO_INCREMENT = 1;
ALTER TABLE inscricoes_turmas AUTO_INCREMENT = 1;
ALTER TABLE checkins AUTO_INCREMENT = 1;
ALTER TABLE turmas AUTO_INCREMENT = 1;
ALTER TABLE horarios AUTO_INCREMENT = 1;
ALTER TABLE dias AUTO_INCREMENT = 1;
ALTER TABLE modalidades AUTO_INCREMENT = 1;
ALTER TABLE professores AUTO_INCREMENT = 1;
ALTER TABLE planos AUTO_INCREMENT = 1;
ALTER TABLE usuarios AUTO_INCREMENT = 2;
ALTER TABLE usuario_tenant AUTO_INCREMENT = 1;
ALTER TABLE tenants AUTO_INCREMENT = 2;
ALTER TABLE tenant_formas_pagamento AUTO_INCREMENT = 1;
ALTER TABLE tenant_planos_sistema AUTO_INCREMENT = 1;

-- ========================================
-- SEÇÃO 11: INSERIR DADOS DE EXEMPLO
-- ========================================
-- Inserir Tenant (Escola de Natação)
INSERT INTO tenants (
    id, 
    nome, 
    slug,
    email, 
    cnpj,
    telefone,
    responsavel_nome,
    responsavel_cpf,
    responsavel_telefone,
    responsavel_email,
    endereco,
    cep,
    logradouro,
    numero,
    complemento,
    bairro,
    cidade, 
    estado, 
    ativo, 
    created_at, 
    updated_at
) VALUES (
    2,
    'Escola de Natação Aqua Masters',
    'escola-natacao-aqua-masters',
    'contato@aquamasters.com.br',
    '12345678000190',
    '(11) 98765-4321',
    'Ana Silva',
    '12345678901',
    '(11) 98765-4321',
    'admin@aquamasters.com.br',
    'Rua das Águas, 123 - Jardim das Flores',
    '01234-567',
    'Rua das Águas',
    '123',
    'Próximo ao parque',
    'Jardim das Flores',
    'São Paulo',
    'SP',
    1,
    NOW(),
    NOW()
);

-- Inserir Usuário Admin da Escola de Natação
INSERT INTO usuarios (
    id,
    nome,
    email,
    telefone,
    cpf,
    cidade,
    estado,
    ativo,
    role_id,
    senha_hash,
    created_at,
    updated_at
) VALUES (
    2,
    'Ana Silva - Admin Aqua Masters',
    'admin@aquamasters.com.br',
    '(11) 98765-4321',
    '12345678901',
    'São Paulo',
    'SP',
    1,
    2,
    '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcg7b3XeKeUxWdeS86E36P3wO2G',
    NOW(),
    NOW()
);

-- Associar Usuário ao Tenant
INSERT INTO usuario_tenant (
    usuario_id,
    tenant_id,
    status,
    data_inicio,
    created_at,
    updated_at
) VALUES (
    2,
    2,
    'ativo',
    CURDATE(),
    NOW(),
    NOW()
);

-- Inserir Modalidade (Natação)
INSERT INTO modalidades (
    tenant_id,
    nome,
    descricao,
    cor,
    icone,
    ativo,
    created_at,
    updated_at
) VALUES (
    2,
    'Natação',
    'Aulas de natação para todas as idades',
    '#3b82f6',
    'swim',
    1,
    NOW(),
    NOW()
);

-- Inserir Primeiro Professor
INSERT INTO professores (
    tenant_id,
    nome,
    email,
    telefone,
    ativo,
    created_at,
    updated_at
) VALUES (
    2,
    'Carlos Mendes',
    'carlos.mendes@aquamasters.com.br',
    '(11) 99876-5432',
    1,
    NOW(),
    NOW()
);

-- Inserir Segundo Professor
INSERT INTO professores (
    tenant_id,
    nome,
    email,
    telefone,
    ativo,
    created_at,
    updated_at
) VALUES (
    2,
    'Marcela Oliveira',
    'marcela.oliveira@aquamasters.com.br',
    '(11) 98765-4123',
    1,
    NOW(),
    NOW()
);

-- Associar Formas de Pagamento ao Tenant
INSERT INTO tenant_formas_pagamento (
    tenant_id,
    forma_pagamento_id,
    ativo,
    created_at,
    updated_at
) SELECT 2, id, 1, NOW(), NOW()
FROM formas_pagamento;

-- Associar Planos do Sistema ao Tenant (apenas um)
INSERT INTO tenant_planos_sistema (
    tenant_id,
    plano_id,
    plano_sistema_id,
    status_id,
    data_inicio,
    created_at,
    updated_at
) VALUES (
    2,
    1,
    1,
    1,
    NOW(),
    NOW(),
    NOW()
);

-- ========================================
-- SEÇÃO 10: REATIVAR INTEGRIDADE
-- ========================================
SET FOREIGN_KEY_CHECKS = 1;

-- ========================================
-- RESULTADO ESPERADO
-- ========================================
-- ✓ Tabela usuarios: id=1 (SuperAdmin) + id=2 (Admin Aqua Masters)
-- ✓ Tabela tenants: id=1 (Sistema) + id=2 (Escola de Natação)
-- ✓ Tabela professores: 2 professores na Aqua Masters
-- ✓ Tabela modalidades: Natação adicionada
-- ✓ Tabela formas_pagamento: preservada completa
-- ✓ Tabela planos_sistema: preservada completa
-- ✓ Tabela roles: preservada completa
-- ✓ Tabela status_*: preservadas
-- ✓ Todos os AUTO_INCREMENT: resetados
-- ========================================
