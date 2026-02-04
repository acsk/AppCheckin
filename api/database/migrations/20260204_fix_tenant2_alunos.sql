-- =====================================================
-- CORREÇÃO TENANT 2: Popular tenant_usuario_papel
-- Data: 04/02/2026
-- Problema: Alunos não aparecem no endpoint /admin/alunos
-- Solução: Inserir registros faltantes em tenant_usuario_papel
-- =====================================================

SET @tenant_id = 2;
SET @papel_aluno = 1;

SELECT '🔧 CORREÇÃO: Populando tenant_usuario_papel para alunos do Tenant 2' AS titulo;
SELECT '' AS '';

-- =====================================================
-- PASSO 1: Verificação pré-correção
-- =====================================================
SELECT '1️⃣ Antes da correção...' AS etapa;

SELECT 
    COUNT(*) AS alunos_ativos_total
FROM alunos
WHERE ativo = 1;

SELECT 
    COUNT(*) AS alunos_em_tenant_usuario_papel
FROM alunos a
INNER JOIN tenant_usuario_papel tup ON tup.usuario_id = a.usuario_id
WHERE tup.tenant_id = @tenant_id 
AND tup.papel_id = @papel_aluno
AND a.ativo = 1;

SELECT 
    COUNT(*) AS alunos_faltando
FROM alunos a
WHERE a.ativo = 1
AND NOT EXISTS (
    SELECT 1 FROM tenant_usuario_papel tup
    WHERE tup.usuario_id = a.usuario_id
    AND tup.tenant_id = @tenant_id
    AND tup.papel_id = @papel_aluno
);

SELECT '' AS '';

-- =====================================================
-- PASSO 2: INSERÇÃO DOS REGISTROS FALTANTES
-- =====================================================
SELECT '2️⃣ Inserindo registros faltantes...' AS etapa;

-- ✅ REGRA: Vincular APENAS alunos que TÊM MATRÍCULA no tenant 2
-- Fonte da verdade: tabela `matriculas`
INSERT INTO tenant_usuario_papel (tenant_id, usuario_id, papel_id, ativo, created_at, updated_at)
SELECT DISTINCT
    @tenant_id,
    a.usuario_id,
    @papel_aluno,
    1,
    NOW(),
    NOW()
FROM alunos a
INNER JOIN matriculas m ON m.usuario_id = a.usuario_id AND m.tenant_id = @tenant_id
WHERE a.ativo = 1
AND NOT EXISTS (
    SELECT 1 FROM tenant_usuario_papel tup
    WHERE tup.usuario_id = a.usuario_id
    AND tup.tenant_id = @tenant_id
    AND tup.papel_id = @papel_aluno
);

-- Contar quantos foram inseridos
SELECT ROW_COUNT() AS registros_inseridos;

SELECT '' AS '';

-- =====================================================
-- PASSO 3: Verificação pós-correção
-- =====================================================
SELECT '3️⃣ Depois da correção...' AS etapa;

SELECT 
    COUNT(*) AS alunos_ativos_total
FROM alunos
WHERE ativo = 1;

SELECT 
    COUNT(*) AS alunos_em_tenant_usuario_papel
FROM alunos a
INNER JOIN tenant_usuario_papel tup ON tup.usuario_id = a.usuario_id
WHERE tup.tenant_id = @tenant_id 
AND tup.papel_id = @papel_aluno
AND a.ativo = 1;

SELECT 
    COUNT(*) AS alunos_ainda_faltando
FROM alunos a
WHERE a.ativo = 1
AND NOT EXISTS (
    SELECT 1 FROM tenant_usuario_papel tup
    WHERE tup.usuario_id = a.usuario_id
    AND tup.tenant_id = @tenant_id
    AND tup.papel_id = @papel_aluno
);

SELECT '' AS '';

-- =====================================================
-- PASSO 4: Testar query do endpoint
-- =====================================================
SELECT '4️⃣ Testando query do endpoint /admin/alunos...' AS etapa;

-- Simular a query que o endpoint usa
SELECT 
    a.id,
    a.usuario_id,
    a.nome,
    a.telefone,
    a.cpf,
    a.foto_caminho,
    a.ativo,
    u.email
FROM alunos a
INNER JOIN tenant_usuario_papel tup ON tup.usuario_id = a.usuario_id 
    AND tup.tenant_id = @tenant_id 
    AND tup.papel_id = @papel_aluno 
    AND tup.ativo = 1
LEFT JOIN usuarios u ON u.id = a.usuario_id
WHERE a.ativo = 1
ORDER BY a.nome ASC
LIMIT 10;

SELECT 
    COUNT(*) AS total_alunos_retornados
FROM alunos a
INNER JOIN tenant_usuario_papel tup ON tup.usuario_id = a.usuario_id 
    AND tup.tenant_id = @tenant_id 
    AND tup.papel_id = @papel_aluno 
    AND tup.ativo = 1
WHERE a.ativo = 1;

SELECT '' AS '';

-- =====================================================
-- PASSO 5: Verificar integridade
-- =====================================================
SELECT '5️⃣ Verificando integridade dos dados...' AS etapa;

-- Verificar se todos os vínculos têm usuário válido
SELECT 
    'Vínculos com usuário inexistente' AS verificacao,
    COUNT(*) AS problemas,
    CASE WHEN COUNT(*) = 0 THEN '✅ OK' ELSE '❌ PROBLEMA' END AS status
FROM tenant_usuario_papel tup
WHERE tup.tenant_id = @tenant_id 
AND tup.papel_id = @papel_aluno
AND NOT EXISTS (SELECT 1 FROM usuarios u WHERE u.id = tup.usuario_id);

-- Verificar se todos os vínculos têm aluno válido
SELECT 
    'Vínculos sem registro em alunos' AS verificacao,
    COUNT(*) AS problemas,
    CASE WHEN COUNT(*) = 0 THEN '✅ OK' ELSE '⚠️ Usuários que não são alunos' END AS status
FROM tenant_usuario_papel tup
WHERE tup.tenant_id = @tenant_id 
AND tup.papel_id = @papel_aluno
AND NOT EXISTS (SELECT 1 FROM alunos a WHERE a.usuario_id = tup.usuario_id);

-- Verificar duplicatas
SELECT 
    'Vínculos duplicados (mesmo usuario_id, tenant_id, papel_id)' AS verificacao,
    COUNT(*) - COUNT(DISTINCT CONCAT(usuario_id, '-', tenant_id, '-', papel_id)) AS problemas,
    CASE 
        WHEN COUNT(*) = COUNT(DISTINCT CONCAT(usuario_id, '-', tenant_id, '-', papel_id)) 
        THEN '✅ Sem duplicatas' 
        ELSE '❌ Existem duplicatas' 
    END AS status
FROM tenant_usuario_papel
WHERE tenant_id = @tenant_id AND papel_id = @papel_aluno;

SELECT '' AS '';

-- =====================================================
-- RESULTADO FINAL
-- =====================================================
SELECT '✅ CORREÇÃO CONCLUÍDA' AS resultado;
SELECT '' AS '';
SELECT '📝 Próximos passos:' AS proximos_passos;
SELECT '1. Testar endpoint: GET /admin/alunos' AS passo_1;
SELECT '2. Verificar se os alunos aparecem na lista' AS passo_2;
SELECT '3. Validar filtros e paginação' AS passo_3;
SELECT '' AS '';
SELECT '⚠️ Se problema persistir, verificar:' AS se_problema;
SELECT '- Logs do servidor PHP (erros 500)' AS check_1;
SELECT '- Middleware de autenticação (tenant_id correto)' AS check_2;
SELECT '- Cache do navegador/API' AS check_3;
