-- =============================================
-- Migration: Renomear usuario_tenant para usuario_tenant_backup
-- Data: 2026-02-04
-- Descrição: 
--   Esta migration renomeia a tabela usuario_tenant para usuario_tenant_backup
--   ao invés de excluí-la, consolidando toda a lógica de vínculo usuário-tenant
--   na tabela tenant_usuario_papel que é mais eficiente e suporta múltiplos papéis.
-- 
-- ATENÇÃO: Execute esta migration após confirmar que todo o código foi refatorado
--          para usar tenant_usuario_papel ao invés de usuario_tenant.
-- =============================================

USE `appcheckin`;

-- 1. Verificar se a tabela usuario_tenant existe
SELECT 'Verificando existência da tabela usuario_tenant...' as status;

-- 2. Remover foreign keys da tabela usuario_tenant antes de renomear
SELECT 'Removendo foreign keys da tabela usuario_tenant...' as status;

-- Verificar e remover foreign keys existentes
SET @exist_fk1 = (SELECT COUNT(*) FROM information_schema.table_constraints 
                  WHERE constraint_schema = 'appcheckin' 
                  AND table_name = 'usuario_tenant' 
                  AND constraint_name = 'fk_usuario_tenant_tenant');
SET @sql1 = IF(@exist_fk1 > 0, 'ALTER TABLE usuario_tenant DROP FOREIGN KEY fk_usuario_tenant_tenant', 'SELECT "FK fk_usuario_tenant_tenant não existe"');
PREPARE stmt1 FROM @sql1;
EXECUTE stmt1;
DEALLOCATE PREPARE stmt1;

SET @exist_fk2 = (SELECT COUNT(*) FROM information_schema.table_constraints 
                  WHERE constraint_schema = 'appcheckin' 
                  AND table_name = 'usuario_tenant' 
                  AND constraint_name = 'fk_usuario_tenant_usuario');
SET @sql2 = IF(@exist_fk2 > 0, 'ALTER TABLE usuario_tenant DROP FOREIGN KEY fk_usuario_tenant_usuario', 'SELECT "FK fk_usuario_tenant_usuario não existe"');
PREPARE stmt2 FROM @sql2;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;

SET @exist_fk3 = (SELECT COUNT(*) FROM information_schema.table_constraints 
                  WHERE constraint_schema = 'appcheckin' 
                  AND table_name = 'usuario_tenant' 
                  AND constraint_name = 'fk_usuario_tenant_plano');
SET @sql3 = IF(@exist_fk3 > 0, 'ALTER TABLE usuario_tenant DROP FOREIGN KEY fk_usuario_tenant_plano', 'SELECT "FK fk_usuario_tenant_plano não existe"');
PREPARE stmt3 FROM @sql3;
EXECUTE stmt3;
DEALLOCATE PREPARE stmt3;

-- 3. Renomear a tabela usuario_tenant para usuario_tenant_backup
SELECT 'Renomeando tabela usuario_tenant para usuario_tenant_backup...' as status;

-- Primeiro, verificar se já existe uma tabela backup antiga e removê-la
DROP TABLE IF EXISTS `usuario_tenant_backup`;

-- Agora renomear
RENAME TABLE `usuario_tenant` TO `usuario_tenant_backup`;

-- 4. Verificar se a tabela foi renomeada com sucesso
SELECT 'Verificando se a tabela foi renomeada...' as status;

SHOW TABLES LIKE 'usuario_tenant_backup';

-- 5. Criar índices na tabela tenant_usuario_papel para garantir performance
SELECT 'Criando índices adicionais na tabela tenant_usuario_papel...' as status;

-- Índice para busca por usuário e tenant (se não existir)
SET @exist_idx1 = (SELECT COUNT(*) FROM information_schema.statistics 
                   WHERE table_schema = 'appcheckin' 
                   AND table_name = 'tenant_usuario_papel' 
                   AND index_name = 'idx_tenant_usuario_papel_usuario_tenant');
SET @sql_idx1 = IF(@exist_idx1 = 0, 
    'CREATE INDEX idx_tenant_usuario_papel_usuario_tenant ON tenant_usuario_papel(usuario_id, tenant_id)', 
    'SELECT "Índice idx_tenant_usuario_papel_usuario_tenant já existe"');
PREPARE stmt_idx1 FROM @sql_idx1;
EXECUTE stmt_idx1;
DEALLOCATE PREPARE stmt_idx1;

-- Índice para busca por tenant e papel (útil para listar usuários por papel)
SET @exist_idx2 = (SELECT COUNT(*) FROM information_schema.statistics 
                   WHERE table_schema = 'appcheckin' 
                   AND table_name = 'tenant_usuario_papel' 
                   AND index_name = 'idx_tenant_usuario_papel_tenant_papel');
SET @sql_idx2 = IF(@exist_idx2 = 0, 
    'CREATE INDEX idx_tenant_usuario_papel_tenant_papel ON tenant_usuario_papel(tenant_id, papel_id)', 
    'SELECT "Índice idx_tenant_usuario_papel_tenant_papel já existe"');
PREPARE stmt_idx2 FROM @sql_idx2;
EXECUTE stmt_idx2;
DEALLOCATE PREPARE stmt_idx2;

-- Índice para busca por ativo
SET @exist_idx3 = (SELECT COUNT(*) FROM information_schema.statistics 
                   WHERE table_schema = 'appcheckin' 
                   AND table_name = 'tenant_usuario_papel' 
                   AND index_name = 'idx_tenant_usuario_papel_ativo');
SET @sql_idx3 = IF(@exist_idx3 = 0, 
    'CREATE INDEX idx_tenant_usuario_papel_ativo ON tenant_usuario_papel(ativo)', 
    'SELECT "Índice idx_tenant_usuario_papel_ativo já existe"');
PREPARE stmt_idx3 FROM @sql_idx3;
EXECUTE stmt_idx3;
DEALLOCATE PREPARE stmt_idx3;

-- 6. Estatísticas finais
SELECT 'Migration concluída com sucesso!' as status;
SELECT COUNT(*) as registros_backup FROM usuario_tenant_backup;
SELECT COUNT(*) as registros_tenant_usuario_papel FROM tenant_usuario_papel;

-- 7. Opcional: Verificar se há registros em usuario_tenant_backup que não estão em tenant_usuario_papel
SELECT 'Verificando registros órfãos...' as status;

SELECT 
    ut.usuario_id, 
    ut.tenant_id,
    ut.status as status_antigo,
    CASE WHEN tup.id IS NULL THEN 'SEM REGISTRO' ELSE 'OK' END as status_migracao
FROM usuario_tenant_backup ut
LEFT JOIN tenant_usuario_papel tup 
    ON tup.usuario_id = ut.usuario_id 
    AND tup.tenant_id = ut.tenant_id
WHERE tup.id IS NULL
LIMIT 10;

-- =============================================
-- ROLLBACK (caso necessário)
-- =============================================
-- Para reverter esta migration, execute:
-- RENAME TABLE `usuario_tenant_backup` TO `usuario_tenant`;
-- 
-- Depois recrie as foreign keys:
-- ALTER TABLE `usuario_tenant` ADD CONSTRAINT `fk_usuario_tenant_usuario` 
--   FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;
-- ALTER TABLE `usuario_tenant` ADD CONSTRAINT `fk_usuario_tenant_tenant` 
--   FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;
-- ALTER TABLE `usuario_tenant` ADD CONSTRAINT `fk_usuario_tenant_plano` 
--   FOREIGN KEY (`plano_id`) REFERENCES `planos` (`id`) ON DELETE SET NULL;
-- =============================================

