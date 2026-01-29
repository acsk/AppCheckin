-- Correção dos triggers da tabela matriculas
-- Problema: Os triggers estavam usando 'usuario_id' mas a coluna correta é 'aluno_id'
-- Executar este script para corrigir o erro:
-- "SQLSTATE[42S22]: Column not found: 1054 Unknown column 'usuario_id' in 'where clause'"

-- Remover os triggers antigos com problema
DROP TRIGGER IF EXISTS validar_matricula_ativa_unica_insert;
DROP TRIGGER IF EXISTS validar_matricula_ativa_unica_update;

-- Recriar trigger de INSERT corrigido (usando aluno_id)
DELIMITER $$
CREATE TRIGGER `validar_matricula_ativa_unica_insert` BEFORE INSERT ON `matriculas` FOR EACH ROW 
BEGIN
    DECLARE matriculas_ativas INT;
    
    IF NEW.status = 'ativa' THEN
        SELECT COUNT(*) INTO matriculas_ativas
        FROM matriculas
        WHERE tenant_id = NEW.tenant_id
          AND aluno_id = NEW.aluno_id
          AND plano_id = NEW.plano_id
          AND status = 'ativa'
          AND id != COALESCE(NEW.id, 0);
        
        IF matriculas_ativas > 0 THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Ja existe uma matricula ativa para este aluno e plano';
        END IF;
    END IF;
END$$
DELIMITER ;

-- Recriar trigger de UPDATE corrigido (usando aluno_id)
DELIMITER $$
CREATE TRIGGER `validar_matricula_ativa_unica_update` BEFORE UPDATE ON `matriculas` FOR EACH ROW 
BEGIN
    DECLARE matriculas_ativas INT;
    
    IF NEW.status = 'ativa' AND (OLD.status != 'ativa' OR NEW.plano_id != OLD.plano_id) THEN
        SELECT COUNT(*) INTO matriculas_ativas
        FROM matriculas
        WHERE tenant_id = NEW.tenant_id
          AND aluno_id = NEW.aluno_id
          AND plano_id = NEW.plano_id
          AND status = 'ativa'
          AND id != NEW.id;
        
        IF matriculas_ativas > 0 THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Ja existe uma matricula ativa para este aluno e plano';
        END IF;
    END IF;
END$$
DELIMITER ;

-- Verificar se os triggers foram criados corretamente
SELECT TRIGGER_NAME, EVENT_OBJECT_TABLE, ACTION_TIMING, EVENT_MANIPULATION 
FROM information_schema.triggers 
WHERE TRIGGER_SCHEMA = DATABASE() 
AND EVENT_OBJECT_TABLE = 'matriculas';
