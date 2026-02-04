-- =====================================================
-- PRÉ-VERIFICAÇÃO - Execute ANTES da migração
-- Verifica o estado atual do banco de dados
-- =====================================================

SELECT '🔍 PRÉ-VERIFICAÇÃO DO BANCO DE DADOS' AS status;
SELECT '' AS '';

-- =====================================================
-- 1. VERIFICAR TABELAS EXISTENTES
-- =====================================================
SELECT '1️⃣ Verificando tabelas existentes...' AS etapa;

SELECT 
    table_name AS tabela,
    table_rows AS linhas_aprox,
    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS tamanho_mb
FROM information_schema.tables
WHERE table_schema = DATABASE()
AND table_name IN ('usuario_tenant', 'usuario_tenant_backup', 'tenant_usuario_papel')
ORDER BY table_name;

-- =====================================================
-- 2. ESTADO ATUAL - USUARIO_TENANT
-- =====================================================
SELECT '' AS '';
SELECT '2️⃣ Estado da tabela usuario_tenant...' AS etapa;

SELECT 
    CASE 
        WHEN EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'usuario_tenant')
        THEN '✅ Tabela usuario_tenant existe'
        ELSE '❌ Tabela usuario_tenant NÃO existe'
    END AS status;

-- Contar registros (se existir)
SELECT 
    'usuario_tenant' AS tabela,
    COUNT(*) AS total_registros,
    SUM(CASE WHEN status = 'ativo' THEN 1 ELSE 0 END) AS ativos,
    SUM(CASE WHEN status != 'ativo' THEN 1 ELSE 0 END) AS inativos
FROM usuario_tenant
WHERE EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'usuario_tenant');

-- =====================================================
-- 3. ESTADO ATUAL - TENANT_USUARIO_PAPEL
-- =====================================================
SELECT '' AS '';
SELECT '3️⃣ Estado da tabela tenant_usuario_papel...' AS etapa;

SELECT 
    'tenant_usuario_papel' AS tabela,
    COUNT(*) AS total_registros,
    COUNT(DISTINCT usuario_id) AS usuarios_unicos,
    SUM(CASE WHEN ativo = 1 THEN 1 ELSE 0 END) AS ativos,
    SUM(CASE WHEN ativo = 0 THEN 1 ELSE 0 END) AS inativos
FROM tenant_usuario_papel;

-- =====================================================
-- 4. VERIFICAR SE BACKUP JÁ EXISTE
-- =====================================================
SELECT '' AS '';
SELECT '4️⃣ Verificando se backup já existe...' AS etapa;

SELECT 
    CASE 
        WHEN EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'usuario_tenant_backup')
        THEN '⚠️ BACKUP JÁ EXISTE - Migração pode já ter sido executada!'
        ELSE '✅ Sem backup - Pronto para migrar'
    END AS status;

-- =====================================================
-- 5. VERIFICAR FUNÇÃO MYSQL ATUAL
-- =====================================================
SELECT '' AS '';
SELECT '5️⃣ Verificando função get_tenant_id_from_usuario...' AS etapa;

SELECT 
    routine_name AS funcao,
    CASE 
        WHEN routine_definition LIKE '%tenant_usuario_papel%' THEN '✅ Já usa tabela nova'
        WHEN routine_definition LIKE '%usuario_tenant%' THEN '⚠️ Usa tabela antiga (será atualizada)'
        ELSE '❓ Desconhecida'
    END AS status
FROM information_schema.routines
WHERE routine_schema = DATABASE()
AND routine_name = 'get_tenant_id_from_usuario';

-- =====================================================
-- 6. DISTRIBUIÇÃO ATUAL POR TENANT
-- =====================================================
SELECT '' AS '';
SELECT '6️⃣ Distribuição atual por tenant...' AS etapa;

SELECT 
    t.id,
    t.nome,
    COALESCE((
        SELECT COUNT(*) 
        FROM usuario_tenant ut 
        WHERE ut.tenant_id = t.id
        AND EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'usuario_tenant')
    ), 0) AS usuario_tenant_count,
    COALESCE((
        SELECT COUNT(DISTINCT usuario_id) 
        FROM tenant_usuario_papel tup 
        WHERE tup.tenant_id = t.id
    ), 0) AS tenant_usuario_papel_count
FROM tenants t
ORDER BY t.id;

-- =====================================================
-- 7. VERIFICAR USUÁRIOS ATIVOS
-- =====================================================
SELECT '' AS '';
SELECT '7️⃣ Verificando usuários ativos...' AS etapa;

SELECT 
    COUNT(*) AS total_usuarios_ativos,
    SUM(CASE WHEN EXISTS (
        SELECT 1 FROM tenant_usuario_papel tup 
        WHERE tup.usuario_id = u.id AND tup.ativo = 1
    ) THEN 1 ELSE 0 END) AS com_tenant_novo,
    SUM(CASE WHEN EXISTS (
        SELECT 1 FROM usuario_tenant ut 
        WHERE ut.usuario_id = u.id AND ut.status = 'ativo'
        AND EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'usuario_tenant')
    ) THEN 1 ELSE 0 END) AS com_tenant_antigo
FROM usuarios u
WHERE u.ativo = 1;

-- =====================================================
-- 8. DECISÃO: PODE MIGRAR?
-- =====================================================
SELECT '' AS '';
SELECT '📊 RESULTADO DA PRÉ-VERIFICAÇÃO' AS resultado;

SELECT 
    CASE 
        WHEN EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'usuario_tenant_backup')
        THEN '⚠️ MIGRAÇÃO JÁ EXECUTADA - Use script de validação'
        
        WHEN NOT EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'usuario_tenant')
        THEN '❌ TABELA usuario_tenant NÃO EXISTE - Verifique banco'
        
        WHEN (SELECT COUNT(*) FROM usuario_tenant) = 0
        THEN '⚠️ Tabela usuario_tenant VAZIA - Verifique se há dados'
        
        ELSE '✅ PRONTO PARA MIGRAÇÃO - Execute o script 20260204_producao_migrar_usuario_tenant.sql'
    END AS decisao;

-- =====================================================
SELECT '' AS '';
SELECT '📝 PRÓXIMO PASSO:' AS proximos_passos;
SELECT CASE 
    WHEN EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'usuario_tenant_backup')
    THEN 'Execute: 20260204_producao_validacao.sql'
    ELSE 'Execute: 20260204_producao_migrar_usuario_tenant.sql'
END AS proximo_script;
