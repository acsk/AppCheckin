-- Migration: Criar tabela de pagamentos Mercado Pago
-- Data: 2026-02-06

CREATE TABLE IF NOT EXISTS pagamentos_mercadopago (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    matricula_id INT NOT NULL,
    aluno_id INT NULL,
    usuario_id INT NULL,
    
    -- Dados do Mercado Pago
    payment_id VARCHAR(100) NOT NULL UNIQUE,
    external_reference VARCHAR(100) NULL,
    preference_id VARCHAR(100) NULL,
    
    -- Status do pagamento
    status VARCHAR(50) NOT NULL, -- approved, pending, rejected, cancelled, refunded
    status_detail VARCHAR(100) NULL,
    
    -- Valores
    transaction_amount DECIMAL(10,2) NOT NULL,
    
    -- Método de pagamento
    payment_method_id VARCHAR(50) NULL, -- pix, credit_card, debit_card, etc
    payment_type_id VARCHAR(50) NULL,
    installments INT DEFAULT 1,
    
    -- Datas
    date_approved DATETIME NULL,
    date_created DATETIME NOT NULL,
    
    -- Dados do pagador
    payer_email VARCHAR(255) NULL,
    payer_identification_type VARCHAR(20) NULL,
    payer_identification_number VARCHAR(50) NULL,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Foreign keys
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (matricula_id) REFERENCES matriculas(id) ON DELETE CASCADE,
    FOREIGN KEY (aluno_id) REFERENCES alunos(id) ON DELETE SET NULL,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    
    -- Índices
    INDEX idx_payment_id (payment_id),
    INDEX idx_external_reference (external_reference),
    INDEX idx_matricula (matricula_id),
    INDEX idx_status (status),
    INDEX idx_tenant (tenant_id),
    INDEX idx_date_created (date_created)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Comentários
ALTER TABLE pagamentos_mercadopago COMMENT = 'Pagamentos processados via Mercado Pago';
