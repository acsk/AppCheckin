-- Migration 013: Criar tabelas auxiliares para formas de pagamento e status de contas
-- Data: 2025-11-24

-- Tabela de formas de pagamento
CREATE TABLE IF NOT EXISTS formas_pagamento (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(50) NOT NULL,
    percentual_desconto DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT 'Percentual que fica com operadora (ex: 3.50 para 3.5%)',
    ativo BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_forma_pagamento_nome (nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de status de contas
CREATE TABLE IF NOT EXISTS status_conta (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(20) NOT NULL,
    cor VARCHAR(20) NOT NULL COMMENT 'Cor para exibição no frontend',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_status_conta_nome (nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inserir formas de pagamento padrão
INSERT INTO formas_pagamento (nome, percentual_desconto, ativo) VALUES
('Dinheiro', 0.00, TRUE),
('Pix', 0.00, TRUE),
('Débito', 2.50, TRUE),
('Crédito à vista', 3.50, TRUE),
('Crédito parcelado 2x', 4.50, TRUE),
('Crédito parcelado 3x', 5.00, TRUE),
('Transferência bancária', 0.00, TRUE),
('Boleto', 2.00, TRUE);

-- Inserir status padrão
INSERT INTO status_conta (nome, cor) VALUES
('pendente', 'warning'),
('pago', 'success'),
('vencido', 'danger'),
('cancelado', 'medium');

-- Adicionar colunas na tabela contas_receber
ALTER TABLE contas_receber 
    ADD COLUMN forma_pagamento_id INT NULL AFTER status,
    ADD COLUMN valor_liquido DECIMAL(10,2) NULL COMMENT 'Valor após desconto da operadora',
    ADD COLUMN valor_desconto DECIMAL(10,2) NULL COMMENT 'Valor do desconto da operadora',
    ADD FOREIGN KEY fk_conta_forma_pagamento (forma_pagamento_id) REFERENCES formas_pagamento(id) ON DELETE SET NULL;

-- Migrar status antigo para novo formato (criar função temporária)
UPDATE contas_receber SET status = 'pendente' WHERE status = 'pendente';
UPDATE contas_receber SET status = 'pago' WHERE status = 'pago';
UPDATE contas_receber SET status = 'vencido' WHERE status = 'vencido';
UPDATE contas_receber SET status = 'cancelado' WHERE status = 'cancelado';

-- Adicionar índices para performance
CREATE INDEX idx_forma_pagamento_ativo ON formas_pagamento(ativo);
CREATE INDEX idx_conta_forma_pagamento ON contas_receber(forma_pagamento_id);
