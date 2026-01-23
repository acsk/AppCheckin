-- ================================================
-- Migration: Criar tabela de auditoria de emails
-- Data: 2026-01-23
-- Descrição: Registra todos os emails enviados pelo sistema
-- ================================================

CREATE TABLE IF NOT EXISTS email_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NULL COMMENT 'Tenant associado (se aplicável)',
    usuario_id INT NULL COMMENT 'Usuário destinatário (se aplicável)',
    
    -- Dados do email
    to_email VARCHAR(255) NOT NULL COMMENT 'Email de destino',
    to_name VARCHAR(255) NULL COMMENT 'Nome do destinatário',
    from_email VARCHAR(255) NOT NULL COMMENT 'Email remetente',
    from_name VARCHAR(255) NULL COMMENT 'Nome do remetente',
    subject VARCHAR(500) NOT NULL COMMENT 'Assunto do email',
    
    -- Tipo e conteúdo
    email_type VARCHAR(50) NOT NULL DEFAULT 'generic' COMMENT 'Tipo: password_recovery, welcome, notification, etc',
    body_preview TEXT NULL COMMENT 'Preview do corpo (primeiros 500 caracteres)',
    
    -- Status do envio
    status ENUM('pending', 'sent', 'failed', 'bounced') NOT NULL DEFAULT 'pending' COMMENT 'Status do envio',
    error_message TEXT NULL COMMENT 'Mensagem de erro se falhou',
    
    -- Metadados
    provider VARCHAR(50) NOT NULL DEFAULT 'ses' COMMENT 'Provedor: ses, smtp, sendgrid',
    ip_address VARCHAR(45) NULL COMMENT 'IP de origem da requisição',
    user_agent VARCHAR(500) NULL COMMENT 'User agent se disponível',
    
    -- Rastreamento
    message_id VARCHAR(255) NULL COMMENT 'ID da mensagem no provedor',
    sent_at DATETIME NULL COMMENT 'Data/hora do envio efetivo',
    opened_at DATETIME NULL COMMENT 'Data/hora da abertura (se rastreado)',
    clicked_at DATETIME NULL COMMENT 'Data/hora do clique (se rastreado)',
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Índices
    INDEX idx_email_logs_tenant (tenant_id),
    INDEX idx_email_logs_usuario (usuario_id),
    INDEX idx_email_logs_to_email (to_email),
    INDEX idx_email_logs_status (status),
    INDEX idx_email_logs_type (email_type),
    INDEX idx_email_logs_created (created_at),
    INDEX idx_email_logs_provider (provider),
    
    -- Foreign keys (opcionais - tenant e usuario podem não existir)
    CONSTRAINT fk_email_logs_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE SET NULL,
    CONSTRAINT fk_email_logs_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Auditoria de emails enviados';

-- ================================================
-- Estatísticas úteis (views opcionais)
-- ================================================

-- View para estatísticas diárias de emails
CREATE OR REPLACE VIEW vw_email_stats_daily AS
SELECT 
    DATE(created_at) as data,
    tenant_id,
    email_type,
    status,
    COUNT(*) as total
FROM email_logs
GROUP BY DATE(created_at), tenant_id, email_type, status;

-- View para taxa de sucesso por tenant
CREATE OR REPLACE VIEW vw_email_success_rate AS
SELECT 
    tenant_id,
    COUNT(*) as total_emails,
    SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as enviados,
    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as falhas,
    ROUND(SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) * 100.0 / COUNT(*), 2) as taxa_sucesso
FROM email_logs
GROUP BY tenant_id;
