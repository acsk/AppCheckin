-- =====================================================
-- CRIAR TABELA tenant_professor
-- =====================================================
-- Criado em: 2026-02-03
-- 
-- OBJETIVO:
-- Criar vínculo direto entre professor e tenant, similar a usuario_tenant,
-- permitindo:
-- - Cadastro global de professor (tabela professores)
-- - Associação com múltiplos tenants (tenant_professor)
-- - Status independente por tenant
-- - Informações específicas por tenant (plano, datas)
--
-- ARQUITETURA:
-- - professores: Tabela global (sem tenant_id)
-- - tenant_professor: Vínculo N:M professor↔tenant
-- - tenant_usuario_papel: Permissões/papéis (papel_id=2 para professor)
--
-- FLUXO:
-- 1. Criar professor global (busca por CPF)
-- 2. Associar ao tenant via tenant_professor
-- 3. Criar papel em tenant_usuario_papel (papel_id=2)
-- =====================================================

USE appcheckin;

-- Criar tabela tenant_professor (similar a usuario_tenant)
CREATE TABLE IF NOT EXISTS `tenant_professor` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `professor_id` INT NOT NULL COMMENT 'FK para professores.id',
    `tenant_id` INT NOT NULL COMMENT 'FK para tenants.id',
    `cpf` VARCHAR(14) NULL COMMENT 'CPF do professor (redundância para busca rápida)',
    `email` VARCHAR(255) NULL COMMENT 'Email do professor no tenant (permite email diferente por tenant)',
    `plano_id` INT NULL COMMENT 'FK para planos.id (plano específico do professor no tenant)',
    `status` ENUM('ativo', 'inativo', 'suspenso', 'cancelado') DEFAULT 'ativo' COMMENT 'Status do professor no tenant',
    `data_inicio` DATE NOT NULL DEFAULT (CURDATE()) COMMENT 'Data de início do vínculo',
    `data_fim` DATE NULL COMMENT 'Data de término do vínculo (NULL se ativo)',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_tenant_professor` (`tenant_id`, `professor_id`),
    UNIQUE KEY `unique_tenant_email` (`tenant_id`, `email`) COMMENT 'Email único por tenant',
    KEY `cpf` (`cpf`) COMMENT 'Índice para busca por CPF',
    KEY `idx_professores_tenant` (`tenant_id`),
    KEY `idx_professores_ativo` (`status`),
    KEY `idx_professores_usuario_id` (`professor_id`),
    
    CONSTRAINT `fk_tenant_professor_professor` 
        FOREIGN KEY (`professor_id`) REFERENCES `professores` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_tenant_professor_tenant` 
        FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_tenant_professor_plano` 
        FOREIGN KEY (`plano_id`) REFERENCES `planos` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Vínculo entre professores e tenants com status e plano específico';

-- Migrar dados existentes de tenant_usuario_papel para tenant_professor
-- Buscar professores que já têm papel_id=2 em tenant_usuario_papel
INSERT INTO tenant_professor (professor_id, tenant_id, cpf, email, status, data_inicio)
SELECT DISTINCT
    p.id as professor_id,
    tup.tenant_id,
    p.cpf,
    p.email,
    'ativo' as status,
    COALESCE(ut.data_inicio, CURDATE()) as data_inicio
FROM professores p
INNER JOIN tenant_usuario_papel tup 
    ON tup.usuario_id = p.usuario_id 
    AND tup.papel_id = 2 
    AND tup.ativo = 1
LEFT JOIN usuario_tenant ut 
    ON ut.usuario_id = p.usuario_id 
    AND ut.tenant_id = tup.tenant_id
WHERE NOT EXISTS (
    SELECT 1 FROM tenant_professor tp 
    WHERE tp.professor_id = p.id 
    AND tp.tenant_id = tup.tenant_id
);

-- Verificações
SELECT 'tenant_professor criada com sucesso!' as status;

SELECT 
    COUNT(*) as total_vinculos,
    COUNT(DISTINCT professor_id) as professores_unicos,
    COUNT(DISTINCT tenant_id) as tenants_distintos
FROM tenant_professor;

SELECT 
    tp.id,
    tp.professor_id,
    p.nome as professor_nome,
    tp.tenant_id,
    t.nome as tenant_nome,
    tp.status,
    tp.data_inicio
FROM tenant_professor tp
INNER JOIN professores p ON p.id = tp.professor_id
INNER JOIN tenants t ON t.id = tp.tenant_id
ORDER BY tp.id;
