-- Tabela para armazenar payloads completos dos webhooks do Mercado Pago
CREATE TABLE IF NOT EXISTS webhook_payloads_mercadopago (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED,
    tipo VARCHAR(50),
    data_id BIGINT UNSIGNED,
    external_reference VARCHAR(255),
    payment_id BIGINT UNSIGNED NULL,
    preapproval_id VARCHAR(255) NULL,
    status VARCHAR(50),
    erro_processamento VARCHAR(500) NULL,
    payload LONGTEXT NOT NULL COMMENT 'Payload completo em JSON',
    resultado_processamento LONGTEXT NULL COMMENT 'Resultado do processamento em JSON',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_tipo (tipo),
    INDEX idx_data_id (data_id),
    INDEX idx_external_reference (external_reference),
    INDEX idx_payment_id (payment_id),
    INDEX idx_preapproval_id (preapproval_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Armazena payloads completos dos webhooks do Mercado Pago para auditoria e debug';
