-- =====================================================
-- MIGRAÇÃO PRODUÇÃO: usuario_tenant → tenant_usuario_papel
-- Data: 04/02/2026
-- Descrição: Migra dados e atualiza função MySQL
-- =====================================================

-- Passo 1: Verificar estado atual
SELECT 'Estado atual das tabelas:' AS status;
SELECT COUNT(*) AS total_usuario_tenant FROM usuario_tenant;
SELECT COUNT(*) AS total_tenant_usuario_papel FROM tenant_usuario_papel;

-- Passo 2: Migrar dados de usuario_tenant para tenant_usuario_papel
-- Importante: Define papel_id baseado no contexto
-- papel_id = 1 (aluno) para usuários normais
-- papel_id = 3 (admin) para admins
INSERT INTO tenant_usuario_papel (tenant_id, usuario_id, papel_id, ativo, created_at, updated_at)
SELECT 
    ut.tenant_id,
    ut.usuario_id,
    -- Define papel: admin (3) ou aluno (1)
    CASE 
        WHEN EXISTS (
            SELECT 1 FROM usuarios u 
            WHERE u.id = ut.usuario_id 
            AND u.role_id_bkp = 2
        ) THEN 3  -- Admin
        ELSE 1    -- Aluno
    END AS papel_id,
    -- Converte status VARCHAR para TINYINT
    CASE 
        WHEN ut.status = 'ativo' THEN 1
        ELSE 0
    END AS ativo,
    ut.created_at,
    ut.updated_at
FROM usuario_tenant ut
WHERE NOT EXISTS (
    -- Evita duplicatas (caso já exista algum registro)
    SELECT 1 FROM tenant_usuario_papel tup
    WHERE tup.tenant_id = ut.tenant_id
    AND tup.usuario_id = ut.usuario_id
    AND tup.papel_id = CASE 
        WHEN EXISTS (
            SELECT 1 FROM usuarios u 
            WHERE u.id = ut.usuario_id 
            AND u.role_id_bkp = 2
        ) THEN 3
        ELSE 1
    END
)
ORDER BY ut.created_at;

-- Passo 3: Verificar migração
SELECT 'Verificação pós-migração:' AS status;
SELECT COUNT(*) AS total_migrados FROM tenant_usuario_papel;

-- Mostrar comparação
SELECT 'Comparação de registros por tenant:' AS status;
SELECT 
    t.id,
    t.nome AS tenant_nome,
    (SELECT COUNT(*) FROM usuario_tenant ut WHERE ut.tenant_id = t.id) AS antigos,
    (SELECT COUNT(DISTINCT usuario_id) FROM tenant_usuario_papel tup WHERE tup.tenant_id = t.id) AS novos
FROM tenants t;

-- Passo 4: Renomear tabela antiga para backup
RENAME TABLE usuario_tenant TO usuario_tenant_backup;

SELECT 'Tabela usuario_tenant renomeada para usuario_tenant_backup' AS status;

-- Passo 5: Atualizar função get_tenant_id_from_usuario
DROP FUNCTION IF EXISTS get_tenant_id_from_usuario;

DELIMITER $$
CREATE FUNCTION get_tenant_id_from_usuario(p_usuario_id INT) 
RETURNS INT
DETERMINISTIC
READS SQL DATA
BEGIN
    DECLARE v_tenant_id INT;
    
    -- Busca tenant_id do usuário na nova tabela
    SELECT tup.tenant_id INTO v_tenant_id
    FROM tenant_usuario_papel tup
    WHERE tup.usuario_id = p_usuario_id
    AND tup.ativo = 1
    LIMIT 1;
    
    -- Se não encontrar, retorna tenant padrão
    IF v_tenant_id IS NULL THEN
        SET v_tenant_id = 1;
    END IF;
    
    RETURN v_tenant_id;
END$$
DELIMITER ;

SELECT 'Função get_tenant_id_from_usuario atualizada com sucesso' AS status;

-- Passo 6: Verificar integridade dos dados
SELECT 'Verificação de integridade:' AS status;

-- Usuários sem tenant ativo
SELECT 
    u.id,
    u.nome,
    u.email,
    'SEM TENANT ATIVO' AS problema
FROM usuarios u
WHERE u.ativo = 1
AND NOT EXISTS (
    SELECT 1 FROM tenant_usuario_papel tup
    WHERE tup.usuario_id = u.id
    AND tup.ativo = 1
)
LIMIT 10;

-- Passo 7: Testar função atualizada
SELECT 'Teste da função:' AS status;
SELECT 
    id,
    nome,
    get_tenant_id_from_usuario(id) AS tenant_id_funcao
FROM usuarios
LIMIT 5;

-- Passo 8: Estatísticas finais
SELECT 'Estatísticas finais:' AS status;
SELECT 
    'tenant_usuario_papel' AS tabela,
    COUNT(*) AS total_registros,
    COUNT(DISTINCT tenant_id) AS tenants_distintos,
    COUNT(DISTINCT usuario_id) AS usuarios_distintos,
    SUM(CASE WHEN ativo = 1 THEN 1 ELSE 0 END) AS ativos,
    SUM(CASE WHEN ativo = 0 THEN 1 ELSE 0 END) AS inativos
FROM tenant_usuario_papel;

-- =====================================================
-- FIM DA MIGRAÇÃO
-- =====================================================

SELECT '✅ Migração concluída com sucesso!' AS status;
SELECT 'IMPORTANTE: A tabela usuario_tenant foi renomeada para usuario_tenant_backup' AS aviso;
SELECT 'Após validar a migração, você pode excluir a tabela de backup:' AS aviso;
SELECT 'DROP TABLE usuario_tenant_backup;' AS comando_cleanup;
