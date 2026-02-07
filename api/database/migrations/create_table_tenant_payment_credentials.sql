-- Migration: Criar tabela de credenciais de pagamento por tenant
-- Data: 2026-02-07

CREATE TABLE IF NOT EXISTS tenant_payment_credentials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL UNIQUE,
    
    -- Provider de pagamento
    provider VARCHAR(50) NOT NULL DEFAULT 'mercadopago',
    
    -- Ambiente ativo (sandbox ou production)
    environment VARCHAR(20) NOT NULL DEFAULT 'sandbox',
    
    -- Credenciais de TESTE (criptografadas)
    access_token_test TEXT NULL,
    public_key_test VARCHAR(255) NULL,
    
    -- Credenciais de PRODUÇÃO (criptografadas)
    access_token_prod TEXT NULL,
    public_key_prod VARCHAR(255) NULL,
    
    -- Webhook
    webhook_secret TEXT NULL,
    
    -- Status
    is_active BOOLEAN DEFAULT TRUE,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Foreign keys
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    
    -- Índices
    INDEX idx_tenant (tenant_id),
    INDEX idx_provider (provider),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Comentários
ALTER TABLE tenant_payment_credentials COMMENT = 'Credenciais de pagamento por tenant (Mercado Pago, etc)';
