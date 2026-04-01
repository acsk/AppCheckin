-- ============================================================
-- Migration: Melhoria no sistema de descontos
-- 1. Adiciona valor_original em pagamentos_plano
-- 2. Cria tabela pivot pagamento_desconto_aplicado
-- ============================================================

-- 1. Coluna valor_original: valor cheio ANTES do desconto
ALTER TABLE pagamentos_plano
    ADD COLUMN valor_original DECIMAL(10,2) NULL AFTER valor;

-- Backfill: para registros existentes, valor_original = valor + desconto
UPDATE pagamentos_plano
SET valor_original = valor + COALESCE(desconto, 0)
WHERE valor_original IS NULL;

-- 2. Tabela pivot: vincula cada pagamento aos descontos que foram aplicados
CREATE TABLE IF NOT EXISTS pagamento_desconto_aplicado (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pagamento_plano_id INT NOT NULL,
    matricula_desconto_id INT NOT NULL,
    valor_desconto DECIMAL(10,2) NOT NULL COMMENT 'Quanto este desconto abateu nesta parcela',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (pagamento_plano_id) REFERENCES pagamentos_plano(id) ON DELETE CASCADE,
    FOREIGN KEY (matricula_desconto_id) REFERENCES matricula_descontos(id) ON DELETE CASCADE,
    INDEX idx_pagamento (pagamento_plano_id),
    INDEX idx_desconto (matricula_desconto_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
