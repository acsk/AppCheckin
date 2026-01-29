-- Migration: Remover ENUM motivo e usar tabela de domínio motivo_matricula
-- Data: 2026-01-28
-- Objetivo: Eliminar o uso de ENUM 'motivo' em favor de tabela de domínio

-- =============================================================================
-- PARTE 1: Remover triggers que referenciam a coluna 'status' antiga
-- =============================================================================
DROP TRIGGER IF EXISTS validar_matricula_ativa_unica_insert;
DROP TRIGGER IF EXISTS update_matricula_vencida;
DROP TRIGGER IF EXISTS validar_matricula_ativa_unica_update;

-- =============================================================================
-- PARTE 2: Criar tabela de domínio para motivo_matricula
-- =============================================================================

-- 1. Criar tabela de domínio para motivo_matricula
CREATE TABLE IF NOT EXISTS motivo_matricula (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(50) NOT NULL UNIQUE,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT,
    ativo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Tabela de domínio para motivos de matrícula';

-- 2. Popular a tabela de motivos
INSERT IGNORE INTO motivo_matricula (id, codigo, nome, descricao) VALUES
(1, 'nova', 'Nova Matrícula', 'Primeira matrícula do aluno'),
(2, 'renovacao', 'Renovação', 'Renovação de matrícula existente'),
(3, 'upgrade', 'Upgrade', 'Mudança para plano superior'),
(4, 'downgrade', 'Downgrade', 'Mudança para plano inferior');

-- 3. Adicionar coluna motivo_id
ALTER TABLE matriculas ADD COLUMN motivo_id INT DEFAULT NULL COMMENT 'FK para motivo_matricula' AFTER status_id;

-- 4. Popular motivo_id baseado no motivo atual
UPDATE matriculas SET motivo_id = 1 WHERE motivo = 'nova';
UPDATE matriculas SET motivo_id = 2 WHERE motivo = 'renovacao';
UPDATE matriculas SET motivo_id = 3 WHERE motivo = 'upgrade';
UPDATE matriculas SET motivo_id = 4 WHERE motivo = 'downgrade';

-- 5. Definir valor padrão para motivo_id (1 = nova) para registros que ficaram NULL
UPDATE matriculas SET motivo_id = 1 WHERE motivo_id IS NULL;

-- 6. Adicionar FK para motivo
ALTER TABLE matriculas 
ADD CONSTRAINT fk_matriculas_motivo FOREIGN KEY (motivo_id) REFERENCES motivo_matricula(id);

-- 7. Alterar motivo_id para NOT NULL
ALTER TABLE matriculas MODIFY COLUMN motivo_id INT NOT NULL DEFAULT 1 COMMENT 'FK para motivo_matricula';

-- 8. Remover a coluna motivo (ENUM)
ALTER TABLE matriculas DROP COLUMN motivo;

-- 9. Criar índice no motivo_id
CREATE INDEX idx_matriculas_motivo_id ON matriculas(motivo_id);

-- 10. Verificar se FK de status_id existe, se não, criar
SET @fk_status_exists = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS 
    WHERE CONSTRAINT_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'matriculas' 
    AND CONSTRAINT_NAME = 'fk_matriculas_status'
);

SET @sql_fk = IF(@fk_status_exists = 0,
    'ALTER TABLE matriculas ADD CONSTRAINT fk_matriculas_status FOREIGN KEY (status_id) REFERENCES status_matricula(id)',
    'SELECT 1'
);
PREPARE stmt FROM @sql_fk;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 11. Garantir que status_id é NOT NULL
ALTER TABLE matriculas MODIFY COLUMN status_id INT NOT NULL DEFAULT 1 COMMENT 'FK para status_matricula';

-- 12. Criar índice em status_id se não existir
DROP PROCEDURE IF EXISTS create_index_if_not_exists;
DELIMITER //
CREATE PROCEDURE create_index_if_not_exists()
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'matriculas' AND INDEX_NAME = 'idx_matriculas_status_id') THEN
        CREATE INDEX idx_matriculas_status_id ON matriculas(status_id);
    END IF;
END //
DELIMITER ;
CALL create_index_if_not_exists();
DROP PROCEDURE IF EXISTS create_index_if_not_exists;

-- =============================================================================
-- PARTE 3: Recriar triggers usando status_id em vez de status (ENUM)
-- =============================================================================

-- Trigger para validar matrícula única ativa no INSERT
DELIMITER //
CREATE TRIGGER validar_matricula_ativa_unica_insert
BEFORE INSERT ON matriculas
FOR EACH ROW
BEGIN
    DECLARE matriculas_ativas INT;
    DECLARE status_ativa_id INT;
    
    -- Buscar o ID do status 'ativa'
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
    
    -- Buscar os IDs dos status
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
    
    -- Buscar o ID do status 'ativa'
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
