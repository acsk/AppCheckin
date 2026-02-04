-- =====================================================
-- VALIDAÇÃO PÓS-MIGRAÇÃO
-- ⚠️ IMPORTANTE: Execute SOMENTE APÓS o script de migração
-- Execute este script para verificar se tudo está correto
-- Data: 04/02/2026
-- =====================================================

SELECT '🔍 VALIDAÇÃO PÓS-MIGRAÇÃO' AS titulo;
SELECT '' AS '';

-- Verificar se a migração foi executada
SELECT 
    CASE 
        WHEN EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'usuario_tenant_backup')
        THEN '✅ BACKUP ENCONTRADO - Prosseguindo com validação'
        ELSE '❌❌❌ ERRO: A MIGRAÇÃO NÃO FOI EXECUTADA! Execute primeiro: 20260204_producao_migrar_usuario_tenant.sql ❌❌❌'
    END AS status_migracao;

SELECT '' AS '';

-- =====================================================
-- 1. VERIFICAR ESTRUTURA DAS TABELAS
-- =====================================================
SELECT '1️⃣ Verificando estrutura das tabelas...' AS etapa;

SELECT 
    'usuario_tenant_backup' AS tabela,
    CASE 
        WHEN COUNT(*) > 0 THEN '✅ Existe (backup criado)'
        ELSE '❌ NÃO EXISTE - PROBLEMA!'
    END AS status
FROM information_schema.tables
WHERE table_schema = DATABASE()
AND table_name = 'usuario_tenant_backup';

SELECT 
    'tenant_usuario_papel' AS tabela,
    CASE 
        WHEN COUNT(*) > 0 THEN '✅ Existe'
        ELSE '❌ NÃO EXISTE - PROBLEMA!'
    END AS status
FROM information_schema.tables
WHERE table_schema = DATABASE()
AND table_name = 'tenant_usuario_papel';

-- =====================================================
-- 2. COMPARAR CONTAGENS
-- =====================================================
SELECT '' AS '';
SELECT '2️⃣ Comparando contagens de registros...' AS etapa;

-- Contagem da tabela backup
SELECT COUNT(*) AS backup_total FROM usuario_tenant_backup;

-- Contagem de usuários únicos migrados
SELECT COUNT(DISTINCT usuario_id) AS migrados_usuarios_unicos FROM tenant_usuario_papel;

-- Contagem total de registros migrados
SELECT COUNT(*) AS migrados_total FROM tenant_usuario_papel;

-- Status da migração
SELECT 
    CASE 
        WHEN (SELECT COUNT(DISTINCT usuario_id) FROM tenant_usuario_papel) >= (SELECT COUNT(*) FROM usuario_tenant_backup)
        THEN '✅ OK - Todos usuários migrados'
        ELSE '⚠️ ATENÇÃO - Faltam usuários'
    END AS status_migracao;

-- =====================================================
-- 3. VERIFICAR DISTRIBUIÇÃO POR TENANT
-- =====================================================
SELECT '' AS '';
SELECT '3️⃣ Distribuição por tenant...' AS etapa;

SELECT 
    t.id AS tenant_id,
    t.nome AS tenant_nome,
    COALESCE((SELECT COUNT(*) FROM usuario_tenant_backup ut WHERE ut.tenant_id = t.id), 0) AS antes,
    COALESCE((SELECT COUNT(DISTINCT usuario_id) FROM tenant_usuario_papel tup WHERE tup.tenant_id = t.id), 0) AS depois,
    CASE 
        WHEN (SELECT COUNT(DISTINCT usuario_id) FROM tenant_usuario_papel tup WHERE tup.tenant_id = t.id) >= 
             COALESCE((SELECT COUNT(*) FROM usuario_tenant_backup ut WHERE ut.tenant_id = t.id), 0)
        THEN '✅ OK'
        ELSE '⚠️ Verificar'
    END AS status
FROM tenants t
ORDER BY t.id;

-- =====================================================
-- 4. VERIFICAR PAPÉIS ATRIBUÍDOS
-- =====================================================
SELECT '' AS '';
SELECT '4️⃣ Distribuição de papéis...' AS etapa;

SELECT 
    p.id,
    p.nome AS papel,
    COUNT(tup.id) AS total_usuarios,
    SUM(CASE WHEN tup.ativo = 1 THEN 1 ELSE 0 END) AS ativos,
    SUM(CASE WHEN tup.ativo = 0 THEN 1 ELSE 0 END) AS inativos
FROM papeis p
LEFT JOIN tenant_usuario_papel tup ON tup.papel_id = p.id
GROUP BY p.id, p.nome
ORDER BY p.nivel DESC;

-- =====================================================
-- 5. VERIFICAR CONVERSÃO DE STATUS
-- =====================================================
SELECT '' AS '';
SELECT '5️⃣ Verificando conversão de status...' AS etapa;

SELECT 
    'Status na tabela antiga' AS tipo,
    ut.status AS status_original,
    COUNT(*) AS quantidade
FROM usuario_tenant_backup ut
GROUP BY ut.status

UNION ALL

SELECT 
    'Status na tabela nova' AS tipo,
    CASE WHEN tup.ativo = 1 THEN 'ativo' ELSE 'inativo' END AS status_convertido,
    COUNT(*) AS quantidade
FROM tenant_usuario_papel tup
GROUP BY tup.ativo;

-- =====================================================
-- 6. VERIFICAR FUNÇÃO MySQL
-- =====================================================
SELECT '' AS '';
SELECT '6️⃣ Testando função get_tenant_id_from_usuario...' AS etapa;

SELECT 
    u.id AS usuario_id,
    u.nome,
    u.email,
    get_tenant_id_from_usuario(u.id) AS tenant_id_funcao,
    (SELECT tup.tenant_id FROM tenant_usuario_papel tup WHERE tup.usuario_id = u.id AND tup.ativo = 1 LIMIT 1) AS tenant_id_direto,
    CASE 
        WHEN get_tenant_id_from_usuario(u.id) = (SELECT tup.tenant_id FROM tenant_usuario_papel tup WHERE tup.usuario_id = u.id AND tup.ativo = 1 LIMIT 1)
        THEN '✅ OK'
        ELSE '❌ DIFERENTE'
    END AS status
FROM usuarios u
WHERE u.ativo = 1
LIMIT 10;

-- =====================================================
-- 7. VERIFICAR USUÁRIOS SEM TENANT
-- =====================================================
SELECT '' AS '';
SELECT '7️⃣ Verificando usuários órfãos (sem tenant)...' AS etapa;

SELECT 
    COUNT(*) AS total_orfaos,
    CASE 
        WHEN COUNT(*) = 0 THEN '✅ Nenhum usuário órfão'
        ELSE '⚠️ Existem usuários sem tenant'
    END AS status
FROM usuarios u
WHERE u.ativo = 1
AND NOT EXISTS (
    SELECT 1 FROM tenant_usuario_papel tup
    WHERE tup.usuario_id = u.id
    AND tup.ativo = 1
);

-- Listar usuários órfãos (se houver)
SELECT 
    u.id,
    u.nome,
    u.email,
    u.telefone,
    'SEM TENANT' AS problema
FROM usuarios u
WHERE u.ativo = 1
AND NOT EXISTS (
    SELECT 1 FROM tenant_usuario_papel tup
    WHERE tup.usuario_id = u.id
    AND tup.ativo = 1
)
LIMIT 10;

-- =====================================================
-- 8. VERIFICAR INTEGRIDADE REFERENCIAL
-- =====================================================
SELECT '' AS '';
SELECT '8️⃣ Verificando integridade referencial...' AS etapa;

-- Tenant_usuario_papel com tenant inexistente
SELECT 
    'Tenant inexistente' AS problema,
    COUNT(*) AS quantidade
FROM tenant_usuario_papel tup
WHERE NOT EXISTS (SELECT 1 FROM tenants t WHERE t.id = tup.tenant_id);

-- Tenant_usuario_papel com usuario inexistente
SELECT 
    'Usuario inexistente' AS problema,
    COUNT(*) AS quantidade
FROM tenant_usuario_papel tup
WHERE NOT EXISTS (SELECT 1 FROM usuarios u WHERE u.id = tup.usuario_id);

-- Tenant_usuario_papel com papel inexistente
SELECT 
    'Papel inexistente' AS problema,
    COUNT(*) AS quantidade
FROM tenant_usuario_papel tup
WHERE NOT EXISTS (SELECT 1 FROM papeis p WHERE p.id = tup.papel_id);

-- =====================================================
-- 9. VERIFICAR TRIGGERS ATUALIZADOS
-- =====================================================
SELECT '' AS '';
SELECT '9️⃣ Verificando triggers...' AS etapa;

SELECT 
    trigger_name,
    event_object_table AS tabela,
    CASE 
        WHEN INSTR(action_statement, 'tenant_usuario_papel') > 0 THEN '✅ Atualizado'
        WHEN INSTR(action_statement, 'usuario_tenant') > 0 THEN '⚠️ Usa tabela antiga'
        ELSE '✅ OK'
    END AS status
FROM information_schema.triggers
WHERE trigger_schema = DATABASE()
AND (
    INSTR(action_statement, 'tenant_usuario_papel') > 0 
    OR INSTR(action_statement, 'usuario_tenant') > 0
);

-- =====================================================
-- 10. RESUMO FINAL
-- =====================================================
SELECT '' AS '';
SELECT '📊 RESUMO FINAL DA VALIDAÇÃO' AS resultado;

-- Migração
SELECT '✅ Migração' AS item, COUNT(*) AS backup_original FROM usuario_tenant_backup;
SELECT '✅ Migrados' AS item, COUNT(DISTINCT usuario_id) AS usuarios_unicos FROM tenant_usuario_papel;

-- Papéis
SELECT '✅ Papéis' AS item, COUNT(DISTINCT papel_id) AS papeis_utilizados FROM tenant_usuario_papel;

-- Tenants
SELECT '✅ Tenants' AS item, COUNT(DISTINCT tenant_id) AS tenants_com_usuarios FROM tenant_usuario_papel;

-- Status Ativos
SELECT '✅ Status Ativos' AS item, COUNT(*) AS registros_ativos FROM tenant_usuario_papel WHERE ativo = 1;

-- Função
SELECT '✅ Função MySQL' AS item, 'get_tenant_id_from_usuario() atualizada' AS detalhes;

-- =====================================================
-- RESULTADO FINAL
-- =====================================================
SELECT '' AS '';

-- Contar usuários órfãos
SELECT COUNT(*) INTO @orfaos FROM usuarios 
WHERE ativo = 1 
AND NOT EXISTS (
    SELECT 1 FROM tenant_usuario_papel tup 
    WHERE tup.usuario_id = usuarios.id AND tup.ativo = 1
);

-- Exibir resultado
SELECT 
    CASE 
        WHEN @orfaos = 0
        THEN '✅✅✅ MIGRAÇÃO BEM-SUCEDIDA! ✅✅✅'
        ELSE CONCAT('⚠️ ATENÇÃO: ', @orfaos, ' usuários sem tenant')
    END AS resultado_final;

SELECT '' AS '';
SELECT '📝 Próximos passos:' AS proximos_passos;
SELECT '1. Testar API (login, perfil, check-in)' AS passo_1;
SELECT '2. Monitorar por 24-48h' AS passo_2;
SELECT '3. Após validação, executar: DROP TABLE usuario_tenant_backup;' AS passo_3;

-- =====================================================
-- FIM DA VALIDAÇÃO
-- =====================================================