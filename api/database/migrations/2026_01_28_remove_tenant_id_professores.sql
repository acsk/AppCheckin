-- Migration: Remover tenant_id de professores (tornar global)
-- Data: 2026-01-28
-- Descrição: Remove tenant_id de professores pois o controle de permissão
--            agora é feito 100% via tenant_usuario_papel

-- ==============================================
-- 1. REMOVER FK E COLUNA tenant_id
-- ==============================================

-- Remover FK se existir
SET @fk_existe = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'professores' 
    AND CONSTRAINT_NAME = 'professores_ibfk_1'
);

SET @sql_drop_fk = IF(@fk_existe > 0,
    'ALTER TABLE professores DROP FOREIGN KEY professores_ibfk_1',
    'SELECT "FK professores_ibfk_1 não existe"'
);

PREPARE stmt FROM @sql_drop_fk;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Tentar remover outra possível FK
SET @fk_existe2 = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'professores' 
    AND CONSTRAINT_NAME = 'fk_professor_tenant'
);

SET @sql_drop_fk2 = IF(@fk_existe2 > 0,
    'ALTER TABLE professores DROP FOREIGN KEY fk_professor_tenant',
    'SELECT "FK fk_professor_tenant não existe"'
);

PREPARE stmt FROM @sql_drop_fk2;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Remover índice de tenant_id se existir
SET @idx_existe = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'professores' 
    AND INDEX_NAME = 'tenant_id'
);

SET @sql_drop_idx = IF(@idx_existe > 0,
    'ALTER TABLE professores DROP INDEX tenant_id',
    'SELECT "Índice tenant_id não existe"'
);

PREPARE stmt FROM @sql_drop_idx;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Remover coluna tenant_id
SET @col_existe = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'professores' 
    AND COLUMN_NAME = 'tenant_id'
);

SET @sql_drop_col = IF(@col_existe > 0,
    'ALTER TABLE professores DROP COLUMN tenant_id',
    'SELECT "Coluna tenant_id não existe"'
);

PREPARE stmt FROM @sql_drop_col;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ==============================================
-- 2. REMOVER CAMPOS REDUNDANTES (email, telefone, cpf)
-- ==============================================

-- Remover email (usar usuarios.email)
SET @col_email = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'professores' 
    AND COLUMN_NAME = 'email'
);

SET @sql_drop_email = IF(@col_email > 0,
    'ALTER TABLE professores DROP COLUMN email',
    'SELECT "Coluna email não existe"'
);

PREPARE stmt FROM @sql_drop_email;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Remover telefone (usar usuarios.telefone)
SET @col_tel = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'professores' 
    AND COLUMN_NAME = 'telefone'
);

SET @sql_drop_tel = IF(@col_tel > 0,
    'ALTER TABLE professores DROP COLUMN telefone',
    'SELECT "Coluna telefone não existe"'
);

PREPARE stmt FROM @sql_drop_tel;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Remover cpf (usar usuarios.cpf) - precisa remover índice único primeiro
SET @idx_cpf = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'professores' 
    AND INDEX_NAME = 'cpf'
);

SET @sql_drop_idx_cpf = IF(@idx_cpf > 0,
    'ALTER TABLE professores DROP INDEX cpf',
    'SELECT "Índice cpf não existe"'
);

PREPARE stmt FROM @sql_drop_idx_cpf;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_cpf = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'professores' 
    AND COLUMN_NAME = 'cpf'
);

SET @sql_drop_cpf = IF(@col_cpf > 0,
    'ALTER TABLE professores DROP COLUMN cpf',
    'SELECT "Coluna cpf não existe"'
);

PREPARE stmt FROM @sql_drop_cpf;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ==============================================
-- 3. TORNAR usuario_id NOT NULL E UNIQUE
-- ==============================================

-- Primeiro atualizar registros sem usuario_id (se houver)
-- DELETE FROM professores WHERE usuario_id IS NULL;

-- Modificar para NOT NULL (somente se tiver dados válidos)
-- ALTER TABLE professores MODIFY COLUMN usuario_id INT NOT NULL;

-- Criar índice único para usuario_id
SET @idx_usuario_unique = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'professores' 
    AND INDEX_NAME = 'uk_professor_usuario'
);

SET @sql_add_unique = IF(@idx_usuario_unique = 0,
    'ALTER TABLE professores ADD UNIQUE KEY uk_professor_usuario (usuario_id)',
    'SELECT "Índice uk_professor_usuario já existe"'
);

-- Não executar ainda pois pode ter professores sem usuario_id
-- PREPARE stmt FROM @sql_add_unique;
-- EXECUTE stmt;
-- DEALLOCATE PREPARE stmt;

-- ==============================================
-- 4. VERIFICAÇÃO
-- ==============================================

-- Verificar estrutura final:
-- DESCRIBE professores;

-- Verificar professores:
-- SELECT p.id, p.nome, p.usuario_id, u.email, u.telefone
-- FROM professores p
-- LEFT JOIN usuarios u ON u.id = p.usuario_id;
