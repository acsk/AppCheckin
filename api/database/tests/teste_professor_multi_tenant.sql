-- =====================================================
-- TESTE: Associação de Professor Multi-Tenant
-- =====================================================
-- Este script testa o fluxo completo de associação de professor
-- a múltiplos tenants usando a nova estrutura tenant_professor
-- =====================================================

USE appcheckin;

-- ============================================
-- CENÁRIO 1: Professor em Múltiplos Tenants
-- ============================================

-- Criar professor teste (se não existir)
INSERT IGNORE INTO usuarios (id, nome, email, cpf, senha_hash, ativo)
VALUES (100, 'João Silva', 'joao.silva@teste.com', '12345678900', '$2y$10$hash_teste', 1);

INSERT IGNORE INTO professores (id, usuario_id, nome, ativo)
VALUES (100, 100, 'João Silva', 1);

-- Associar ao Tenant 2 (Academia Aqua Masters)
INSERT INTO tenant_professor (professor_id, tenant_id, status, data_inicio)
VALUES (100, 2, 'ativo', CURDATE())
ON DUPLICATE KEY UPDATE status = 'ativo';

-- Criar papel de professor no tenant
INSERT IGNORE INTO tenant_usuario_papel (tenant_id, usuario_id, papel_id, ativo)
VALUES (2, 100, 2, 1);

-- Verificar associação
SELECT 
    '=== Professor João Silva ===' as info,
    p.id as professor_id,
    p.nome,
    u.cpf,
    tp.tenant_id,
    t.nome as tenant_nome,
    tp.status,
    tp.data_inicio
FROM professores p
INNER JOIN usuarios u ON u.id = p.usuario_id
LEFT JOIN tenant_professor tp ON tp.professor_id = p.id
LEFT JOIN tenants t ON t.id = tp.tenant_id
WHERE p.id = 100;

-- ============================================
-- CENÁRIO 2: Simular Professor em 2 Tenants
-- ============================================

-- Criar segundo tenant para teste (se não existir)
INSERT IGNORE INTO tenants (id, nome, slug, email, ativo)
VALUES (3, 'Academia Teste B', 'academia-teste-b', 'contato@academiatesteb.com', 1);

-- Associar mesmo professor ao Tenant 3
INSERT INTO tenant_professor (professor_id, tenant_id, status, data_inicio)
VALUES (100, 3, 'ativo', CURDATE())
ON DUPLICATE KEY UPDATE status = 'ativo';

INSERT IGNORE INTO tenant_usuario_papel (tenant_id, usuario_id, papel_id, ativo)
VALUES (3, 100, 2, 1);

-- Verificar professor em múltiplos tenants
SELECT 
    '=== Professor em Múltiplos Tenants ===' as info,
    p.id as professor_id,
    p.nome,
    COUNT(DISTINCT tp.tenant_id) as qtd_tenants,
    GROUP_CONCAT(DISTINCT t.nome SEPARATOR ' | ') as tenants
FROM professores p
LEFT JOIN tenant_professor tp ON tp.professor_id = p.id AND tp.status = 'ativo'
LEFT JOIN tenants t ON t.id = tp.tenant_id
WHERE p.id = 100
GROUP BY p.id;

-- Detalhes por tenant
SELECT 
    '=== Detalhes por Tenant ===' as info,
    p.nome as professor,
    t.nome as tenant,
    tp.status,
    tp.data_inicio,
    tp.plano_id,
    tup.papel_id,
    tup.ativo as papel_ativo
FROM tenant_professor tp
INNER JOIN professores p ON p.id = tp.professor_id
INNER JOIN tenants t ON t.id = tp.tenant_id
LEFT JOIN tenant_usuario_papel tup 
    ON tup.usuario_id = p.usuario_id 
    AND tup.tenant_id = tp.tenant_id
    AND tup.papel_id = 2
WHERE p.id = 100
ORDER BY t.nome;

-- ============================================
-- CENÁRIO 3: Desassociar de um Tenant
-- ============================================

-- Desativar professor no Tenant 3 (soft delete)
UPDATE tenant_professor 
SET status = 'inativo', data_fim = CURDATE()
WHERE professor_id = 100 AND tenant_id = 3;

UPDATE tenant_usuario_papel
SET ativo = 0
WHERE usuario_id = 100 AND tenant_id = 3 AND papel_id = 2;

-- Verificar status após desassociação
SELECT 
    '=== Após Desassociação do Tenant 3 ===' as info,
    p.nome as professor,
    t.nome as tenant,
    tp.status,
    tp.data_inicio,
    tp.data_fim,
    tup.ativo as papel_ativo
FROM tenant_professor tp
INNER JOIN professores p ON p.id = tp.professor_id
INNER JOIN tenants t ON t.id = tp.tenant_id
LEFT JOIN tenant_usuario_papel tup 
    ON tup.usuario_id = p.usuario_id 
    AND tup.tenant_id = tp.tenant_id
    AND tup.papel_id = 2
WHERE p.id = 100
ORDER BY t.nome;

-- Professores ATIVOS por tenant
SELECT 
    '=== Professores Ativos por Tenant ===' as info,
    t.nome as tenant,
    COUNT(DISTINCT tp.professor_id) as qtd_professores
FROM tenant_professor tp
INNER JOIN tenants t ON t.id = tp.tenant_id
WHERE tp.status = 'ativo'
GROUP BY t.id
ORDER BY t.nome;

-- ============================================
-- LIMPEZA (opcional - descomente para limpar)
-- ============================================

-- DELETE FROM tenant_usuario_papel WHERE usuario_id = 100;
-- DELETE FROM tenant_professor WHERE professor_id = 100;
-- DELETE FROM professores WHERE id = 100;
-- DELETE FROM usuarios WHERE id = 100;
-- DELETE FROM tenants WHERE id = 3;

SELECT '=== TESTE CONCLUÍDO ===' as info;
