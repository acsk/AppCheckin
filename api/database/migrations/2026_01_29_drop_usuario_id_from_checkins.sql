-- =====================================================
-- Migration: Remover usuario_id da tabela checkins
-- Data: 2026-01-29
-- Descrição: Remove a coluna redundante usuario_id da tabela checkins
--            pois a relação agora é feita via aluno_id
-- Banco: MariaDB 11.8.3 (Produção)
-- =====================================================

-- IMPORTANTE: Execute esta migration APÓS confirmar que:
-- 1. Todos os checkins têm aluno_id preenchido
-- 2. O código foi atualizado para não usar mais checkins.usuario_id

-- =====================================================
-- FASE 1: VERIFICAÇÃO
-- =====================================================

-- Verificar se todos os checkins têm aluno_id
SELECT 
    COUNT(*) as total_checkins,
    SUM(CASE WHEN aluno_id IS NULL THEN 1 ELSE 0 END) as sem_aluno_id,
    SUM(CASE WHEN aluno_id IS NOT NULL THEN 1 ELSE 0 END) as com_aluno_id
FROM checkins;

-- Se houver checkins sem aluno_id, popular antes de remover a coluna
UPDATE checkins c
SET c.aluno_id = (
    SELECT a.id FROM alunos a WHERE a.usuario_id = c.usuario_id LIMIT 1
)
WHERE c.aluno_id IS NULL AND c.usuario_id IS NOT NULL;

-- =====================================================
-- FASE 2: REMOVER TRIGGER QUE USA usuario_id
-- =====================================================

-- O trigger usa: get_tenant_id_from_usuario(NEW.usuario_id)
-- Precisamos atualizar para usar aluno_id
DROP TRIGGER IF EXISTS `checkins_before_insert_tenant`;

DELIMITER $$
CREATE TRIGGER `checkins_before_insert_tenant` BEFORE INSERT ON `checkins` FOR EACH ROW 
BEGIN
    -- Agora obtém tenant_id a partir do aluno_id
    IF NEW.tenant_id IS NULL AND NEW.aluno_id IS NOT NULL THEN
        SET NEW.tenant_id = (
            SELECT tup.tenant_id 
            FROM tenant_usuario_papel tup 
            INNER JOIN alunos a ON a.usuario_id = tup.usuario_id
            WHERE a.id = NEW.aluno_id 
            AND tup.ativo = 1
            LIMIT 1
        );
    END IF;
END$$
DELIMITER ;

-- =====================================================
-- FASE 3: REMOVER ÍNDICES E CONSTRAINTS QUE USAM usuario_id
-- =====================================================
-- NOTA: Ignore erros 1091 (índice não existe) - pode já ter sido removido

-- 3.1 Verificar índices existentes antes de remover
SHOW INDEX FROM checkins WHERE Column_name = 'usuario_id';

-- 3.2 Remover FK que usa o índice idx_checkins_horario_usuario (se existir)
-- Ignore erro se não existir
ALTER TABLE `checkins` DROP FOREIGN KEY `checkins_ibfk_2`;

-- 3.3 Remover índices que usam usuario_id (executar cada um, ignorar erro 1091)
-- ALTER TABLE `checkins` DROP INDEX `unique_usuario_horario_data`;
-- ALTER TABLE `checkins` DROP INDEX `idx_checkins_horario_usuario`;
-- ALTER TABLE `checkins` DROP INDEX `idx_tenant_usuario_data`;

-- =====================================================
-- FASE 4: REMOVER A COLUNA usuario_id
-- =====================================================

ALTER TABLE `checkins` DROP COLUMN `usuario_id`;

-- =====================================================
-- FASE 5: CRIAR NOVOS ÍNDICES USANDO aluno_id
-- =====================================================

-- 5.1 Nova UNIQUE constraint (aluno_id, horario_id, data_checkin_date)
ALTER TABLE `checkins` ADD UNIQUE KEY `unique_aluno_horario_data` (`aluno_id`, `horario_id`, `data_checkin_date`);

-- 5.2 Novo índice (horario_id, aluno_id)
ALTER TABLE `checkins` ADD KEY `idx_checkins_horario_aluno` (`horario_id`, `aluno_id`);

-- 5.3 Novo índice (tenant_id, aluno_id, data_checkin_date)
ALTER TABLE `checkins` ADD KEY `idx_tenant_aluno_data` (`tenant_id`, `aluno_id`, `data_checkin_date`);

-- 5.4 Recriar FK para horarios (foi removida na fase 3)
ALTER TABLE `checkins` ADD CONSTRAINT `checkins_ibfk_2` FOREIGN KEY (`horario_id`) REFERENCES `horarios` (`id`) ON DELETE CASCADE;

-- 5.5 Adicionar FK para alunos (se não existir)
-- ALTER TABLE `checkins` ADD CONSTRAINT `fk_checkins_aluno` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE;

-- =====================================================
-- FASE 6: VERIFICAÇÃO FINAL
-- =====================================================

-- Verificar estrutura
DESCRIBE checkins;

-- Verificar índices
SHOW INDEX FROM checkins;

-- Contar checkins para confirmar que dados estão intactos
SELECT COUNT(*) as total_checkins FROM checkins;

-- Verificar trigger
SHOW TRIGGERS LIKE 'checkins';

