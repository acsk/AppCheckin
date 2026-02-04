-- ========================================
-- MIGRATION: Alinhar Produção com Localhost
-- ========================================
-- Remove tenant_id de professores
-- Garante que tenant_professor gerencia TODOS os vínculos
-- ========================================

-- 1. Adicionar CPF e EMAIL à tabela professores (se não existir)
-- IMPORTANTE: Fazer ANTES da migração de dados

-- Verificar se CPF existe
SET @cpf_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'professores'
    AND COLUMN_NAME = 'cpf'
);

-- Adicionar CPF se não existir
SET @sql_cpf = IF(
    @cpf_exists = 0,
    'ALTER TABLE professores ADD COLUMN cpf VARCHAR(14) NULL AFTER nome, ADD UNIQUE KEY uk_professor_cpf (cpf);',
    'SELECT "CPF já existe" as msg'
);

PREPARE stmt_cpf FROM @sql_cpf;
EXECUTE stmt_cpf;
DEALLOCATE PREPARE stmt_cpf;

-- Verificar se EMAIL existe
SET @email_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'professores'
    AND COLUMN_NAME = 'email'
);

-- Adicionar EMAIL se não existir
SET @sql_email = IF(
    @email_exists = 0,
    'ALTER TABLE professores ADD COLUMN email VARCHAR(255) NULL AFTER telefone, ADD KEY idx_professor_email (email);',
    'SELECT "EMAIL já existe" as msg'
);

PREPARE stmt_email FROM @sql_email;
EXECUTE stmt_email;
DEALLOCATE PREPARE stmt_email;

SELECT 'Colunas CPF e EMAIL verificadas/adicionadas na tabela professores' as status;

-- 2. Criar tenant_professor (se não existir)
CREATE TABLE IF NOT EXISTS `tenant_professor` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `professor_id` INT NOT NULL COMMENT 'FK para professores.id',
    `tenant_id` INT NOT NULL COMMENT 'FK para tenants.id',
    `cpf` VARCHAR(14) NULL COMMENT 'CPF do professor (redundância para busca rápida)',
    `email` VARCHAR(255) NULL COMMENT 'Email do professor no tenant (permite email diferente por tenant)',
    `plano_id` INT NULL COMMENT 'FK para planos.id (plano específico do professor no tenant)',
    `status` ENUM('ativo', 'inativo', 'suspenso', 'cancelado') DEFAULT 'ativo' COMMENT 'Status do professor no tenant',
    `data_inicio` DATE NOT NULL DEFAULT (CURDATE()) COMMENT 'Data de início do vínculo',
    `data_fim` DATE NULL COMMENT 'Data de término do vínculo (NULL se ativo)',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_tenant_professor` (`tenant_id`, `professor_id`),
    UNIQUE KEY `unique_tenant_email` (`tenant_id`, `email`) COMMENT 'Email único por tenant',
    KEY `cpf` (`cpf`) COMMENT 'Índice para busca por CPF',
    KEY `idx_professores_tenant` (`tenant_id`),
    KEY `idx_professores_ativo` (`status`),
    KEY `idx_professores_usuario_id` (`professor_id`),
    
    CONSTRAINT `fk_tenant_professor_professor` 
        FOREIGN KEY (`professor_id`) REFERENCES `professores` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_tenant_professor_tenant` 
        FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_tenant_professor_plano` 
        FOREIGN KEY (`plano_id`) REFERENCES `planos` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Vínculo entre professores e tenants com status e plano específico';

-- 2.1. Adicionar CPF e EMAIL em tenant_professor (se não existirem)
SET @tp_cpf_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'tenant_professor'
    AND COLUMN_NAME = 'cpf'
);

SET @sql_tp_cpf = IF(
    @tp_cpf_exists = 0,
    'ALTER TABLE tenant_professor ADD COLUMN cpf VARCHAR(14) NULL AFTER tenant_id, ADD KEY cpf (cpf);',
    'SELECT "CPF já existe em tenant_professor" as msg'
);

PREPARE stmt_tp_cpf FROM @sql_tp_cpf;
EXECUTE stmt_tp_cpf;
DEALLOCATE PREPARE stmt_tp_cpf;

SET @tp_email_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'tenant_professor'
    AND COLUMN_NAME = 'email'
);

SET @sql_tp_email = IF(
    @tp_email_exists = 0,
    'ALTER TABLE tenant_professor ADD COLUMN email VARCHAR(255) NULL AFTER cpf;',
    'SELECT "EMAIL já existe em tenant_professor" as msg'
);

PREPARE stmt_tp_email FROM @sql_tp_email;
EXECUTE stmt_tp_email;
DEALLOCATE PREPARE stmt_tp_email;

-- 2.2. Adicionar índice unique_tenant_email se não existir
SET @tp_index_exists = (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'tenant_professor'
    AND INDEX_NAME = 'unique_tenant_email'
);

SET @sql_tp_index = IF(
    @tp_index_exists = 0,
    'ALTER TABLE tenant_professor ADD UNIQUE KEY unique_tenant_email (tenant_id, email);',
    'SELECT "Índice unique_tenant_email já existe" as msg'
);

PREPARE stmt_tp_index FROM @sql_tp_index;
EXECUTE stmt_tp_index;
DEALLOCATE PREPARE stmt_tp_index;

SELECT 'Tabela tenant_professor verificada/criada/atualizada' as status;

-- 3. Migrar dados existentes de professores.tenant_id para tenant_professor
INSERT INTO tenant_professor (professor_id, tenant_id, cpf, email, status, data_inicio)
SELECT 
    p.id,
    p.tenant_id,
    p.cpf,
    p.email,
    CASE WHEN p.ativo = 1 THEN 'ativo' ELSE 'inativo' END,
    COALESCE(p.created_at, CURDATE())
FROM professores p
WHERE p.tenant_id IS NOT NULL
AND NOT EXISTS (
    SELECT 1 
    FROM tenant_professor tp 
    WHERE tp.professor_id = p.id 
    AND tp.tenant_id = p.tenant_id
);

SELECT 'Migração de vínculos concluída' as status;
SELECT COUNT(*) as total_vinculos FROM tenant_professor;

-- 4. Remover o índice unique_tenant_email da tabela professores (se existir)
SET @index_exists = (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'professores'
    AND INDEX_NAME = 'unique_tenant_email'
);

SET @sql_drop_index = IF(
    @index_exists > 0,
    'ALTER TABLE professores DROP INDEX unique_tenant_email;',
    'SELECT "Índice unique_tenant_email não existe" as status'
);

PREPARE stmt_drop_index FROM @sql_drop_index;
EXECUTE stmt_drop_index;
DEALLOCATE PREPARE stmt_drop_index;

-- 5. Remover foreign key de professores.tenant_id (se existir)
SET @fk_exists = (
    SELECT COUNT(*)
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'professores'
    AND COLUMN_NAME = 'tenant_id'
    AND REFERENCED_TABLE_NAME IS NOT NULL
);

SET @fk_name = (
    SELECT CONSTRAINT_NAME
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'professores'
    AND COLUMN_NAME = 'tenant_id'
    AND REFERENCED_TABLE_NAME IS NOT NULL
    LIMIT 1
);

SET @sql_drop_fk = IF(
    @fk_exists > 0,
    CONCAT('ALTER TABLE professores DROP FOREIGN KEY ', @fk_name, ';'),
    'SELECT "Foreign key não existe" as msg'
);

PREPARE stmt_drop_fk FROM @sql_drop_fk;
EXECUTE stmt_drop_fk;
DEALLOCATE PREPARE stmt_drop_fk;

-- 6. Remover o índice idx_professores_tenant (se existir)
SET @idx_tenant_exists = (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'professores'
    AND INDEX_NAME = 'idx_professores_tenant'
);

SET @sql_drop_idx_tenant = IF(
    @idx_tenant_exists > 0,
    'ALTER TABLE professores DROP INDEX idx_professores_tenant;',
    'SELECT "Índice idx_professores_tenant não existe" as msg'
);

PREPARE stmt_drop_idx_tenant FROM @sql_drop_idx_tenant;
EXECUTE stmt_drop_idx_tenant;
DEALLOCATE PREPARE stmt_drop_idx_tenant;

-- 7. Remover a coluna tenant_id de professores
-- 7. Remover a coluna tenant_id de professores
SET @tenant_id_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'professores'
    AND COLUMN_NAME = 'tenant_id'
);

SET @sql_drop_tenant_id = IF(
    @tenant_id_exists > 0,
    'ALTER TABLE professores DROP COLUMN tenant_id;',
    'SELECT "Coluna tenant_id já foi removida" as msg'
);

PREPARE stmt_drop_tenant_id FROM @sql_drop_tenant_id;
EXECUTE stmt_drop_tenant_id;
DEALLOCATE PREPARE stmt_drop_tenant_id;

-- 8. Verificação final
SELECT 'ALINHAMENTO CONCLUÍDO!' as status;

SELECT 
    'Estrutura da tabela professores' as info,
    COLUMN_NAME,
    COLUMN_TYPE,
    IS_NULLABLE,
    COLUMN_KEY
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME = 'professores'
ORDER BY ORDINAL_POSITION;

SELECT 
    'Índices da tabela professores' as info,
    INDEX_NAME,
    COLUMN_NAME,
    NON_UNIQUE
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME = 'professores'
ORDER BY INDEX_NAME, SEQ_IN_INDEX;

SELECT 
    'Total de vínculos em tenant_professor' as info,
    COUNT(*) as total_vinculos
FROM tenant_professor;
