-- =====================================================
-- LIMPAR COLUNAS TEMPORÁRIAS DA MIGRAÇÃO
-- =====================================================
-- Criado em: 2026-02-03
-- 
-- Este script remove as colunas temporárias criadas durante
-- a migração de consolidação entre usuario_tenant e tenant_usuario_papel.
--
-- CONTEXTO:
-- As colunas *_temp foram criadas para preservar dados de plano_id,
-- status, data_inicio e data_fim durante a análise de migração.
-- 
-- DECISÃO ARQUITETURAL (Opção 1):
-- Manter AMBAS as tabelas com responsabilidades distintas:
-- - usuario_tenant: Vínculo usuário↔tenant + plano + status + datas
-- - tenant_usuario_papel: Papéis/permissões (aluno, professor, admin)
--
-- Como decidimos manter usuario_tenant, as colunas temporárias
-- em tenant_usuario_papel não são mais necessárias.
-- =====================================================

USE appcheckin;

-- Verificar se colunas temporárias existem antes de remover
SET @db_name = 'appcheckin';
SET @table_name = 'tenant_usuario_papel';

-- Remover plano_id_temp
SET @col_name = 'plano_id_temp';
SET @drop_sql = (
    SELECT IF(
        COUNT(*) > 0,
        CONCAT('ALTER TABLE ', @table_name, ' DROP COLUMN ', @col_name),
        'SELECT "Coluna plano_id_temp não existe" as info'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
    AND TABLE_NAME = @table_name
    AND COLUMN_NAME = @col_name
);
PREPARE stmt FROM @drop_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Remover status_temp
SET @col_name = 'status_temp';
SET @drop_sql = (
    SELECT IF(
        COUNT(*) > 0,
        CONCAT('ALTER TABLE ', @table_name, ' DROP COLUMN ', @col_name),
        'SELECT "Coluna status_temp não existe" as info'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
    AND TABLE_NAME = @table_name
    AND COLUMN_NAME = @col_name
);
PREPARE stmt FROM @drop_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Remover data_inicio_temp
SET @col_name = 'data_inicio_temp';
SET @drop_sql = (
    SELECT IF(
        COUNT(*) > 0,
        CONCAT('ALTER TABLE ', @table_name, ' DROP COLUMN ', @col_name),
        'SELECT "Coluna data_inicio_temp não existe" as info'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
    AND TABLE_NAME = @table_name
    AND COLUMN_NAME = @col_name
);
PREPARE stmt FROM @drop_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Remover data_fim_temp
SET @col_name = 'data_fim_temp';
SET @drop_sql = (
    SELECT IF(
        COUNT(*) > 0,
        CONCAT('ALTER TABLE ', @table_name, ' DROP COLUMN ', @col_name),
        'SELECT "Coluna data_fim_temp não existe" as info'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
    AND TABLE_NAME = @table_name
    AND COLUMN_NAME = @col_name
);
PREPARE stmt FROM @drop_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verificação final
SELECT 'Colunas temporárias removidas com sucesso!' as status;

-- Verificar estrutura final da tabela tenant_usuario_papel
SHOW COLUMNS FROM tenant_usuario_papel;
