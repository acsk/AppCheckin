-- Migration: Criar tabela para armazenar dados PIX (QR, ticket, expiração)
CREATE TABLE IF NOT EXISTS pagamentos_pix (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    matricula_id INT NOT NULL,
    payment_id VARCHAR(50) NOT NULL,
    ticket_url VARCHAR(500) NOT NULL,
    qr_code TEXT NULL,
    qr_code_base64 LONGTEXT NULL,
    expires_at DATETIME NULL,
    status VARCHAR(50) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tenant_matricula (tenant_id, matricula_id),
    INDEX idx_payment_id (payment_id),
    INDEX idx_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
