-- Antecipação automática Asaas (D+2): toggle + taxas mensais editáveis.
-- Padrão comercial Asaas: 1,15% ao mês à vista e 1,6% ao mês no parcelado.
-- Produção: aplicar manualmente no MySQL compartilhado (não usar php artisan migrate).

SET @dbname = DATABASE();

SET @col_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'tenant_payment_credentials'
      AND COLUMN_NAME = 'enable_sobretaxa_antecipacao'
);
SET @sql = IF(
    @col_exists = 0,
    "ALTER TABLE tenant_payment_credentials
        ADD COLUMN enable_sobretaxa_antecipacao TINYINT(1) NOT NULL DEFAULT 0
            COMMENT 'Repassa taxa de antecipação automática Asaas (D+2) no cartão' AFTER enable_credit_card,
        ADD COLUMN sobretaxa_antecipacao_avista_percentual DECIMAL(5,2) NOT NULL DEFAULT 1.15
            COMMENT 'Percentual ao mês à vista (Asaas: 1,15%)' AFTER enable_sobretaxa_antecipacao,
        ADD COLUMN sobretaxa_antecipacao_parcelada_percentual DECIMAL(5,2) NOT NULL DEFAULT 1.60
            COMMENT 'Percentual ao mês no parcelado (Asaas: 1,6%)' AFTER sobretaxa_antecipacao_avista_percentual",
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'pagamentos_plano'
      AND COLUMN_NAME = 'tarifa_cartao'
);
SET @sql = IF(
    @col_exists = 0,
    "ALTER TABLE pagamentos_plano
        ADD COLUMN tarifa_cartao DECIMAL(10,2) NULL DEFAULT 0.00
            COMMENT 'Taxa de cartão do Asaas embutida (não misturar com desconto_provedor)',
        ADD COLUMN sobretaxa_antecipacao DECIMAL(10,2) NULL DEFAULT 0.00
            COMMENT 'Acréscimo complementar da antecipação automática'",
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
