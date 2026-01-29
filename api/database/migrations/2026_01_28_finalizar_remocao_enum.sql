-- Migration: Finalizar remoção do ENUM motivo
-- Data: 2026-01-28

-- 1. Popular motivo_id baseado no motivo atual
UPDATE matriculas SET motivo_id = 1 WHERE motivo = 'nova' AND motivo_id IS NULL;
UPDATE matriculas SET motivo_id = 2 WHERE motivo = 'renovacao' AND motivo_id IS NULL;
UPDATE matriculas SET motivo_id = 3 WHERE motivo = 'upgrade' AND motivo_id IS NULL;
UPDATE matriculas SET motivo_id = 4 WHERE motivo = 'downgrade' AND motivo_id IS NULL;

-- 2. Definir valor padrão para registros que ainda ficaram NULL
UPDATE matriculas SET motivo_id = 1 WHERE motivo_id IS NULL;

-- 3. Adicionar FK para motivo (verificar se já existe)
SET @fk_motivo_exists = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS 
    WHERE CONSTRAINT_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'matriculas' 
    AND CONSTRAINT_NAME = 'fk_matriculas_motivo'
);
SET @sql = IF(@fk_motivo_exists = 0,
    'ALTER TABLE matriculas ADD CONSTRAINT fk_matriculas_motivo FOREIGN KEY (motivo_id) REFERENCES motivo_matricula(id)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 4. Alterar motivo_id para NOT NULL
ALTER TABLE matriculas MODIFY COLUMN motivo_id INT NOT NULL DEFAULT 1 COMMENT 'FK para motivo_matricula';

-- 5. Remover a coluna motivo (ENUM)
ALTER TABLE matriculas DROP COLUMN motivo;

-- 6. Criar índice no motivo_id se não existir
DROP PROCEDURE IF EXISTS create_motivo_index;
DELIMITER //
CREATE PROCEDURE create_motivo_index()
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'matriculas' AND INDEX_NAME = 'idx_matriculas_motivo_id') THEN
        CREATE INDEX idx_matriculas_motivo_id ON matriculas(motivo_id);
    END IF;
END //
DELIMITER ;
CALL create_motivo_index();
DROP PROCEDURE IF EXISTS create_motivo_index;

-- =============================================================================
-- Recriar triggers usando status_id
-- =============================================================================

-- Trigger para validar matrícula única ativa no INSERT
DELIMITER //
CREATE TRIGGER validar_matricula_ativa_unica_insert
BEFORE INSERT ON matriculas
FOR EACH ROW
BEGIN
    DECLARE matriculas_ativas INT;
    DECLARE status_ativa_id INT;
    
    SELECT id INTO status_ativa_id FROM status_matricula WHERE codigo = 'ativa' LIMIT 1;
    
    IF NEW.status_id = status_ativa_id THEN
        SELECT COUNT(*) INTO matriculas_ativas
        FROM matriculas
        WHERE tenant_id = NEW.tenant_id
          AND aluno_id = NEW.aluno_id
          AND plano_id = NEW.plano_id
          AND status_id = status_ativa_id
          AND id != COALESCE(NEW.id, 0);
        
        IF matriculas_ativas > 0 THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Ja existe uma matricula ativa para este aluno e plano';
        END IF;
    END IF;
END //
DELIMITER ;

-- Trigger para atualizar matrícula vencida no UPDATE
DELIMITER //
CREATE TRIGGER update_matricula_vencida
BEFORE UPDATE ON matriculas
FOR EACH ROW
BEGIN
    DECLARE status_ativa_id INT;
    DECLARE status_vencida_id INT;
    
    SELECT id INTO status_ativa_id FROM status_matricula WHERE codigo = 'ativa' LIMIT 1;
    SELECT id INTO status_vencida_id FROM status_matricula WHERE codigo = 'vencida' LIMIT 1;
    
    IF NEW.data_vencimento < CURDATE() AND NEW.status_id = status_ativa_id THEN
        SET NEW.status_id = status_vencida_id;
    END IF;
END //
DELIMITER ;

-- Trigger para validar matrícula única ativa no UPDATE
DELIMITER //
CREATE TRIGGER validar_matricula_ativa_unica_update
BEFORE UPDATE ON matriculas
FOR EACH ROW
BEGIN
    DECLARE matriculas_ativas INT;
    DECLARE status_ativa_id INT;
    
    SELECT id INTO status_ativa_id FROM status_matricula WHERE codigo = 'ativa' LIMIT 1;
    
    IF NEW.status_id = status_ativa_id AND (OLD.status_id != status_ativa_id OR NEW.plano_id != OLD.plano_id) THEN
        SELECT COUNT(*) INTO matriculas_ativas
        FROM matriculas
        WHERE tenant_id = NEW.tenant_id
          AND aluno_id = NEW.aluno_id
          AND plano_id = NEW.plano_id
          AND status_id = status_ativa_id
          AND id != NEW.id;
        
        IF matriculas_ativas > 0 THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Ja existe uma matricula ativa para este aluno e plano';
        END IF;
    END IF;
END //
DELIMITER ;
