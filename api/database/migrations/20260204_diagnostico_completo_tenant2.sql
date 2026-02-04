-- =====================================================
-- DIAGNÓSTICO COMPLETO: Tenant 2
-- Data: 04/02/2026
-- Objetivo: Identificar QUAIS alunos realmente pertencem ao tenant 2
-- =====================================================

SET @tenant_id = 2;

SELECT '🔍 DIAGNÓSTICO COMPLETO - TENANT 2' AS titulo;
SELECT '' AS '';

-- =====================================================
-- 1. INFORMAÇÕES DO TENANT
-- =====================================================
SELECT '1️⃣ Informações do Tenant' AS etapa;

SELECT id, nome, slug, ativo 
FROM tenants 
WHERE id = @tenant_id;

SELECT '' AS '';

-- =====================================================
-- 2. ALUNOS JÁ VINCULADOS AO TENANT 2
-- =====================================================
SELECT '2️⃣ Alunos JÁ vinculados ao tenant 2 via tenant_usuario_papel' AS etapa;

SELECT 
    a.id AS aluno_id,
    a.usuario_id,
    a.nome,
    a.cpf,
    tup.ativo AS vinculo_ativo,
    DATE(a.created_at) AS data_cadastro,
    tup.created_at AS data_vinculo
FROM alunos a
INNER JOIN tenant_usuario_papel tup ON tup.usuario_id = a.usuario_id
WHERE tup.tenant_id = @tenant_id 
AND tup.papel_id = 1
ORDER BY a.id;

SELECT COUNT(*) AS total_alunos_vinculados
FROM alunos a
INNER JOIN tenant_usuario_papel tup ON tup.usuario_id = a.usuario_id
WHERE tup.tenant_id = @tenant_id 
AND tup.papel_id = 1;

SELECT '' AS '';

-- =====================================================
-- 3. ALUNOS COM MATRÍCULA ATIVA NO TENANT 2 (FONTE DA VERDADE)
-- =====================================================
SELECT '3️⃣ Alunos com matrícula no tenant 2' AS etapa;

SELECT 
    a.id AS aluno_id,
    a.usuario_id,
    a.nome,
    a.cpf,
    p.nome AS plano_nome,
    m.data_inicio,
    m.data_vencimento,
    DATE(a.created_at) AS data_cadastro_aluno,
    CASE 
        WHEN tup.id IS NOT NULL THEN '✅ Tem vínculo'
        ELSE '❌ SEM VÍNCULO - PRECISA CORRIGIR'
    END AS status_vinculo
FROM alunos a
INNER JOIN matriculas m ON m.usuario_id = a.usuario_id AND m.tenant_id = @tenant_id
INNER JOIN planos p ON p.id = m.plano_id
LEFT JOIN tenant_usuario_papel tup ON tup.usuario_id = a.usuario_id 
    AND tup.tenant_id = @tenant_id 
    AND tup.papel_id = 1
WHERE a.ativo = 1
ORDER BY 
    CASE WHEN tup.id IS NULL THEN 0 ELSE 1 END,  -- Sem vínculo primeiro
    a.id;

SELECT COUNT(*) AS total_alunos_com_matricula
FROM alunos a
INNER JOIN matriculas m ON m.usuario_id = a.usuario_id AND m.tenant_id = @tenant_id
WHERE a.ativo = 1;

SELECT COUNT(*) AS alunos_com_matricula_SEM_vinculo
FROM alunos a
INNER JOIN matriculas m ON m.usuario_id = a.usuario_id AND m.tenant_id = @tenant_id
WHERE a.ativo = 1
AND NOT EXISTS (
    SELECT 1 FROM tenant_usuario_papel tup
    WHERE tup.usuario_id = a.usuario_id
    AND tup.tenant_id = @tenant_id
    AND tup.papel_id = 1
);

SELECT '' AS '';

-- =====================================================
-- 4. ALUNOS COM INSCRIÇÕES EM TURMAS DO TENANT 2
-- =====================================================
SELECT '4️⃣ Alunos inscritos em turmas do tenant 2' AS etapa;

SELECT DISTINCT
    a.id AS aluno_id,
    a.usuario_id,
    a.nome,
    a.cpf,
    t.id AS turma_id,
    t.nome AS turma_nome,
    it.status AS status_inscricao,
    DATE(a.created_at) AS data_cadastro_aluno
FROM alunos a
INNER JOIN inscricoes_turmas it ON it.usuario_id = a.usuario_id
INNER JOIN turmas t ON t.id = it.turma_id
WHERE t.tenant_id = @tenant_id
AND a.ativo = 1
ORDER BY a.id, t.id;

SELECT '' AS '';

-- =====================================================
-- 5. ALUNOS COM CHECK-INS NO TENANT 2
-- =====================================================
SELECT '5️⃣ Alunos com check-ins no tenant 2' AS etapa;

SELECT DISTINCT
    a.id AS aluno_id,
    a.usuario_id,
    a.nome,
    COUNT(c.id) AS total_checkins,
    MAX(c.data_checkin) AS ultimo_checkin
FROM alunos a
INNER JOIN checkins c ON c.aluno_id = a.id
WHERE c.tenant_id = @tenant_id
GROUP BY a.id, a.usuario_id, a.nome
ORDER BY a.id;

SELECT '' AS '';

-- =====================================================
-- 6. ALUNOS SEM TENANT (Cadastrados mas órfãos)
-- =====================================================
SELECT '6️⃣ Alunos SEM nenhum vínculo de tenant' AS etapa;

SELECT 
    a.id AS aluno_id,
    a.usuario_id,
    a.nome,
    a.cpf,
    DATE(a.created_at) AS data_cadastro,
    CASE 
        WHEN DATE(a.created_at) = '2026-02-04' THEN '✅ Cadastrado hoje'
        ELSE '📅 Cadastro antigo'
    END AS quando
FROM alunos a
WHERE a.ativo = 1
AND NOT EXISTS (
    SELECT 1 FROM tenant_usuario_papel tup
    WHERE tup.usuario_id = a.usuario_id
    AND tup.papel_id = 1
)
ORDER BY a.created_at DESC;

SELECT COUNT(*) AS total_alunos_sem_tenant
FROM alunos a
WHERE a.ativo = 1
AND NOT EXISTS (
    SELECT 1 FROM tenant_usuario_papel tup
    WHERE tup.usuario_id = a.usuario_id
    AND tup.papel_id = 1
);

SELECT '' AS '';

-- =====================================================
-- 7. RESUMO GERAL
-- =====================================================
SELECT '📊 RESUMO GERAL' AS etapa;

SELECT 
    (SELECT COUNT(*) FROM alunos WHERE ativo = 1) AS total_alunos_sistema,
    (SELECT COUNT(DISTINCT a.usuario_id)
     FROM alunos a
     INNER JOIN matriculas m ON m.usuario_id = a.usuario_id AND m.tenant_id = @tenant_id
     WHERE a.ativo = 1) AS alunos_com_matricula_tenant2,
    (SELECT COUNT(DISTINCT a.usuario_id) 
     FROM alunos a 
     INNER JOIN tenant_usuario_papel tup ON tup.usuario_id = a.usuario_id
     WHERE tup.tenant_id = @tenant_id AND tup.papel_id = 1) AS alunos_vinculados_tenant2,
    (SELECT COUNT(DISTINCT a.usuario_id)
     FROM alunos a
     INNER JOIN matriculas m ON m.usuario_id = a.usuario_id AND m.tenant_id = @tenant_id
     WHERE a.ativo = 1
     AND NOT EXISTS (
         SELECT 1 FROM tenant_usuario_papel tup
         WHERE tup.usuario_id = a.usuario_id 
         AND tup.tenant_id = @tenant_id 
         AND tup.papel_id = 1
     )) AS alunos_com_matricula_SEM_vinculo,
    (SELECT COUNT(*) FROM alunos a
     WHERE a.ativo = 1
     AND NOT EXISTS (
         SELECT 1 FROM tenant_usuario_papel tup
         WHERE tup.usuario_id = a.usuario_id AND tup.papel_id = 1
     )) AS alunos_sem_tenant_nenhum;

SELECT '' AS '';

-- =====================================================
-- 8. RECOMENDAÇÃO
-- =====================================================
SELECT '💡 RECOMENDAÇÃO' AS etapa;
SELECT 'A seção 3 mostra os alunos que TÊM MATRÍCULA mas NÃO TÊM VÍNCULO' AS analise;
SELECT '✅ Esses são os alunos que DEVEM ser corrigidos' AS passo_1;
SELECT '📝 Use o script 20260204_fix_tenant2_alunos.sql para corrigir' AS passo_2;
SELECT '⚠️ O script vai inserir APENAS alunos com matrícula ativa no tenant 2' AS passo_3;
