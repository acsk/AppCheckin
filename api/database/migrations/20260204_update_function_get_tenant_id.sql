-- =====================================================
-- MIGRATION: Atualizar função get_tenant_id_from_usuario
-- Data: 2026-02-04
-- =====================================================
-- 
-- Motivo: A função get_tenant_id_from_usuario estava usando
--         a tabela usuario_tenant que foi renomeada para 
--         usuario_tenant_backup. Agora deve usar tenant_usuario_papel.
-- 
-- Esta migration atualiza a função para usar a nova estrutura.
-- =====================================================

USE `appcheckin`;

-- 1. Dropar a função antiga
DROP FUNCTION IF EXISTS `get_tenant_id_from_usuario`;

-- 2. Recriar a função usando tenant_usuario_papel
DELIMITER //

CREATE FUNCTION `get_tenant_id_from_usuario`(p_usuario_id INT)
RETURNS INT
DETERMINISTIC
READS SQL DATA
BEGIN
    DECLARE v_tenant_id INT;
    
    -- Buscar tenant_id ativo do usuário na tabela tenant_usuario_papel
    -- Priorizar papel de aluno (papel_id = 1)
    SELECT tup.tenant_id INTO v_tenant_id
    FROM tenant_usuario_papel tup
    WHERE tup.usuario_id = p_usuario_id
    AND tup.ativo = 1
    ORDER BY 
        CASE tup.papel_id
            WHEN 1 THEN 1  -- Aluno tem prioridade
            WHEN 2 THEN 2  -- Professor
            WHEN 3 THEN 3  -- Admin
            WHEN 4 THEN 4  -- SuperAdmin
            ELSE 5
        END
    LIMIT 1;
    
    -- Se não encontrar, retornar tenant padrão (1)
    IF v_tenant_id IS NULL THEN
        SET v_tenant_id = 1;
    END IF;
    
    RETURN v_tenant_id;
END //

DELIMITER ;

-- 3. Verificação: Confirmar que a função foi atualizada
SELECT 
    ROUTINE_NAME, 
    ROUTINE_SCHEMA,
    CREATED,
    LAST_ALTERED
FROM INFORMATION_SCHEMA.ROUTINES 
WHERE ROUTINE_NAME = 'get_tenant_id_from_usuario' 
AND ROUTINE_SCHEMA = 'appcheckin';

SELECT 'Função get_tenant_id_from_usuario atualizada com sucesso!' as status;

-- =====================================================
-- NOTA: O trigger checkins_before_insert_tenant continua
--       funcionando pois ainda usa get_tenant_id_from_usuario,
--       apenas agora a função consulta tenant_usuario_papel.
-- =====================================================

