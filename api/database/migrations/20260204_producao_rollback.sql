-- =====================================================
-- ROLLBACK: Reverter migração tenant_usuario_papel → usuario_tenant
-- ⚠️ USAR APENAS EM CASO DE EMERGÊNCIA
-- Data: 04/02/2026
-- =====================================================

-- Passo 1: Restaurar tabela original
RENAME TABLE usuario_tenant_backup TO usuario_tenant;

SELECT '✅ Tabela usuario_tenant restaurada' AS status;

-- Passo 2: Restaurar função original
DROP FUNCTION IF EXISTS get_tenant_id_from_usuario;

DELIMITER $$
CREATE FUNCTION get_tenant_id_from_usuario(p_usuario_id INT) 
RETURNS INT
DETERMINISTIC
READS SQL DATA
BEGIN
    DECLARE v_tenant_id INT;
    
    SELECT ut.tenant_id INTO v_tenant_id
    FROM usuario_tenant ut
    WHERE ut.usuario_id = p_usuario_id
    AND ut.status = 'ativo'
    LIMIT 1;
    
    IF v_tenant_id IS NULL THEN
        SET v_tenant_id = 1;
    END IF;
    
    RETURN v_tenant_id;
END$$
DELIMITER ;

SELECT '✅ Função restaurada para versão original' AS status;

-- Passo 3: Limpar dados migrados (OPCIONAL - apenas se necessário)
-- DELETE FROM tenant_usuario_papel WHERE created_at >= '2026-02-04';

SELECT '✅ Rollback concluído' AS status;
SELECT '⚠️ ATENÇÃO: Reinicie a aplicação para aplicar as mudanças' AS aviso;
