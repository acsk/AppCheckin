-- =====================================================
-- REMOVER VÍNCULOS: Alunos cadastrados hoje do Tenant 2
-- Data: 04/02/2026
-- Motivo: Alunos cadastrados hoje não têm matrícula no tenant 2
-- =====================================================

SET @tenant_id = 2;
SET @data_hoje = '2026-02-04';

SELECT '🗑️ REMOÇÃO: Desvincular alunos cadastrados hoje do Tenant 2' AS titulo;
SELECT '' AS '';

-- =====================================================
-- PASSO 1: Mostrar o que será removido
-- =====================================================
SELECT '1️⃣ Alunos que serão desvinculados...' AS etapa;

SELECT 
    a.id AS aluno_id,
    a.usuario_id,
    a.nome,
    a.cpf,
    DATE(a.created_at) AS data_cadastro
FROM alunos a
INNER JOIN tenant_usuario_papel tup ON tup.usuario_id = a.usuario_id
WHERE tup.tenant_id = @tenant_id 
AND tup.papel_id = 1
AND DATE(a.created_at) = @data_hoje
ORDER BY a.id;

SELECT COUNT(*) AS total_a_remover
FROM alunos a
INNER JOIN tenant_usuario_papel tup ON tup.usuario_id = a.usuario_id
WHERE tup.tenant_id = @tenant_id 
AND tup.papel_id = 1
AND DATE(a.created_at) = @data_hoje;

SELECT '' AS '';

-- =====================================================
-- PASSO 2: REMOVER OS VÍNCULOS
-- =====================================================
SELECT '2️⃣ Removendo vínculos...' AS etapa;

DELETE tup
FROM tenant_usuario_papel tup
INNER JOIN alunos a ON a.usuario_id = tup.usuario_id
WHERE tup.tenant_id = @tenant_id 
AND tup.papel_id = 1
AND DATE(a.created_at) = @data_hoje;

SELECT ROW_COUNT() AS registros_removidos;

SELECT '' AS '';

-- =====================================================
-- PASSO 3: Verificar resultado
-- =====================================================
SELECT '3️⃣ Verificando resultado...' AS etapa;

-- Confirmar que não há mais alunos de hoje vinculados
SELECT 
    COUNT(*) AS alunos_de_hoje_ainda_vinculados
FROM alunos a
INNER JOIN tenant_usuario_papel tup ON tup.usuario_id = a.usuario_id
WHERE tup.tenant_id = @tenant_id 
AND tup.papel_id = 1
AND DATE(a.created_at) = @data_hoje;

-- Mostrar quantos alunos restaram vinculados ao tenant 2
SELECT 
    COUNT(*) AS total_alunos_vinculados_tenant2
FROM alunos a
INNER JOIN tenant_usuario_papel tup ON tup.usuario_id = a.usuario_id
WHERE tup.tenant_id = @tenant_id 
AND tup.papel_id = 1;

SELECT '' AS '';

-- =====================================================
-- PASSO 4: Testar endpoint
-- =====================================================
SELECT '4️⃣ Testando endpoint /admin/alunos...' AS etapa;

SELECT 
    COUNT(*) AS total_alunos_que_aparecerao
FROM alunos a
INNER JOIN tenant_usuario_papel tup ON tup.usuario_id = a.usuario_id 
    AND tup.tenant_id = @tenant_id 
    AND tup.papel_id = 1 
    AND tup.ativo = 1
WHERE a.ativo = 1;

SELECT 
    a.id,
    a.usuario_id,
    a.nome,
    a.cpf,
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

SELECT '' AS '';

-- =====================================================
-- RESULTADO FINAL
-- =====================================================
SELECT '✅ REMOÇÃO CONCLUÍDA' AS resultado;
SELECT '📝 Agora teste o endpoint GET /admin/alunos' AS proximo_passo;
