-- Migration: Consolidar tabelas roles e papeis
-- Data: 2026-01-28
-- Descrição: Remove a tabela 'roles' e usa apenas 'papeis' para controle de perfis
-- 
-- ANTES:
--   - roles: 1=aluno, 2=admin, 3=super_admin (antiga)
--   - papeis: 1=aluno, 2=professor, 3=admin (nova)
--   - usuarios.role_id -> roles.id
--
-- DEPOIS:
--   - papeis: 1=aluno, 2=professor, 3=admin (única tabela)
--   - usuarios.role_id -> papeis.id
--
-- MAPEAMENTO:
--   roles.id=1 (aluno) -> papeis.id=1 (aluno)
--   roles.id=2 (admin) -> papeis.id=3 (admin)
--   roles.id=3 (super_admin) -> papeis.id=3 (admin) - Super admin vira admin
-- =====================================================

-- 1. Verificar dados atuais
SELECT 'Estado atual - usuarios.role_id:' as info;
SELECT role_id, COUNT(*) as qtd FROM usuarios GROUP BY role_id;

SELECT 'Estado atual - tabela roles:' as info;
SELECT * FROM roles;

SELECT 'Estado atual - tabela papeis:' as info;
SELECT * FROM papeis;

-- 2. Adicionar papel super_admin se não existir (para manter distinção)
INSERT INTO papeis (id, nome, descricao, nivel, ativo)
SELECT 4, 'super_admin', 'Super administrador com acesso total ao sistema', 200, 1
WHERE NOT EXISTS (SELECT 1 FROM papeis WHERE id = 4);

-- 3. Remover FK de usuarios para roles (se existir)
SET @fk_exists = (
    SELECT COUNT(*) 
    FROM information_schema.TABLE_CONSTRAINTS 
    WHERE CONSTRAINT_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'usuarios' 
    AND CONSTRAINT_NAME = 'usuarios_ibfk_1'
);

-- Drop FK se existir
SET @sql = IF(@fk_exists > 0, 'ALTER TABLE usuarios DROP FOREIGN KEY usuarios_ibfk_1', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Tentar outros nomes de FK comuns
ALTER TABLE usuarios DROP FOREIGN KEY IF EXISTS fk_usuarios_role;

-- 4. Atualizar usuarios.role_id para apontar para papeis
-- Mapeamento:
--   role_id=1 (aluno) -> 1 (aluno em papeis)
--   role_id=2 (admin) -> 3 (admin em papeis)
--   role_id=3 (super_admin) -> 4 (super_admin em papeis)

UPDATE usuarios SET role_id = 1 WHERE role_id = 1; -- Aluno permanece 1
UPDATE usuarios SET role_id = 3 WHERE role_id = 2; -- Admin vai para 3
UPDATE usuarios SET role_id = 4 WHERE role_id = 3; -- Super admin vai para 4

-- 5. Criar nova FK de usuarios para papeis
-- Primeiro verificar se a coluna existe e ajustar se necessário
ALTER TABLE usuarios 
    ADD CONSTRAINT fk_usuarios_papel 
    FOREIGN KEY (role_id) REFERENCES papeis(id);

-- 6. Fazer backup e remover tabela roles
-- RENAME TABLE roles TO roles_backup;
DROP TABLE IF EXISTS roles;

-- 7. Verificar estado final
SELECT 'Estado final - usuarios.role_id:' as info;
SELECT role_id, COUNT(*) as qtd FROM usuarios GROUP BY role_id;

SELECT 'Estado final - tabela papeis:' as info;
SELECT * FROM papeis;

SELECT 'Verificação de integridade:' as info;
SELECT u.id, u.nome, u.email, u.role_id, p.nome as papel_nome
FROM usuarios u
LEFT JOIN papeis p ON p.id = u.role_id
WHERE p.id IS NULL;

-- Se tudo OK, a última query não deve retornar nenhum resultado

-- =====================================================
-- ROLLBACK (se necessário executar manualmente):
-- =====================================================
-- CREATE TABLE roles LIKE papeis;
-- INSERT INTO roles SELECT * FROM roles_backup;
-- ALTER TABLE usuarios DROP FOREIGN KEY fk_usuarios_papel;
-- ALTER TABLE usuarios ADD CONSTRAINT usuarios_ibfk_1 FOREIGN KEY (role_id) REFERENCES roles(id);
-- UPDATE usuarios SET role_id = 2 WHERE role_id = 3 AND role_id IN (SELECT id FROM papeis WHERE nome = 'admin');
-- UPDATE usuarios SET role_id = 3 WHERE role_id = 4;
