-- =====================================================
-- MIGRATION 037: Criar Tabelas de Status
-- Padronização: Remover ENUMs e usar tabelas + FK
-- =====================================================

-- 1. Tabela de Status de Contas a Receber
CREATE TABLE IF NOT EXISTS status_conta_receber (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(50) UNIQUE NOT NULL COMMENT 'Código único (ex: pendente, pago)',
    nome VARCHAR(100) NOT NULL COMMENT 'Nome exibido na UI',
    descricao TEXT COMMENT 'Descrição detalhada do status',
    cor VARCHAR(20) DEFAULT '#6b7280' COMMENT 'Cor hexadecimal para UI',
    icone VARCHAR(50) COMMENT 'Nome do ícone (Feather Icons)',
    ordem INT DEFAULT 0 COMMENT 'Ordem de exibição',
    permite_edicao BOOLEAN DEFAULT TRUE COMMENT 'Permite editar a conta neste status',
    permite_cancelamento BOOLEAN DEFAULT TRUE COMMENT 'Permite cancelar a conta',
    ativo BOOLEAN DEFAULT TRUE COMMENT 'Status ativo no sistema',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_codigo (codigo),
    INDEX idx_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inserir status padrões de contas a receber
INSERT INTO status_conta_receber (codigo, nome, descricao, cor, icone, ordem, permite_edicao, permite_cancelamento) VALUES
('pendente', 'Pendente', 'Aguardando pagamento', '#f59e0b', 'clock', 1, TRUE, TRUE),
('pago', 'Pago', 'Pagamento confirmado', '#10b981', 'check-circle', 2, FALSE, FALSE),
('vencido', 'Vencido', 'Pagamento em atraso', '#ef4444', 'alert-circle', 3, TRUE, TRUE),
('cancelado', 'Cancelado', 'Pagamento cancelado', '#6b7280', 'x-circle', 4, FALSE, FALSE);

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

INSERT INTO status_matricula (codigo, nome, descricao, cor, icone, ordem, permite_checkin) VALUES
('ativa', 'Ativa', 'Matrícula regular e ativa', '#10b981', 'check', 1, TRUE),
('suspensa', 'Suspensa', 'Matrícula temporariamente suspensa', '#f59e0b', 'pause', 2, FALSE),
('cancelada', 'Cancelada', 'Matrícula cancelada', '#ef4444', 'x', 3, FALSE);

-- 3. Tabela de Status de Pagamentos
CREATE TABLE IF NOT EXISTS status_pagamento (
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

INSERT INTO status_pagamento (codigo, nome, descricao, cor, icone, ordem) VALUES
('pendente', 'Pendente', 'Aguardando processamento', '#f59e0b', 'clock', 1),
('aprovado', 'Aprovado', 'Pagamento aprovado', '#10b981', 'check-circle', 2),
('recusado', 'Recusado', 'Pagamento recusado', '#ef4444', 'x-circle', 3),
('cancelado', 'Cancelado', 'Pagamento cancelado', '#6b7280', 'slash', 4);

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

INSERT INTO status_checkin (codigo, nome, descricao, cor, icone, ordem) VALUES
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

INSERT INTO status_usuario (codigo, nome, descricao, cor, icone, ordem, permite_login) VALUES
('ativo', 'Ativo', 'Usuário ativo no sistema', '#10b981', 'user-check', 1, TRUE),
('inativo', 'Inativo', 'Usuário temporariamente inativo', '#f59e0b', 'user-x', 2, FALSE),
('bloqueado', 'Bloqueado', 'Usuário bloqueado', '#ef4444', 'lock', 3, FALSE);

-- 6. Tabela de Status de Contrato/Plano de Tenant
CREATE TABLE IF NOT EXISTS status_contrato (
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

INSERT INTO status_contrato (codigo, nome, descricao, cor, icone, ordem) VALUES
('ativo', 'Ativo', 'Contrato ativo e vigente', '#10b981', 'check-circle', 1),
('suspenso', 'Suspenso', 'Contrato temporariamente suspenso', '#f59e0b', 'pause-circle', 2),
('cancelado', 'Cancelado', 'Contrato cancelado', '#ef4444', 'x-circle', 3),
('expirado', 'Expirado', 'Contrato expirado', '#6b7280', 'clock', 4);
