-- Migration: Refatorar tabela professores - remover tenant_id, adicionar usuario_id
-- Data: 2026-01-28
-- Descrição: Simplifica a tabela professores vinculando diretamente ao usuário global
--            O controle de "quem é professor em qual tenant" fica em tenant_usuario_papel

-- ==============================================
-- 1. ADICIONAR COLUNA usuario_id
-- ==============================================

-- Verificar se coluna já existe
SET @col_usuario_existe = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'professores' 
    AND COLUMN_NAME = 'usuario_id'
);

SET @sql_add_usuario = IF(@col_usuario_existe = 0,
    'ALTER TABLE professores ADD COLUMN usuario_id INT NULL AFTER id',
    'SELECT "Coluna usuario_id já existe"'
);

PREPARE stmt FROM @sql_add_usuario;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ==============================================
-- 2. MIGRAR DADOS: VINCULAR PROFESSOR AO USUÁRIO PELO EMAIL
-- ==============================================

-- Vincular professores existentes aos usuários pelo email
UPDATE professores p
INNER JOIN usuarios u ON LOWER(u.email) = LOWER(p.email)
SET p.usuario_id = u.id
WHERE p.usuario_id IS NULL;

-- ==============================================
-- 3. GARANTIR QUE TODOS PROFESSORES TEM PAPEL NA tenant_usuario_papel
-- ==============================================

-- Inserir papel de professor para usuários vinculados
INSERT IGNORE INTO tenant_usuario_papel (tenant_id, usuario_id, papel_id, ativo)
SELECT p.tenant_id, p.usuario_id, 2, 1 -- papel_id = 2 (professor)
FROM professores p
WHERE p.usuario_id IS NOT NULL
AND p.ativo = 1;

-- ==============================================
-- 4. CRIAR FK PARA USUARIO
-- ==============================================

-- Verificar se FK já existe
SET @fk_existe = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'professores' 
    AND CONSTRAINT_NAME = 'fk_professor_usuario'
);

SET @sql_add_fk = IF(@fk_existe = 0,
    'ALTER TABLE professores ADD CONSTRAINT fk_professor_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL',
    'SELECT "FK fk_professor_usuario já existe"'
);

PREPARE stmt FROM @sql_add_fk;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Criar índice para usuario_id
SET @idx_existe = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'professores' 
    AND INDEX_NAME = 'idx_professor_usuario'
);

SET @sql_add_idx = IF(@idx_existe = 0,
    'CREATE INDEX idx_professor_usuario ON professores(usuario_id)',
    'SELECT "Índice idx_professor_usuario já existe"'
);

PREPARE stmt FROM @sql_add_idx;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ==============================================
-- 5. REMOVER CAMPOS REDUNDANTES (COMENTADO POR SEGURANÇA)
-- ==============================================

-- Após validar que tudo funciona, descomentar para remover:

-- Remover tenant_id (agora controlado via tenant_usuario_papel)
-- ALTER TABLE professores DROP FOREIGN KEY fk_professor_tenant;
-- ALTER TABLE professores DROP COLUMN tenant_id;

-- Remover email (usar usuarios.email)
-- ALTER TABLE professores DROP COLUMN email;

-- Remover telefone (usar usuarios.telefone)
-- ALTER TABLE professores DROP COLUMN telefone;

-- Remover cpf (usar usuarios.cpf)
-- ALTER TABLE professores DROP COLUMN cpf;

-- ==============================================
-- 6. VERIFICAÇÃO
-- ==============================================

-- Verificar professores vinculados:
-- SELECT p.id, p.nome, p.usuario_id, u.email, p.tenant_id
-- FROM professores p
-- LEFT JOIN usuarios u ON u.id = p.usuario_id
-- ORDER BY p.id;

-- Verificar professores SEM vínculo (precisam criar usuário):
-- SELECT p.id, p.nome, p.email, p.tenant_id
-- FROM professores p
-- WHERE p.usuario_id IS NULL;

-- Verificar papéis de professor:
-- SELECT u.nome, u.email, t.nome as tenant
-- FROM tenant_usuario_papel tup
-- INNER JOIN usuarios u ON u.id = tup.usuario_id
-- INNER JOIN tenants t ON t.id = tup.tenant_id
-- WHERE tup.papel_id = 2 AND tup.ativo = 1;
