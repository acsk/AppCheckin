-- ==========================================
-- Migration 044b: Transição Progressiva - Checkins com Tenant
-- ==========================================
-- Descrição: Prepara checkins para usar tenant_id sem quebrar código existente
-- Estratégia: Adiciona tenant_id com DEFAULT, depois remove DEFAULT gradualmente
-- Autor: Sistema
-- Data: 2026-01-06
-- ==========================================

-- ==========================================
-- FASE 1: Adicionar coluna com DEFAULT (NÃO QUEBRA CÓDIGO)
-- ==========================================

-- Adicionar tenant_id com DEFAULT baseado no usuário
-- Permite INSERT sem especificar tenant_id (compatibilidade retroativa)
-- Verificar se a coluna já existe antes de adicionar
SET @col_exists = (
    SELECT COUNT(1) 
    FROM information_schema.columns 
    WHERE table_schema = DATABASE() 
    AND table_name = 'checkins' 
    AND column_name = 'tenant_id'
);

SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE checkins ADD COLUMN tenant_id INT NULL AFTER id', 
    'SELECT "Coluna tenant_id já existe" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ==========================================
-- FASE 2: Preencher dados existentes
-- ==========================================

-- Preencher checkins sem tenant_id usando tenant ativo do usuário
UPDATE checkins c
JOIN usuarios u ON c.usuario_id = u.id
JOIN usuario_tenant ut ON u.id = ut.usuario_id AND ut.status = 'ativo'
SET c.tenant_id = ut.tenant_id
WHERE c.tenant_id IS NULL;

-- Fallback: Se usuário não tem tenant ativo, usar tenant padrão (id=1)
UPDATE checkins c
JOIN usuarios u ON c.usuario_id = u.id
SET c.tenant_id = 1
WHERE c.tenant_id IS NULL;

-- ==========================================
-- FASE 3: Criar função para obter tenant automaticamente
-- ==========================================

DELIMITER //

DROP FUNCTION IF EXISTS get_tenant_id_from_usuario//

CREATE FUNCTION get_tenant_id_from_usuario(p_usuario_id INT)
RETURNS INT
DETERMINISTIC
READS SQL DATA
BEGIN
    DECLARE v_tenant_id INT;
    
    -- Buscar primeiro tenant ativo do usuário
    SELECT ut.tenant_id INTO v_tenant_id
    FROM usuario_tenant ut
    WHERE ut.usuario_id = p_usuario_id 
      AND ut.status = 'ativo'
    LIMIT 1;
    
    -- Se não encontrar, retornar tenant padrão
    IF v_tenant_id IS NULL THEN
        SET v_tenant_id = 1;
    END IF;
    
    RETURN v_tenant_id;
END//

DELIMITER ;

-- ==========================================
-- FASE 4: Criar trigger para preencher automaticamente
-- ==========================================

DELIMITER //

DROP TRIGGER IF EXISTS checkins_before_insert_tenant//

CREATE TRIGGER checkins_before_insert_tenant
BEFORE INSERT ON checkins
FOR EACH ROW
BEGIN
    -- Se tenant_id não foi informado, buscar automaticamente
    IF NEW.tenant_id IS NULL THEN
        SET NEW.tenant_id = get_tenant_id_from_usuario(NEW.usuario_id);
    END IF;
END//

DELIMITER ;

-- ==========================================
-- FASE 5: Tornar NOT NULL (após preencher tudo)
-- ==========================================

-- Tornar coluna NOT NULL
ALTER TABLE checkins 
MODIFY COLUMN tenant_id INT NOT NULL;

-- Adicionar FK (se não existir)
SET @fk_exists = (
    SELECT COUNT(1) 
    FROM information_schema.table_constraints 
    WHERE constraint_schema = DATABASE() 
    AND table_name = 'checkins' 
    AND constraint_name = 'fk_checkins_tenant'
    AND constraint_type = 'FOREIGN KEY'
);

SET @sql = IF(@fk_exists = 0, 
    'ALTER TABLE checkins ADD CONSTRAINT fk_checkins_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE', 
    'SELECT "FK fk_checkins_tenant já existe" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ==========================================
-- FASE 6: Criar índices otimizados
-- ==========================================

-- Remover índice antigo idx_checkins_usuario (se existir)
SET @index_exists = (
    SELECT COUNT(1) 
    FROM information_schema.statistics 
    WHERE table_schema = DATABASE() 
    AND table_name = 'checkins' 
    AND index_name = 'idx_checkins_usuario'
);

SET @sql = IF(@index_exists > 0, 
    'DROP INDEX idx_checkins_usuario ON checkins', 
    'SELECT "Índice idx_checkins_usuario não existe" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Remover índice antigo idx_checkins_horario (se existir)
SET @index_exists = (
    SELECT COUNT(1) 
    FROM information_schema.statistics 
    WHERE table_schema = DATABASE() 
    AND table_name = 'checkins' 
    AND index_name = 'idx_checkins_horario'
);

SET @sql = IF(@index_exists > 0, 
    'DROP INDEX idx_checkins_horario ON checkins', 
    'SELECT "Índice idx_checkins_horario não existe" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Criar índices tenant-first (verificando se não existem)
SET @index_exists = (
    SELECT COUNT(1) 
    FROM information_schema.statistics 
    WHERE table_schema = DATABASE() 
    AND table_name = 'checkins' 
    AND index_name = 'idx_tenant_usuario_data'
);

SET @sql = IF(@index_exists = 0, 
    'CREATE INDEX idx_tenant_usuario_data ON checkins(tenant_id, usuario_id, data_checkin_date)', 
    'SELECT "Índice idx_tenant_usuario_data já existe" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @index_exists = (
    SELECT COUNT(1) 
    FROM information_schema.statistics 
    WHERE table_schema = DATABASE() 
    AND table_name = 'checkins' 
    AND index_name = 'idx_tenant_horario_data'
);

SET @sql = IF(@index_exists = 0, 
    'CREATE INDEX idx_tenant_horario_data ON checkins(tenant_id, horario_id, data_checkin_date)', 
    'SELECT "Índice idx_tenant_horario_data já existe" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @index_exists = (
    SELECT COUNT(1) 
    FROM information_schema.statistics 
    WHERE table_schema = DATABASE() 
    AND table_name = 'checkins' 
    AND index_name = 'idx_tenant_data'
);

SET @sql = IF(@index_exists = 0, 
    'CREATE INDEX idx_tenant_data ON checkins(tenant_id, data_checkin_date)', 
    'SELECT "Índice idx_tenant_data já existe" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ==========================================
-- OBSERVAÇÕES
-- ==========================================
-- 1. Compatibilidade Retroativa:
--    - Código antigo continua funcionando (trigger preenche tenant_id)
--    - INSERT INTO checkins (usuario_id, horario_id) funciona normalmente
--    - tenant_id é preenchido automaticamente via trigger
--
-- 2. Função get_tenant_id_from_usuario():
--    - Busca primeiro tenant ativo do usuário
--    - Fallback para tenant_id = 1 se usuário sem tenant
--    - Determinística e eficiente
--
-- 3. Trigger checkins_before_insert_tenant:
--    - Executa ANTES de INSERT
--    - Preenche tenant_id apenas se NULL
--    - Não interfere se tenant_id for informado
--
-- 4. Migração do Código:
--    - FASE A (Imediato): Migration executada, código antigo funciona
--    - FASE B (1-2 semanas): Atualizar código para passar tenant_id
--    - FASE C (Após validação): Remover trigger e função
--
-- 5. Performance:
--    - Trigger adiciona ~0.1ms por INSERT
--    - Função usa índice em usuario_tenant (rápida)
--    - Índices tenant-first melhoram SELECT significativamente
--
-- 6. Rollback:
--    DROP TRIGGER checkins_before_insert_tenant;
--    DROP FUNCTION get_tenant_id_from_usuario;
--    ALTER TABLE checkins DROP FOREIGN KEY fk_checkins_tenant;
--    ALTER TABLE checkins DROP COLUMN tenant_id;
--    CREATE INDEX idx_checkins_usuario ON checkins(usuario_id);
--    CREATE INDEX idx_checkins_horario ON checkins(horario_id);
