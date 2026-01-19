-- Script de Validação: Correção de Usuários Duplicados
-- =====================================================
-- Este script demonstra o problema original e valida a solução

-- 1. VERIFICAR USUÁRIOS COM MÚLTIPLOS TENANTS
-- =====================================================
SELECT 
    u.id,
    u.nome,
    COUNT(DISTINCT ut.tenant_id) as total_tenants,
    GROUP_CONCAT(DISTINCT t.nome SEPARATOR ', ') as tenants_nomes
FROM usuarios u
INNER JOIN usuario_tenant ut ON u.id = ut.usuario_id
LEFT JOIN tenants t ON ut.tenant_id = t.id
GROUP BY u.id, u.nome
HAVING COUNT(DISTINCT ut.tenant_id) > 1
ORDER BY u.id;

-- Resultado esperado: Mostra usuários em múltiplos tenants
-- Exemplo:
-- | id | nome                | total_tenants | tenants_nomes                          |
-- |----|---------------------|---------------|----------------------------------------|
-- | 11 | CAROLINA FERREIRA   | 2             | Fitpro 7 - Plus, Sporte e Saúde...    |


-- 2. DEMONSTRAR O PROBLEMA ORIGINAL (Query antiga)
-- =====================================================
-- A query antiga retornava múltiplas linhas para usuários com vários tenants:
SELECT 
    u.id,
    u.nome,
    ut.tenant_id,
    t.nome as tenant_nome,
    COUNT(*) as vezes_retornado
FROM usuarios u
INNER JOIN usuario_tenant ut ON u.id = ut.usuario_id
LEFT JOIN tenants t ON ut.tenant_id = t.id
GROUP BY u.id, u.nome, ut.tenant_id, t.nome
HAVING COUNT(*) > 0
ORDER BY u.id;

-- Resultado: Mostra quantas vezes cada usuário aparecia


-- 3. VALIDAR A SOLUÇÃO (Deduplicação PHP)
-- =====================================================
-- A query agora é ordenada para garantir consistência:
SELECT 
    u.id,
    u.nome,
    u.email,
    ut.tenant_id,
    t.nome as tenant_nome,
    t.slug as tenant_slug,
    r.nome as role_nome,
    ut.status
FROM usuarios u
LEFT JOIN roles r ON u.role_id = r.id
INNER JOIN usuario_tenant ut ON u.id = ut.usuario_id
LEFT JOIN tenants t ON ut.tenant_id = t.id
ORDER BY u.id ASC, ut.status DESC, t.id ASC;

-- Nota: A deduplicação ocorre em PHP (no método listarTodos)
-- O PHP mantém apenas o primeiro registro de cada usuário (baseado em u.id)


-- 4. VERIFICAR O TOTAL CORRETO DE USUÁRIOS
-- =====================================================
SELECT 
    COUNT(DISTINCT u.id) as total_usuarios_unicos,
    COUNT(*) as total_registros_raw
FROM usuarios u
INNER JOIN usuario_tenant ut ON u.id = ut.usuario_id;

-- Resultado esperado:
-- | total_usuarios_unicos | total_registros_raw |
-- |----------------------|-------------------|
-- | N (usuários únicos)   | M (com duplicatas)|


-- 5. LISTAR TODOS OS USUÁRIOS (como faz a API agora)
-- =====================================================
-- Simulando o comportamento da API após a correção:

-- Passo 1: Query retorna todos os registros (pode ter duplicatas)
SET @total_registros = (
    SELECT COUNT(*)
    FROM usuarios u
    INNER JOIN usuario_tenant ut ON u.id = ut.usuario_id
);

-- Passo 2: PHP filtra duplicatas (mantendo apenas u.id único)
-- Resultado: Apenas usuários únicos são retornados

SELECT 
    u.id,
    u.nome,
    u.email,
    u.telefone,
    u.cpf,
    u.role_id,
    r.nome as role_nome,
    ut.status,
    ut.tenant_id,
    t.nome as tenant_nome,
    t.slug as tenant_slug,
    CASE WHEN ut.status = 'ativo' THEN 1 ELSE 0 END as ativo
FROM usuarios u
LEFT JOIN roles r ON u.role_id = r.id
INNER JOIN usuario_tenant ut ON u.id = ut.usuario_id
LEFT JOIN tenants t ON ut.tenant_id = t.id
ORDER BY u.id ASC, ut.status DESC, t.id ASC
LIMIT 20;

-- Nota: A deduplicação em PHP garante que apenas um registro por usuário é retornado


-- 6. VERIFICAR INTEGRIDADE DOS DADOS
-- =====================================================
-- Garantir que não há usuários órfãos
SELECT 
    u.id,
    u.nome,
    COUNT(ut.id) as total_vinculacoes
FROM usuarios u
LEFT JOIN usuario_tenant ut ON u.id = ut.usuario_id
GROUP BY u.id, u.nome
HAVING COUNT(ut.id) = 0;

-- Resultado: Lista usuários sem tenant (se houver)


-- 7. ESTATÍSTICAS DA SOLUÇÃO
-- =====================================================
SELECT 
    'Total Usuários Únicos' as metrica,
    COUNT(DISTINCT u.id) as valor
FROM usuarios u
INNER JOIN usuario_tenant ut ON u.id = ut.usuario_id

UNION ALL

SELECT 
    'Total Registros Raw (com duplicatas)',
    COUNT(*)
FROM usuarios u
INNER JOIN usuario_tenant ut ON u.id = ut.usuario_id

UNION ALL

SELECT 
    'Usuários com 2+ tenants',
    COUNT(DISTINCT u.id)
FROM usuarios u
INNER JOIN usuario_tenant ut ON u.id = ut.usuario_id
GROUP BY u.id
HAVING COUNT(DISTINCT ut.tenant_id) > 1;
