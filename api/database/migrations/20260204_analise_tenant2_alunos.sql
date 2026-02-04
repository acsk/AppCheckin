-- =====================================================
-- ANÁLISE TENANT 2: Identificar alunos faltantes
-- Data: 04/02/2026
-- Problema: Endpoint /admin/alunos não retorna dados
-- Causa: Alunos sem registro em tenant_usuario_papel
-- =====================================================

SET @tenant_id = 2;

SELECT '🔍 ANÁLISE TENANT 2 - ALUNOS' AS titulo;
SELECT '' AS '';

-- =====================================================
-- 1. CONTAGEM GERAL
-- =====================================================
SELECT '1️⃣ Contagens gerais...' AS etapa;

SELECT 'Total de alunos ativos no sistema' AS item, COUNT(*) AS quantidade
FROM alunos
WHERE ativo = 1;

SELECT 'Total de alunos com matrícula no tenant 2' AS item, COUNT(DISTINCT a.usuario_id) AS quantidade
FROM alunos a
INNER JOIN matriculas m ON m.usuario_id = a.usuario_id AND m.tenant_id = @tenant_id
WHERE a.ativo = 1;

SELECT 'Total de usuários com papel_id=1 (Aluno) vinculados ao tenant 2' AS item, COUNT(*) AS quantidade
FROM tenant_usuario_papel
WHERE tenant_id = @tenant_id AND papel_id = 1;

SELECT '' AS '';

-- =====================================================
-- 2. LISTAR ALUNOS QUE ESTÃO EM tenant_usuario_papel
-- =====================================================
SELECT '2️⃣ Alunos que ESTÃO no tenant_usuario_papel (tenant_id=2, papel_id=1)...' AS etapa;

SELECT 
    a.id AS aluno_id,
    a.usuario_id,
    a.nome,
    a.telefone,
    tup.ativo AS vinculo_ativo,
    tup.created_at AS data_vinculo
FROM alunos a
INNER JOIN tenant_usuario_papel tup ON tup.usuario_id = a.usuario_id
WHERE tup.tenant_id = @tenant_id 
AND tup.papel_id = 1
AND a.ativo = 1
ORDER BY a.id;

SELECT '' AS '';

-- =====================================================
-- 3. LISTAR ALUNOS QUE FALTAM (NÃO ESTÃO)
-- =====================================================
SELECT '3️⃣ ⚠️ Alunos com matrícula no tenant 2 mas SEM vínculo...' AS etapa;

SELECT 
    a.id AS aluno_id,
    a.usuario_id,
    a.nome,
    a.telefone,
    a.cpf,
    u.email,
    p.nome AS plano_nome,
    m.data_inicio,
    m.data_vencimento,
    '❌ FALTANDO VÍNCULO' AS status
FROM alunos a
INNER JOIN usuarios u ON u.id = a.usuario_id
INNER JOIN matriculas m ON m.usuario_id = a.usuario_id AND m.tenant_id = @tenant_id
LEFT JOIN planos p ON p.id = m.plano_id
WHERE a.ativo = 1
AND NOT EXISTS (
    SELECT 1 FROM tenant_usuario_papel tup
    WHERE tup.usuario_id = a.usuario_id
    AND tup.tenant_id = @tenant_id
    AND tup.papel_id = 1
)
ORDER BY a.id;

SELECT '' AS '';

-- =====================================================
-- 4. CONTAR QUANTOS FALTAM
-- =====================================================
SELECT '4️⃣ Resumo de alunos faltantes...' AS etapa;

SELECT 
    COUNT(*) AS total_faltando,
    CASE 
        WHEN COUNT(*) = 0 THEN '✅ Todos os alunos estão no tenant_usuario_papel'
        ELSE CONCAT('⚠️ AÇÃO NECESSÁRIA: ', COUNT(*), ' alunos precisam ser adicionados')
    END AS status
FROM alunos a
WHERE a.ativo = 1
AND NOT EXISTS (
    SELECT 1 FROM tenant_usuario_papel tup
    WHERE tup.usuario_id = a.usuario_id
    AND tup.tenant_id = @tenant_id
    AND tup.papel_id = 1
);

SELECT '' AS '';

-- =====================================================
-- 5. QUERY QUE O ENDPOINT USA (SIMULAÇÃO)
-- =====================================================
SELECT '5️⃣ Simulação da query do endpoint /admin/alunos...' AS etapa;

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
    AND tup.papel_id = 1 
    AND tup.ativo = 1
LEFT JOIN usuarios u ON u.id = a.usuario_id
WHERE a.ativo = 1
ORDER BY a.nome ASC
LIMIT 10;

SELECT 
    COUNT(*) AS total_retornados,
    CASE 
        WHEN COUNT(*) = 0 THEN '❌ POR ISSO O ENDPOINT RETORNA VAZIO'
        ELSE CONCAT('✅ Endpoint deveria retornar ', COUNT(*), ' alunos')
    END AS diagnostico
FROM alunos a
INNER JOIN tenant_usuario_papel tup ON tup.usuario_id = a.usuario_id 
    AND tup.tenant_id = @tenant_id 
    AND tup.papel_id = 1 
    AND tup.ativo = 1
WHERE a.ativo = 1;

SELECT '' AS '';

-- =====================================================
-- 6. IDENTIFICAR IDs DOS USUARIOS FALTANTES
-- =====================================================
SELECT '6️⃣ Lista de usuario_ids que precisam ser inseridos...' AS etapa;

SELECT GROUP_CONCAT(a.usuario_id ORDER BY a.id) AS usuario_ids_faltantes
FROM alunos a
WHERE a.ativo = 1
AND NOT EXISTS (
    SELECT 1 FROM tenant_usuario_papel tup
    WHERE tup.usuario_id = a.usuario_id
    AND tup.tenant_id = @tenant_id
    AND tup.papel_id = 1
);

-- =====================================================
-- CONCLUSÃO
-- =====================================================
SELECT '' AS '';
SELECT '📝 CONCLUSÃO' AS resultado;
SELECT 'Execute o script de correção: 20260204_fix_tenant2_alunos.sql' AS proxima_acao;
