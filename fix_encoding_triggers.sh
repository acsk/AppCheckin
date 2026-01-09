#!/bin/bash
# Script para reaplicar os triggers de validação de matrícula com encoding correto

echo "Reaplicando triggers de validação de matrícula..."

docker exec appcheckin-db mysql -u appcheckin -pappcheckin123 appcheckin <<'EOF'

DELIMITER //

-- Remover triggers existentes
DROP TRIGGER IF EXISTS validar_matricula_ativa_unica_insert//
DROP TRIGGER IF EXISTS validar_matricula_ativa_unica_update//

-- Recriar triggers com encoding correto (sem acentos)
CREATE TRIGGER validar_matricula_ativa_unica_insert
BEFORE INSERT ON matriculas
FOR EACH ROW
BEGIN
    DECLARE matriculas_ativas INT;
    
    IF NEW.status = 'ativa' THEN
        SELECT COUNT(*) INTO matriculas_ativas
        FROM matriculas
        WHERE tenant_id = NEW.tenant_id
          AND usuario_id = NEW.usuario_id
          AND plano_id = NEW.plano_id
          AND status = 'ativa'
          AND id != COALESCE(NEW.id, 0);
        
        IF matriculas_ativas > 0 THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Ja existe uma matricula ativa para este usuario e plano';
        END IF;
    END IF;
END//

CREATE TRIGGER validar_matricula_ativa_unica_update
BEFORE UPDATE ON matriculas
FOR EACH ROW
BEGIN
    DECLARE matriculas_ativas INT;
    
    IF NEW.status = 'ativa' AND (OLD.status != 'ativa' OR NEW.plano_id != OLD.plano_id) THEN
        SELECT COUNT(*) INTO matriculas_ativas
        FROM matriculas
        WHERE tenant_id = NEW.tenant_id
          AND usuario_id = NEW.usuario_id
          AND plano_id = NEW.plano_id
          AND status = 'ativa'
          AND id != NEW.id;
        
        IF matriculas_ativas > 0 THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Ja existe uma matricula ativa para este usuario e plano';
        END IF;
    END IF;
END//

DELIMITER ;

-- Verificar triggers criados
SHOW TRIGGERS WHERE `Table` = 'matriculas' AND Trigger LIKE 'validar_matricula_ativa_unica%';

EOF

echo "Triggers reaplicados com sucesso!"
