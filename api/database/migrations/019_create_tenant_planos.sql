-- Tabela de contratos/associações entre Tenants e Planos
-- Mantém histórico de planos do tenant com datas e formas de pagamento

CREATE TABLE IF NOT EXISTS tenant_planos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    plano_id INT NOT NULL,
    
    -- Período do contrato
    data_inicio DATE NOT NULL,
    data_vencimento DATE NOT NULL,
    
    -- Forma de pagamento
    forma_pagamento ENUM('cartao', 'pix', 'operadora') NOT NULL DEFAULT 'pix',
    
    -- Status do contrato
    status ENUM('ativo', 'inativo', 'cancelado') NOT NULL DEFAULT 'ativo',
    
    -- Observações
    observacoes TEXT NULL,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Chaves estrangeiras
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (plano_id) REFERENCES planos(id) ON DELETE RESTRICT,
    
    -- Índices para performance
    INDEX idx_tenant_status (tenant_id, status),
    INDEX idx_status (status),
    INDEX idx_datas (data_inicio, data_vencimento),
    
    -- Constraint: Apenas um contrato ativo por tenant
    UNIQUE KEY uk_tenant_ativo (tenant_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Comentário na tabela
ALTER TABLE tenant_planos COMMENT = 'Contratos de planos dos tenants com histórico completo';
