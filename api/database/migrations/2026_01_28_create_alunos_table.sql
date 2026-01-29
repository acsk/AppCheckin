-- Migration: Criar tabela alunos separada de usuarios
-- Data: 2026-01-28
-- Descrição: Separa dados de perfil do aluno (nome, telefone, cpf, endereço, foto)
--            dos dados de autenticação (email, senha_hash, tokens) em usuarios

-- ==============================================
-- 1. CRIAR TABELA ALUNOS
-- ==============================================

CREATE TABLE IF NOT EXISTS alunos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    nome VARCHAR(255) NOT NULL,
    telefone VARCHAR(20) NULL,
    cpf VARCHAR(14) NULL,
    
    -- Endereço
    cep VARCHAR(10) NULL,
    logradouro VARCHAR(255) NULL,
    numero VARCHAR(20) NULL,
    complemento VARCHAR(100) NULL,
    bairro VARCHAR(100) NULL,
    cidade VARCHAR(100) NULL,
    estado VARCHAR(2) NULL,
    
    -- Foto
    foto_url VARCHAR(500) NULL,
    foto_base64 LONGTEXT NULL,
    
    -- Controle
    ativo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- FK
    UNIQUE KEY uk_aluno_usuario (usuario_id),
    CONSTRAINT fk_aluno_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==============================================
-- 2. MIGRAR DADOS DE USUARIOS PARA ALUNOS
-- ==============================================

-- Inserir alunos a partir de usuarios existentes que são REALMENTE alunos (role_id = 1)
-- E que não são professores
INSERT INTO alunos (usuario_id, nome, telefone, cpf, cep, logradouro, numero, complemento, bairro, cidade, estado, foto_base64, ativo, created_at, updated_at)
SELECT 
    u.id,
    u.nome,
    u.telefone,
    u.cpf,
    u.cep,
    u.logradouro,
    u.numero,
    u.complemento,
    u.bairro,
    u.cidade,
    u.estado,
    u.foto_base64,
    u.ativo,
    u.created_at,
    u.updated_at
FROM usuarios u
WHERE u.role_id = 1  -- Apenas alunos (não admin, não superadmin)
  AND NOT EXISTS (SELECT 1 FROM alunos a WHERE a.usuario_id = u.id)
  AND NOT EXISTS (SELECT 1 FROM professores p WHERE p.usuario_id = u.id);

-- ==============================================
-- 3. ADICIONAR PAPEL DE ALUNO (papel_id=1) PARA QUEM NÃO TEM
-- ==============================================

-- Para cada aluno criado, garantir que tenha papel de aluno em algum tenant
-- (usando o tenant do usuario_tenant se existir)
INSERT IGNORE INTO tenant_usuario_papel (tenant_id, usuario_id, papel_id, ativo)
SELECT DISTINCT ut.tenant_id, a.usuario_id, 1, 1
FROM alunos a
INNER JOIN usuario_tenant ut ON ut.usuario_id = a.usuario_id
WHERE NOT EXISTS (
    SELECT 1 FROM tenant_usuario_papel tup 
    WHERE tup.usuario_id = a.usuario_id 
    AND tup.tenant_id = ut.tenant_id 
    AND tup.papel_id = 1
);

-- ==============================================
-- 4. CRIAR ÍNDICES
-- ==============================================

-- Índice para CPF (único global)
SET @idx_cpf = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'alunos' AND INDEX_NAME = 'idx_aluno_cpf'
);
SET @sql = IF(@idx_cpf = 0, 'CREATE INDEX idx_aluno_cpf ON alunos(cpf)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Índice para busca por nome
SET @idx_nome = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'alunos' AND INDEX_NAME = 'idx_aluno_nome'
);
SET @sql = IF(@idx_nome = 0, 'CREATE INDEX idx_aluno_nome ON alunos(nome)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ==============================================
-- NOTA: NÃO remover colunas de usuarios ainda
-- Fazer isso em migration separada após validar
-- ==============================================
