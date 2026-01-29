-- Migration: Remover ENUM status e usar apenas status_id (FK para status_matricula)
-- Data: 2026-01-28
-- Objetivo: Eliminar o uso de ENUM em favor de tabela de domínio status_matricula

-- 1. Primeiro, garantir que status_id está populado corretamente baseado no status atual
UPDATE matriculas m
SET m.status_id = (
    SELECT sm.id FROM status_matricula sm WHERE sm.codigo = m.status
)
WHERE m.status_id IS NULL AND m.status IS NOT NULL;

-- 2. Verificar se há algum status sem correspondência na tabela de domínio
-- Se houver status 'pendente' ou 'bloqueado' que não existem na status_matricula, adicionar
INSERT IGNORE INTO status_matricula (codigo, nome, descricao, cor, icone, ordem, permite_checkin, ativo, automatico)
SELECT 'pendente', 'Pendente', 'Matrícula aguardando confirmação', '#9ca3af', 'clock', 0, 0, 1, 0
WHERE NOT EXISTS (SELECT 1 FROM status_matricula WHERE codigo = 'pendente');

INSERT IGNORE INTO status_matricula (codigo, nome, descricao, cor, icone, ordem, permite_checkin, ativo, automatico)
SELECT 'bloqueado', 'Bloqueado', 'Matrícula bloqueada temporariamente', '#dc2626', 'lock', 5, 0, 1, 0
WHERE NOT EXISTS (SELECT 1 FROM status_matricula WHERE codigo = 'bloqueado');

-- 3. Atualizar novamente após inserir os novos status
UPDATE matriculas m
SET m.status_id = (
    SELECT sm.id FROM status_matricula sm WHERE sm.codigo = m.status
)
WHERE m.status_id IS NULL AND m.status IS NOT NULL;

-- 4. Garantir que status_id tem valor padrão para registros que ficaram NULL
-- (usa 'ativa' como padrão, ID=1)
UPDATE matriculas SET status_id = 1 WHERE status_id IS NULL;

-- 5. Adicionar a FK se não existir
SET @fk_exists = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS 
    WHERE CONSTRAINT_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'matriculas' 
    AND CONSTRAINT_NAME = 'fk_matriculas_status'
);

-- Criar FK apenas se não existir
SET @sql = IF(@fk_exists = 0,
    'ALTER TABLE matriculas ADD CONSTRAINT fk_matriculas_status FOREIGN KEY (status_id) REFERENCES status_matricula(id)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 6. Alterar status_id para NOT NULL (agora que todos têm valor)
ALTER TABLE matriculas MODIFY COLUMN status_id INT NOT NULL DEFAULT 1 COMMENT 'FK para status_matricula';

-- 7. Remover a coluna status (ENUM)
ALTER TABLE matriculas DROP COLUMN status;

-- 8. Remover o índice do status antigo se existir (MySQL não suporta DROP INDEX IF EXISTS)
-- Usar procedimento para verificar antes
DROP PROCEDURE IF EXISTS drop_index_if_exists;
DELIMITER //
CREATE PROCEDURE drop_index_if_exists()
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'matriculas' AND INDEX_NAME = 'idx_status') THEN
        ALTER TABLE matriculas DROP INDEX idx_status;
    END IF;
END //
DELIMITER ;
CALL drop_index_if_exists();
DROP PROCEDURE IF EXISTS drop_index_if_exists;

-- 9. Criar índice no novo status_id
CREATE INDEX idx_matriculas_status_id ON matriculas(status_id);

-- =============================================================================
-- TAMBÉM: Fazer o mesmo para o campo 'motivo' que usa ENUM
-- =============================================================================

-- 10. Criar tabela de domínio para motivo_matricula se não existir
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

-- 11. Popular a tabela de motivos
INSERT IGNORE INTO motivo_matricula (codigo, nome, descricao) VALUES
('nova', 'Nova Matrícula', 'Primeira matrícula do aluno'),
('renovacao', 'Renovação', 'Renovação de matrícula existente'),
('upgrade', 'Upgrade', 'Mudança para plano superior'),
('downgrade', 'Downgrade', 'Mudança para plano inferior');

-- 12. Adicionar coluna motivo_id
ALTER TABLE matriculas ADD COLUMN motivo_id INT DEFAULT NULL COMMENT 'FK para motivo_matricula' AFTER status_id;

-- 13. Popular motivo_id baseado no motivo atual
UPDATE matriculas m
SET m.motivo_id = (
    SELECT mm.id FROM motivo_matricula mm WHERE mm.codigo = m.motivo
)
WHERE m.motivo IS NOT NULL;

-- 14. Definir valor padrão para motivo_id (1 = nova)
UPDATE matriculas SET motivo_id = 1 WHERE motivo_id IS NULL;

-- 15. Adicionar FK para motivo
ALTER TABLE matriculas 
ADD CONSTRAINT fk_matriculas_motivo FOREIGN KEY (motivo_id) REFERENCES motivo_matricula(id);

-- 16. Alterar motivo_id para NOT NULL
ALTER TABLE matriculas MODIFY COLUMN motivo_id INT NOT NULL DEFAULT 1 COMMENT 'FK para motivo_matricula';

-- 17. Remover a coluna motivo (ENUM)
ALTER TABLE matriculas DROP COLUMN motivo;

-- 18. Criar índice no motivo_id
CREATE INDEX idx_matriculas_motivo_id ON matriculas(motivo_id);
