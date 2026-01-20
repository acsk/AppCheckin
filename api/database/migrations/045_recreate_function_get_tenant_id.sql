-- =====================================================
-- MIGRATION 045: Recriar função get_tenant_id_from_usuario
-- e corrigir TRIGGER checkins_before_insert_tenant
-- =====================================================
-- 
-- Motivo: A função foi deletada mas o TRIGGER ainda a referencia.
-- Solução: Recriar a função com a lógica correta e manter o TRIGGER
-- (ou remover o TRIGGER se preferir usar apenas PHP)
--
-- Esta migration recria a função baseada na lógica de TenantService.php
-- =====================================================

-- 1. Recriar a função deletada com a lógica correta
DELIMITER //

CREATE FUNCTION IF NOT EXISTS `get_tenant_id_from_usuario`(p_usuario_id INT)
RETURNS INT
DETERMINISTIC
READS SQL DATA
BEGIN
    DECLARE v_tenant_id INT;
    
    -- Buscar tenant_id ativo do usuário
    SELECT ut.tenant_id INTO v_tenant_id
    FROM usuario_tenant ut
    WHERE ut.usuario_id = p_usuario_id
    AND ut.status = 'ativo'
    LIMIT 1;
    
    -- Se não encontrar, retornar tenant padrão (1)
    IF v_tenant_id IS NULL THEN
        SET v_tenant_id = 1;
    END IF;
    
    RETURN v_tenant_id;
END //

DELIMITER ;

-- 2. Verificação: Confirmar que a função foi criada
SELECT ROUTINE_NAME, ROUTINE_SCHEMA 
FROM INFORMATION_SCHEMA.ROUTINES 
WHERE ROUTINE_NAME = 'get_tenant_id_from_usuario' 
AND ROUTINE_SCHEMA = 'appcheckin';
