-- ============================================================================
-- MIGRAÇÃO: Consolidação de usuario_tenant → tenant_usuario_papel
-- Data: 2026-02-03
-- Objetivo: Eliminar redundância e consolidar papéis de usuários
-- ============================================================================

-- PASSO 0: Criar backup
-- ============================================================================
CREATE TABLE IF NOT EXISTS usuario_tenant_backup AS SELECT * FROM usuario_tenant;
CREATE TABLE IF NOT EXISTS tenant_usuario_papel_backup AS SELECT * FROM tenant_usuario_papel;

SELECT 'Backup criado com sucesso!' AS status;

-- ============================================================================
-- PASSO 1: ANÁLISE DOS DADOS
-- ============================================================================

-- Verificar registros em ambas as tabelas
SELECT 
    'usuario_tenant' as tabela,
    COUNT(*) as total_registros,
    COUNT(DISTINCT usuario_id) as usuarios_unicos,
    COUNT(DISTINCT tenant_id) as tenants_unicos
FROM usuario_tenant
UNION ALL
SELECT 
    'tenant_usuario_papel' as tabela,
    COUNT(*) as total_registros,
    COUNT(DISTINCT usuario_id) as usuarios_unicos,
    COUNT(DISTINCT tenant_id) as tenants_unicos
FROM tenant_usuario_papel;

-- Verificar registros que existem apenas em usuario_tenant
SELECT 
    'Registros APENAS em usuario_tenant (serão migrados)' as analise,
    COUNT(*) as quantidade
FROM usuario_tenant ut
LEFT JOIN tenant_usuario_papel tup 
    ON ut.usuario_id = tup.usuario_id 
    AND ut.tenant_id = tup.tenant_id 
    AND ut.papel_id = tup.papel_id
WHERE tup.id IS NULL;

-- Verificar se há usuários com múltiplos papéis em usuario_tenant
SELECT 
    'Usuários com múltiplos papéis em usuario_tenant' as analise,
    COUNT(*) as quantidade
FROM (
    SELECT usuario_id, tenant_id, COUNT(*) as papeis
    FROM usuario_tenant
    GROUP BY usuario_id, tenant_id
    HAVING COUNT(*) > 1
) multi_papeis;

-- ============================================================================
-- PASSO 2: PREPARAÇÃO - Adicionar campos temporários
-- ============================================================================

-- Verificar se colunas já existem e adicionar apenas se necessário
SET @exist_plano := (
    SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = 'appcheckin' 
    AND TABLE_NAME = 'tenant_usuario_papel' 
    AND COLUMN_NAME = 'plano_id_temp'
);

SET @exist_status := (
    SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = 'appcheckin' 
    AND TABLE_NAME = 'tenant_usuario_papel' 
    AND COLUMN_NAME = 'status_temp'
);

SET @exist_data_inicio := (
    SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = 'appcheckin' 
    AND TABLE_NAME = 'tenant_usuario_papel' 
    AND COLUMN_NAME = 'data_inicio_temp'
);

SET @exist_data_fim := (
    SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = 'appcheckin' 
    AND TABLE_NAME = 'tenant_usuario_papel' 
    AND COLUMN_NAME = 'data_fim_temp'
);

-- Adicionar colunas apenas se não existirem
SET @sql_plano = IF(@exist_plano = 0, 
    'ALTER TABLE tenant_usuario_papel ADD COLUMN plano_id_temp INT NULL COMMENT "Temporário - migrado de usuario_tenant"',
    'SELECT "Coluna plano_id_temp já existe" AS status'
);
PREPARE stmt FROM @sql_plano;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql_status = IF(@exist_status = 0,
    'ALTER TABLE tenant_usuario_papel ADD COLUMN status_temp VARCHAR(20) NULL COMMENT "Temporário - migrado de usuario_tenant"',
    'SELECT "Coluna status_temp já existe" AS status'
);
PREPARE stmt FROM @sql_status;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql_data_inicio = IF(@exist_data_inicio = 0,
    'ALTER TABLE tenant_usuario_papel ADD COLUMN data_inicio_temp DATE NULL COMMENT "Temporário - migrado de usuario_tenant"',
    'SELECT "Coluna data_inicio_temp já existe" AS status'
);
PREPARE stmt FROM @sql_data_inicio;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql_data_fim = IF(@exist_data_fim = 0,
    'ALTER TABLE tenant_usuario_papel ADD COLUMN data_fim_temp DATE NULL COMMENT "Temporário - migrado de usuario_tenant"',
    'SELECT "Coluna data_fim_temp já existe" AS status'
);
PREPARE stmt FROM @sql_data_fim;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SELECT 'Campos temporários verificados/adicionados!' AS status;

-- ============================================================================
-- PASSO 3: MIGRAÇÃO DE DADOS
-- ============================================================================

-- Migrar registros que NÃO existem em tenant_usuario_papel
INSERT INTO tenant_usuario_papel (
    tenant_id, 
    usuario_id, 
    papel_id, 
    ativo,
    plano_id_temp,
    status_temp,
    data_inicio_temp,
    data_fim_temp,
    created_at,
    updated_at
)
SELECT 
    ut.tenant_id,
    ut.usuario_id,
    ut.papel_id,
    CASE ut.status
        WHEN 'ativo' THEN 1
        WHEN 'inativo' THEN 0
        WHEN 'suspenso' THEN 0
        WHEN 'cancelado' THEN 0
        ELSE 1
    END as ativo,
    ut.plano_id,
    ut.status,
    ut.data_inicio,
    ut.data_fim,
    ut.created_at,
    ut.updated_at
FROM usuario_tenant ut
LEFT JOIN tenant_usuario_papel tup 
    ON ut.usuario_id = tup.usuario_id 
    AND ut.tenant_id = tup.tenant_id 
    AND ut.papel_id = tup.papel_id
WHERE tup.id IS NULL;

SELECT 
    'Registros migrados!' AS status,
    ROW_COUNT() as registros_inseridos;

-- Atualizar registros existentes com informações de plano
UPDATE tenant_usuario_papel tup
INNER JOIN usuario_tenant ut 
    ON tup.usuario_id = ut.usuario_id 
    AND tup.tenant_id = ut.tenant_id 
    AND tup.papel_id = ut.papel_id
SET 
    tup.plano_id_temp = ut.plano_id,
    tup.status_temp = ut.status,
    tup.data_inicio_temp = ut.data_inicio,
    tup.data_fim_temp = ut.data_fim
WHERE ut.plano_id IS NOT NULL OR ut.status IS NOT NULL;

SELECT 'Registros existentes atualizados!' AS status;

-- ============================================================================
-- PASSO 4: VERIFICAÇÃO PÓS-MIGRAÇÃO
-- ============================================================================

-- Verificar integridade da migração
SELECT 
    'Verificação de integridade' as tipo,
    ut.total as registros_origem,
    tup.total as registros_destino,
    CASE 
        WHEN ut.total = tup.total THEN '✓ OK - Todos os registros migrados'
        WHEN ut.total < tup.total THEN '✓ OK - Destino tem registros adicionais (múltiplos papéis)'
        ELSE '✗ ERRO - Faltam registros no destino!'
    END as status
FROM 
    (SELECT COUNT(*) as total FROM usuario_tenant) ut,
    (SELECT COUNT(*) as total FROM tenant_usuario_papel) tup;

-- Verificar se há registros órfãos
SELECT 
    'Registros que precisam de atenção' as alerta,
    COUNT(*) as quantidade
FROM usuario_tenant ut
LEFT JOIN tenant_usuario_papel tup 
    ON ut.usuario_id = tup.usuario_id 
    AND ut.tenant_id = tup.tenant_id
WHERE tup.id IS NULL;

-- ============================================================================
-- PASSO 5: REMOVER FOREIGN KEYS que referenciam usuario_tenant
-- ============================================================================

-- Nenhuma FK encontrada referenciando usuario_tenant no schema fornecido
-- Se existir alguma, adicione aqui:
-- ALTER TABLE tabela_exemplo DROP FOREIGN KEY fk_exemplo;

SELECT 'Foreign keys verificadas - nenhuma encontrada!' AS status;

-- ============================================================================
-- PASSO 6: DROPAR TABELA usuario_tenant
-- ============================================================================

-- ATENÇÃO: Execute este passo apenas após verificar que tudo está OK!
-- Descomente as linhas abaixo quando estiver pronto:

-- DROP TABLE IF EXISTS usuario_tenant;
-- SELECT 'Tabela usuario_tenant removida com sucesso!' AS status;

SELECT '⚠️  ATENÇÃO: Tabela usuario_tenant NÃO foi removida ainda!' AS status;
SELECT '⚠️  Verifique os dados em tenant_usuario_papel antes de continuar!' AS status;
SELECT '⚠️  Quando estiver seguro, execute manualmente: DROP TABLE usuario_tenant;' AS status;

-- ============================================================================
-- PASSO 7: ANÁLISE DOS CAMPOS TEMPORÁRIOS
-- ============================================================================

-- Ver quais registros têm informações de plano
SELECT 
    'Registros com plano_id' as tipo,
    COUNT(*) as quantidade,
    COUNT(DISTINCT plano_id_temp) as planos_unicos
FROM tenant_usuario_papel
WHERE plano_id_temp IS NOT NULL;

-- Ver distribuição de status
SELECT 
    status_temp as status,
    COUNT(*) as quantidade
FROM tenant_usuario_papel
WHERE status_temp IS NOT NULL
GROUP BY status_temp
ORDER BY quantidade DESC;

-- ============================================================================
-- PRÓXIMOS PASSOS RECOMENDADOS
-- ============================================================================

SELECT '
📋 PRÓXIMOS PASSOS RECOMENDADOS:

1. ✅ MIGRAÇÃO CONCLUÍDA - Verifique os resultados acima

2. 📊 ANÁLISE DE DADOS:
   - Verifique se todos os registros foram migrados corretamente
   - Analise os campos temporários (plano_id_temp, status_temp, etc)

3. 🔄 DECISÃO SOBRE PLANO_ID:
   Opção A: Mover plano_id para tabela alunos
   Opção B: Mover plano_id para tabela matriculas
   Opção C: Criar tabela usuario_plano separada

4. 🗑️ LIMPEZA (após confirmar que está tudo OK):
   DROP TABLE usuario_tenant;
   
5. 🔧 ATUALIZAR CÓDIGO PHP:
   - Remover referências a usuario_tenant
   - Atualizar queries para usar tenant_usuario_papel

6. 📝 DOCUMENTAÇÃO:
   - Atualizar documentação do banco
   - Atualizar ERD (diagrama)

' as informacoes;

-- ============================================================================
-- ROLLBACK (caso necessário)
-- ============================================================================

/*
-- Para reverter a migração:

-- 1. Restaurar usuario_tenant
DROP TABLE IF EXISTS usuario_tenant;
CREATE TABLE usuario_tenant LIKE usuario_tenant_backup;
INSERT INTO usuario_tenant SELECT * FROM usuario_tenant_backup;

-- 2. Restaurar tenant_usuario_papel
TRUNCATE TABLE tenant_usuario_papel;
INSERT INTO tenant_usuario_papel 
SELECT id, tenant_id, usuario_id, papel_id, ativo, created_at, updated_at 
FROM tenant_usuario_papel_backup;

-- 3. Remover campos temporários
ALTER TABLE tenant_usuario_papel 
DROP COLUMN IF EXISTS plano_id_temp,
DROP COLUMN IF EXISTS status_temp,
DROP COLUMN IF EXISTS data_inicio_temp,
DROP COLUMN IF EXISTS data_fim_temp;

SELECT 'Rollback concluído!' AS status;
*/
