-- =====================================================
-- MIGRATION 037b: Criar Tabelas de Status (Parte 2)
-- Para corrigir o erro da tabela status_matricula
-- =====================================================

-- 2. Tabela de Status de Matrículas
CREATE TABLE IF NOT EXISTS status_matricula (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(50) UNIQUE NOT NULL,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT,
    cor VARCHAR(20) DEFAULT '#6b7280',
    icone VARCHAR(50),
    ordem INT DEFAULT 0,
    permite_checkin BOOLEAN DEFAULT TRUE COMMENT 'Permite fazer check-in com este status',
    ativo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_codigo (codigo),
    INDEX idx_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO status_matricula (codigo, nome, descricao, cor, icone, ordem, permite_checkin) VALUES
('ativa', 'Ativa', 'Matrícula regular e ativa', '#10b981', 'check', 1, TRUE),
('suspensa', 'Suspensa', 'Matrícula temporariamente suspensa', '#f59e0b', 'pause', 2, FALSE),
('cancelada', 'Cancelada', 'Matrícula cancelada', '#ef4444', 'x', 3, FALSE);

-- 4. Tabela de Status de Check-in
CREATE TABLE IF NOT EXISTS status_checkin (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(50) UNIQUE NOT NULL,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT,
    cor VARCHAR(20) DEFAULT '#6b7280',
    icone VARCHAR(50),
    ordem INT DEFAULT 0,
    ativo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_codigo (codigo),
    INDEX idx_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO status_checkin (codigo, nome, descricao, cor, icone, ordem) VALUES
('entrada', 'Entrada', 'Check-in de entrada', '#10b981', 'log-in', 1),
('saida', 'Saída', 'Check-in de saída', '#3b82f6', 'log-out', 2);

-- 5. Tabela de Status de Usuário
CREATE TABLE IF NOT EXISTS status_usuario (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(50) UNIQUE NOT NULL,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT,
    cor VARCHAR(20) DEFAULT '#6b7280',
    icone VARCHAR(50),
    ordem INT DEFAULT 0,
    permite_login BOOLEAN DEFAULT TRUE COMMENT 'Permite login com este status',
    ativo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_codigo (codigo),
    INDEX idx_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO status_usuario (codigo, nome, descricao, cor, icone, ordem, permite_login) VALUES
('ativo', 'Ativo', 'Usuário ativo no sistema', '#10b981', 'user-check', 1, TRUE),
('inativo', 'Inativo', 'Usuário temporariamente inativo', '#f59e0b', 'user-x', 2, FALSE),
('bloqueado', 'Bloqueado', 'Usuário bloqueado', '#ef4444', 'lock', 3, FALSE);

-- Verificar criação
SELECT 'Tabelas criadas:' AS status;
SHOW TABLES LIKE 'status%';
