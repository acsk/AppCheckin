-- ============================================================================
-- MIGRATION PARA PRODUÇÃO - AppCheckin
-- Data: 29/01/2026
-- Descrição: Migração de ENUMs para tabelas de domínio e ajustes estruturais
-- ============================================================================

-- IMPORTANTE: Execute este script em uma janela de manutenção
-- Faça backup do banco antes de executar!

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "-03:00";

-- ============================================================================
-- 1. ADICIONAR COLUNA foto_caminho NA TABELA alunos
-- ============================================================================
ALTER TABLE `alunos` 
ADD COLUMN IF NOT EXISTS `foto_caminho` VARCHAR(255) DEFAULT NULL 
COMMENT 'Caminho relativo da foto de perfil (ex: /uploads/fotos/aluno_123_1234567890.jpg)';

-- Criar índice para foto_caminho se não existir
CREATE INDEX IF NOT EXISTS `idx_alunos_foto_caminho` ON `alunos` (`foto_caminho`);

-- ============================================================================
-- 2. ADICIONAR STATUS PENDENTE E BLOQUEADO NA TABELA status_matricula
-- ============================================================================
-- Verificar e inserir status pendente (id=5)
INSERT INTO `status_matricula` (`id`, `codigo`, `nome`, `descricao`, `cor`, `icone`, `ordem`, `permite_checkin`, `ativo`, `dias_tolerancia`, `automatico`)
SELECT 5, 'pendente', 'Pendente', 'Matrícula aguardando pagamento inicial', '#f59e0b', 'clock', 0, 0, 1, 0, 0
WHERE NOT EXISTS (SELECT 1 FROM `status_matricula` WHERE `codigo` = 'pendente');

-- Verificar e inserir status bloqueado (id=6)
INSERT INTO `status_matricula` (`id`, `codigo`, `nome`, `descricao`, `cor`, `icone`, `ordem`, `permite_checkin`, `ativo`, `dias_tolerancia`, `automatico`)
SELECT 6, 'bloqueado', 'Bloqueado', 'Matrícula bloqueada por inadimplência prolongada', '#dc2626', 'lock', 5, 0, 1, 15, 1
WHERE NOT EXISTS (SELECT 1 FROM `status_matricula` WHERE `codigo` = 'bloqueado');

-- ============================================================================
-- 3. CRIAR TABELA motivo_matricula (tabela de domínio)
-- ============================================================================
CREATE TABLE IF NOT EXISTS `motivo_matricula` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `codigo` VARCHAR(50) NOT NULL,
  `nome` VARCHAR(100) NOT NULL,
  `descricao` TEXT DEFAULT NULL,
  `ordem` INT(11) DEFAULT 0,
  `ativo` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `codigo` (`codigo`),
  KEY `idx_codigo` (`codigo`),
  KEY `idx_ativo` (`ativo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inserir motivos padrão
INSERT INTO `motivo_matricula` (`id`, `codigo`, `nome`, `descricao`, `ordem`, `ativo`) VALUES
(1, 'nova', 'Nova', 'Primeira matrícula do aluno na modalidade', 1, 1),
(2, 'renovacao', 'Renovação', 'Renovação do mesmo plano', 2, 1),
(3, 'upgrade', 'Upgrade', 'Mudança para um plano superior', 3, 1),
(4, 'downgrade', 'Downgrade', 'Mudança para um plano inferior', 4, 1)
ON DUPLICATE KEY UPDATE `nome` = VALUES(`nome`);

-- ============================================================================
-- 4. ADICIONAR COLUNA motivo_id NA TABELA matriculas
-- ============================================================================
ALTER TABLE `matriculas` 
ADD COLUMN IF NOT EXISTS `motivo_id` INT(11) DEFAULT NULL 
COMMENT 'FK para motivo_matricula' AFTER `status_id`;

-- Adicionar FK para motivo_matricula (se não existir)
-- Primeiro verificamos se a constraint já existe
SET @fk_exists = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS 
    WHERE CONSTRAINT_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'matriculas' 
    AND CONSTRAINT_NAME = 'fk_matriculas_motivo'
);

-- Se não existir, criamos
SET @sql = IF(@fk_exists = 0, 
    'ALTER TABLE `matriculas` ADD CONSTRAINT `fk_matriculas_motivo` FOREIGN KEY (`motivo_id`) REFERENCES `motivo_matricula` (`id`)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Criar índice para motivo_id
CREATE INDEX IF NOT EXISTS `idx_motivo_id` ON `matriculas` (`motivo_id`);

-- ============================================================================
-- 5. ADICIONAR COLUNA aluno_id NA TABELA matriculas
-- ============================================================================
ALTER TABLE `matriculas` 
ADD COLUMN IF NOT EXISTS `aluno_id` INT(11) DEFAULT NULL 
COMMENT 'FK para alunos (perfil do aluno)' AFTER `usuario_id`;

-- Criar índice para aluno_id
CREATE INDEX IF NOT EXISTS `idx_aluno_id` ON `matriculas` (`aluno_id`);

-- ============================================================================
-- 6. ADICIONAR COLUNA aluno_id NA TABELA pagamentos_plano
-- ============================================================================
ALTER TABLE `pagamentos_plano` 
ADD COLUMN IF NOT EXISTS `aluno_id` INT(11) DEFAULT NULL 
COMMENT 'FK para alunos' AFTER `usuario_id`;

-- Criar índice para aluno_id em pagamentos_plano
CREATE INDEX IF NOT EXISTS `idx_pagamentos_aluno_id` ON `pagamentos_plano` (`aluno_id`);

-- ============================================================================
-- 6.1 ADICIONAR COLUNA aluno_id NA TABELA checkins
-- ============================================================================
ALTER TABLE `checkins` 
ADD COLUMN IF NOT EXISTS `aluno_id` INT(11) DEFAULT NULL 
COMMENT 'FK para alunos' AFTER `usuario_id`;

-- Criar índice para aluno_id em checkins
CREATE INDEX IF NOT EXISTS `idx_checkins_aluno_id` ON `checkins` (`aluno_id`);

-- ============================================================================
-- 6.2 ADICIONAR COLUNA usuario_id NA TABELA professores
-- ============================================================================
ALTER TABLE `professores` 
ADD COLUMN IF NOT EXISTS `usuario_id` INT(11) DEFAULT NULL 
COMMENT 'FK para usuarios (vinculo com conta de login)' AFTER `id`;

-- Criar índice para usuario_id em professores
CREATE INDEX IF NOT EXISTS `idx_professores_usuario_id` ON `professores` (`usuario_id`);

-- ============================================================================
-- 7. POPULAR DADOS DE MIGRAÇÃO
-- ============================================================================

-- 7.1 Popular status_id baseado no ENUM status (se ainda não populado)
UPDATE `matriculas` m
SET m.`status_id` = (
    SELECT sm.`id` FROM `status_matricula` sm WHERE sm.`codigo` = m.`status`
)
WHERE m.`status_id` IS NULL AND m.`status` IS NOT NULL;

-- 7.2 Popular motivo_id baseado no ENUM motivo
UPDATE `matriculas` m
SET m.`motivo_id` = (
    SELECT mm.`id` FROM `motivo_matricula` mm WHERE mm.`codigo` = m.`motivo`
)
WHERE m.`motivo_id` IS NULL AND m.`motivo` IS NOT NULL;

-- 7.3 Criar registros na tabela alunos para usuários que são alunos mas não têm registro
INSERT INTO `alunos` (`usuario_id`, `nome`, `telefone`, `cpf`, `cep`, `logradouro`, `numero`, `complemento`, `bairro`, `cidade`, `estado`, `ativo`)
SELECT 
    u.`id`,
    u.`nome`,
    u.`telefone`,
    u.`cpf`,
    u.`cep`,
    u.`logradouro`,
    u.`numero`,
    u.`complemento`,
    u.`bairro`,
    u.`cidade`,
    u.`estado`,
    u.`ativo`
FROM `usuarios` u
INNER JOIN `tenant_usuario_papel` tup ON tup.`usuario_id` = u.`id` AND tup.`papel_id` = 1
WHERE NOT EXISTS (
    SELECT 1 FROM `alunos` a WHERE a.`usuario_id` = u.`id`
);

-- 7.4 Popular aluno_id nas matriculas baseado no usuario_id
UPDATE `matriculas` m
SET m.`aluno_id` = (
    SELECT a.`id` FROM `alunos` a WHERE a.`usuario_id` = m.`usuario_id` LIMIT 1
)
WHERE m.`aluno_id` IS NULL;

-- 7.5 Popular aluno_id nos pagamentos_plano baseado no usuario_id
UPDATE `pagamentos_plano` pp
SET pp.`aluno_id` = (
    SELECT a.`id` FROM `alunos` a WHERE a.`usuario_id` = pp.`usuario_id` LIMIT 1
)
WHERE pp.`aluno_id` IS NULL;

-- 7.6 Popular aluno_id nos checkins baseado no usuario_id
UPDATE `checkins` c
SET c.`aluno_id` = (
    SELECT a.`id` FROM `alunos` a WHERE a.`usuario_id` = c.`usuario_id` LIMIT 1
)
WHERE c.`aluno_id` IS NULL;

-- 7.7 Criar usuarios para professores que não têm e vincular
-- Primeiro, criar usuarios para professores existentes que ainda não têm usuario_id
INSERT INTO `usuarios` (`nome`, `email`, `telefone`, `cpf`, `ativo`, `role_id`, `senha_hash`)
SELECT 
    p.`nome`,
    p.`email`,
    p.`telefone`,
    p.`cpf`,
    p.`ativo`,
    2, -- role_id = 2 (admin/professor)
    '$2y$10$gHLUPI9VrE60CMi.TTj4cujkftMVWZe2RLC9JZCCGYHwCv73iaGmW' -- senha padrão temporária
FROM `professores` p
WHERE p.`email` IS NOT NULL 
AND NOT EXISTS (SELECT 1 FROM `usuarios` u WHERE u.`email` = p.`email`);

-- 7.8 Popular usuario_id nos professores baseado no email
UPDATE `professores` p
SET p.`usuario_id` = (
    SELECT u.`id` FROM `usuarios` u WHERE u.`email` = p.`email` LIMIT 1
)
WHERE p.`usuario_id` IS NULL AND p.`email` IS NOT NULL;

-- 7.9 Criar registros em tenant_usuario_papel para professores (papel_id = 2)
INSERT INTO `tenant_usuario_papel` (`tenant_id`, `usuario_id`, `papel_id`, `ativo`)
SELECT DISTINCT
    p.`tenant_id`,
    p.`usuario_id`,
    2, -- papel_id = 2 (professor)
    1
FROM `professores` p
WHERE p.`usuario_id` IS NOT NULL
AND NOT EXISTS (
    SELECT 1 FROM `tenant_usuario_papel` tup 
    WHERE tup.`tenant_id` = p.`tenant_id` 
    AND tup.`usuario_id` = p.`usuario_id`
    AND tup.`papel_id` = 2
);

-- 7.10 Migrar foto_caminho de usuarios para alunos (se existir)
UPDATE `alunos` a
INNER JOIN `usuarios` u ON u.`id` = a.`usuario_id`
SET a.`foto_caminho` = REPLACE(u.`foto_caminho`, 'usuario_', 'aluno_')
WHERE u.`foto_caminho` IS NOT NULL 
AND a.`foto_caminho` IS NULL;

-- ============================================================================
-- 8. CRIAR FK PARA status_id NA TABELA matriculas (se não existir)
-- ============================================================================
SET @fk_status_exists = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS 
    WHERE CONSTRAINT_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'matriculas' 
    AND CONSTRAINT_NAME = 'fk_matriculas_status'
);

SET @sql_status = IF(@fk_status_exists = 0, 
    'ALTER TABLE `matriculas` ADD CONSTRAINT `fk_matriculas_status` FOREIGN KEY (`status_id`) REFERENCES `status_matricula` (`id`)',
    'SELECT 1'
);
PREPARE stmt_status FROM @sql_status;
EXECUTE stmt_status;
DEALLOCATE PREPARE stmt_status;

-- ============================================================================
-- 9. VERIFICAÇÕES FINAIS
-- ============================================================================

-- Verificar se todos os status_id foram populados
SELECT 'VERIFICAÇÃO: Matrículas sem status_id' AS verificacao, COUNT(*) AS total
FROM `matriculas` WHERE `status_id` IS NULL;

-- Verificar se todos os motivo_id foram populados
SELECT 'VERIFICAÇÃO: Matrículas sem motivo_id' AS verificacao, COUNT(*) AS total
FROM `matriculas` WHERE `motivo_id` IS NULL;

-- Verificar se todos os aluno_id foram populados
SELECT 'VERIFICAÇÃO: Matrículas sem aluno_id' AS verificacao, COUNT(*) AS total
FROM `matriculas` WHERE `aluno_id` IS NULL;

-- Verificar se todos os aluno_id foram populados em checkins
SELECT 'VERIFICAÇÃO: Checkins sem aluno_id' AS verificacao, COUNT(*) AS total
FROM `checkins` WHERE `aluno_id` IS NULL;

-- Verificar se todos os usuario_id foram populados em professores
SELECT 'VERIFICAÇÃO: Professores sem usuario_id' AS verificacao, COUNT(*) AS total
FROM `professores` WHERE `usuario_id` IS NULL;

-- Verificar registros de alunos criados
SELECT 'VERIFICAÇÃO: Total de alunos na tabela' AS verificacao, COUNT(*) AS total
FROM `alunos`;

-- Verificar status_matricula
SELECT 'STATUS MATRICULA:' AS info, id, codigo, nome FROM `status_matricula` ORDER BY id;

-- Verificar motivo_matricula
SELECT 'MOTIVO MATRICULA:' AS info, id, codigo, nome FROM `motivo_matricula` ORDER BY id;

COMMIT;

-- ============================================================================
-- NOTAS IMPORTANTES:
-- ============================================================================
-- 1. Após confirmar que a migração funcionou corretamente, você pode 
--    eventualmente remover as colunas ENUM antigas:
--    
--    ALTER TABLE `matriculas` DROP COLUMN `status`;
--    ALTER TABLE `matriculas` DROP COLUMN `motivo`;
--
-- 2. Certifique-se de que os controladores PHP estão usando status_id e 
--    motivo_id em vez dos ENUMs.
--
-- 3. A foto do aluno agora deve ser salva/lida da tabela alunos.foto_caminho
--    e não mais de usuarios.foto_caminho
-- ============================================================================
