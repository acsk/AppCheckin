-- ================================================================
-- Migration: Remover tenant_id de usuarios (usuário pode ter múltiplos tenants)
-- Data: 2026-01-06
-- Descrição: Remove a coluna tenant_id da tabela usuarios, pois a relação
--            usuário-tenant é many-to-many e está corretamente modelada na
--            tabela usuario_tenant. Isso elimina a inconsistência e permite
--            que um usuário pertença a múltiplos tenants.
-- ================================================================

-- 1. Migrar dados existentes para usuario_tenant (se ainda não estiverem)
INSERT INTO usuario_tenant (usuario_id, tenant_id, status, data_inicio)
SELECT 
    u.id,
    u.tenant_id,
    CASE WHEN u.ativo = 1 THEN 'ativo' ELSE 'inativo' END,
    COALESCE(u.created_at, NOW())
FROM usuarios u
WHERE u.tenant_id IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM usuario_tenant ut 
    WHERE ut.usuario_id = u.id AND ut.tenant_id = u.tenant_id
  );

-- 2. Remover foreign key de tenant_id
ALTER TABLE usuarios DROP FOREIGN KEY fk_usuarios_tenant;

-- 3. Remover índices relacionados ao tenant_id
ALTER TABLE usuarios DROP INDEX idx_tenant_usuarios;
ALTER TABLE usuarios DROP INDEX idx_tenant_email;

-- 4. Remover a coluna tenant_id
ALTER TABLE usuarios DROP COLUMN tenant_id;

-- 5. Atualizar email_global para todos os usuários que não têm
UPDATE usuarios 
SET email_global = email 
WHERE email_global IS NULL;

-- 6. Criar índice no email_global se ainda não existir
-- (já existe no schema, mas garantindo)
-- Verificar se índice existe antes de criar
SET @index_exists = (
    SELECT COUNT(1) 
    FROM information_schema.statistics 
    WHERE table_schema = DATABASE() 
    AND table_name = 'usuarios' 
    AND index_name = 'idx_email_global'
);

SET @sql = IF(@index_exists = 0, 
    'CREATE INDEX idx_email_global ON usuarios(email_global)', 
    'SELECT "Índice idx_email_global já existe" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 7. Adicionar comentário na tabela
ALTER TABLE usuarios COMMENT = 'Usuários globais do sistema. A relação com tenants é feita através da tabela usuario_tenant (many-to-many)';

-- ================================================================
-- NOTAS IMPORTANTES PARA O DESENVOLVIMENTO:
-- ================================================================
-- 1. O campo 'email' em usuarios agora é específico por tenant
--    (será atualizado pelo tenant onde o usuário está ativo)
-- 
-- 2. O campo 'email_global' é usado para:
--    - Autenticação inicial
--    - Identificação única do usuário no sistema
--    - Não pode ser duplicado
--
-- 3. Fluxo de autenticação:
--    a) Usuário faz login com email_global
--    b) Sistema busca tenants do usuário via usuario_tenant
--    c) Usuário seleciona o tenant
--    d) JWT é gerado com usuario_id + tenant_id
--
-- 4. Para buscar tenants de um usuário:
--    SELECT t.* FROM tenants t
--    INNER JOIN usuario_tenant ut ON ut.tenant_id = t.id
--    WHERE ut.usuario_id = ? AND ut.status = 'ativo'
--
-- 5. Para buscar usuários de um tenant:
--    SELECT u.* FROM usuarios u
--    INNER JOIN usuario_tenant ut ON ut.usuario_id = u.id
--    WHERE ut.tenant_id = ? AND ut.status = 'ativo'
-- ================================================================
