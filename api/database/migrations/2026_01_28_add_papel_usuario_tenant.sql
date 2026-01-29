-- Migration: Criar sistema de papéis por tenant
-- Data: 2026-01-28
-- Descrição: Permite que um usuário tenha papéis diferentes por tenant (aluno, professor, admin)
--            Um usuário pode ter múltiplos papéis no mesmo tenant

-- ==============================================
-- 1. CRIAR TABELA DE PAPÉIS
-- ==============================================

CREATE TABLE IF NOT EXISTS papeis (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(50) NOT NULL UNIQUE,
    descricao VARCHAR(255) NULL,
    nivel INT NOT NULL DEFAULT 0 COMMENT 'Nível hierárquico: maior = mais permissões',
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_nome (nome),
    INDEX idx_nivel (nivel)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Papéis que um usuário pode ter em cada tenant';

-- Inserir papéis padrão
INSERT INTO papeis (id, nome, descricao, nivel) VALUES
(1, 'aluno', 'Aluno que faz check-in nas aulas', 10),
(2, 'professor', 'Professor que confirma presença dos alunos', 50),
(3, 'admin', 'Administrador do tenant com acesso total', 100)
ON DUPLICATE KEY UPDATE nome = VALUES(nome);

-- ==============================================
-- 2. CRIAR TABELA tenant_usuario_papel
-- ==============================================

CREATE TABLE IF NOT EXISTS tenant_usuario_papel (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    usuario_id INT NOT NULL,
    papel_id INT NOT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Um usuário só pode ter cada papel uma vez por tenant
    UNIQUE KEY uk_tenant_usuario_papel (tenant_id, usuario_id, papel_id),
    
    -- Índices para consultas frequentes
    INDEX idx_tenant_usuario (tenant_id, usuario_id),
    INDEX idx_usuario_papel (usuario_id, papel_id),
    INDEX idx_tenant_papel (tenant_id, papel_id),
    
    -- Foreign Keys
    CONSTRAINT fk_tup_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    CONSTRAINT fk_tup_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_tup_papel FOREIGN KEY (papel_id) REFERENCES papeis(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Papéis de cada usuário em cada tenant. Um usuário pode ter múltiplos papéis no mesmo tenant.';

-- ==============================================
-- 3. MIGRAR DADOS EXISTENTES
-- ==============================================

-- Todos os usuários em usuario_tenant recebem papel de aluno
INSERT IGNORE INTO tenant_usuario_papel (tenant_id, usuario_id, papel_id, ativo)
SELECT ut.tenant_id, ut.usuario_id, 1, 1 -- papel_id = 1 (aluno)
FROM usuario_tenant ut
WHERE ut.status = 'ativo';

-- Usuários com role_id = 2 (admin) recebem papel de admin
INSERT IGNORE INTO tenant_usuario_papel (tenant_id, usuario_id, papel_id, ativo)
SELECT ut.tenant_id, ut.usuario_id, 3, 1 -- papel_id = 3 (admin)
FROM usuario_tenant ut
INNER JOIN usuarios u ON u.id = ut.usuario_id
WHERE u.role_id = 2 AND ut.status = 'ativo';

-- Usuários com role_id = 3 (super_admin) recebem papel de admin nos tenants vinculados
INSERT IGNORE INTO tenant_usuario_papel (tenant_id, usuario_id, papel_id, ativo)
SELECT ut.tenant_id, ut.usuario_id, 3, 1 -- papel_id = 3 (admin)
FROM usuario_tenant ut
INNER JOIN usuarios u ON u.id = ut.usuario_id
WHERE u.role_id = 3 AND ut.status = 'ativo';

-- Professores existentes (da tabela professores) recebem papel de professor
INSERT IGNORE INTO tenant_usuario_papel (tenant_id, usuario_id, papel_id, ativo)
SELECT p.tenant_id, u.id, 2, 1 -- papel_id = 2 (professor)
FROM professores p
INNER JOIN usuarios u ON LOWER(u.email) = LOWER(p.email)
WHERE p.ativo = 1;

-- ==============================================
-- 4. REMOVER COLUNA papel_id DE usuario_tenant (se existir)
-- ==============================================

-- Verificar se coluna existe e remover
SET @coluna_existe = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'usuario_tenant' 
    AND COLUMN_NAME = 'papel_id'
);

SET @sql_drop_fk = IF(@coluna_existe > 0,
    'ALTER TABLE usuario_tenant DROP FOREIGN KEY fk_usuario_tenant_papel',
    'SELECT "FK não existe"'
);

-- Ignorar erro se FK não existir
SET @sql_drop_column = IF(@coluna_existe > 0,
    'ALTER TABLE usuario_tenant DROP COLUMN papel_id',
    'SELECT "Coluna papel_id não existe"'
);

-- Remover FK primeiro (pode falhar se não existir, então ignoramos)
-- PREPARE stmt FROM @sql_drop_fk;
-- EXECUTE stmt;
-- DEALLOCATE PREPARE stmt;

-- Não vamos remover a coluna agora para não quebrar nada
-- A coluna será removida em uma migration futura após validar que tudo funciona

-- ==============================================
-- 5. VERIFICAÇÃO
-- ==============================================

-- Após executar, verificar distribuição:
-- SELECT p.nome as papel, COUNT(*) as total 
-- FROM tenant_usuario_papel tup 
-- INNER JOIN papeis p ON p.id = tup.papel_id 
-- WHERE tup.ativo = 1
-- GROUP BY tup.papel_id;

-- Verificar usuários com múltiplos papéis:
-- SELECT u.nome, u.email, t.nome as tenant, GROUP_CONCAT(p.nome) as papeis
-- FROM tenant_usuario_papel tup
-- INNER JOIN usuarios u ON u.id = tup.usuario_id
-- INNER JOIN tenants t ON t.id = tup.tenant_id
-- INNER JOIN papeis p ON p.id = tup.papel_id
-- WHERE tup.ativo = 1
-- GROUP BY tup.tenant_id, tup.usuario_id
-- HAVING COUNT(*) > 1;
