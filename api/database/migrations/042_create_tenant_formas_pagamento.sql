-- Cria sistema de formas de pagamento personalizadas por tenant
-- Migration 042: Permite cada tenant configurar taxas, parcelamento e condições específicas

CREATE TABLE IF NOT EXISTS tenant_formas_pagamento (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    forma_pagamento_id INT NOT NULL,
    ativo TINYINT(1) DEFAULT 1,
    
    -- Taxas da operadora/banco
    taxa_percentual DECIMAL(5,2) DEFAULT 0.00 COMMENT 'Taxa em % cobrada pela operadora (ex: 3.99 = 3.99%)',
    taxa_fixa DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Taxa fixa por transação em R$ (ex: 3.50)',
    
    -- Configurações de parcelamento (principalmente para cartão)
    aceita_parcelamento TINYINT(1) DEFAULT 0 COMMENT '1 = permite parcelar',
    parcelas_minimas INT DEFAULT 1 COMMENT 'Mínimo de parcelas permitidas',
    parcelas_maximas INT DEFAULT 12 COMMENT 'Máximo de parcelas permitidas',
    juros_parcelamento DECIMAL(5,2) DEFAULT 0.00 COMMENT 'Juros ao mês em % (ex: 1.99 = 1.99%)',
    parcelas_sem_juros INT DEFAULT 1 COMMENT 'Quantidade de parcelas sem juros',
    
    -- Outros
    dias_compensacao INT DEFAULT 0 COMMENT 'Dias úteis para compensação do pagamento',
    valor_minimo DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Valor mínimo para aceitar esta forma',
    observacoes TEXT COMMENT 'Observações internas sobre esta forma de pagamento',
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (forma_pagamento_id) REFERENCES formas_pagamento(id) ON DELETE CASCADE,
    
    UNIQUE KEY unique_tenant_forma (tenant_id, forma_pagamento_id),
    INDEX idx_tenant (tenant_id),
    INDEX idx_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inserir configurações padrão para todos os tenants existentes (exceto superadmin)
INSERT INTO tenant_formas_pagamento 
(tenant_id, forma_pagamento_id, ativo, taxa_percentual, taxa_fixa, 
 aceita_parcelamento, parcelas_minimas, parcelas_maximas, juros_parcelamento, parcelas_sem_juros, 
 dias_compensacao, valor_minimo)
SELECT 
    t.id as tenant_id,
    fp.id as forma_pagamento_id,
    1 as ativo,
    -- Taxa percentual baseada no tipo
    CASE 
        WHEN fp.nome IN ('PIX', 'Pix') THEN 0.00
        WHEN fp.nome LIKE '%Dinheiro%' THEN 0.00
        WHEN fp.nome LIKE '%Cartão%' OR fp.nome LIKE '%Crédito%' OR fp.nome LIKE '%Débito%' THEN 3.99
        WHEN fp.nome LIKE '%Boleto%' THEN 0.00
        WHEN fp.nome LIKE '%Transfer%' THEN 0.00
        WHEN fp.nome LIKE '%Cheque%' THEN 0.00
        ELSE 0.00
    END as taxa_percentual,
    -- Taxa fixa baseada no tipo
    CASE 
        WHEN fp.nome LIKE '%Boleto%' THEN 3.50
        ELSE 0.00
    END as taxa_fixa,
    -- Aceita parcelamento apenas para cartões
    CASE 
        WHEN fp.nome LIKE '%Cartão%' OR fp.nome LIKE '%Crédito%' THEN 1
        ELSE 0
    END as aceita_parcelamento,
    -- Parcelas mínimas
    1 as parcelas_minimas,
    -- Parcelas máximas
    CASE 
        WHEN fp.nome LIKE '%Cartão%' OR fp.nome LIKE '%Crédito%' THEN 12
        ELSE 1
    END as parcelas_maximas,
    -- Juros de parcelamento
    CASE 
        WHEN fp.nome LIKE '%Cartão%' OR fp.nome LIKE '%Crédito%' THEN 1.99
        ELSE 0.00
    END as juros_parcelamento,
    -- Parcelas sem juros
    CASE 
        WHEN fp.nome LIKE '%Cartão%' OR fp.nome LIKE '%Crédito%' THEN 3
        ELSE 1
    END as parcelas_sem_juros,
    -- Dias de compensação
    CASE 
        WHEN fp.nome IN ('PIX', 'Pix') THEN 0
        WHEN fp.nome LIKE '%Dinheiro%' THEN 0
        WHEN fp.nome LIKE '%Boleto%' THEN 3
        WHEN fp.nome LIKE '%Cartão%' OR fp.nome LIKE '%Crédito%' THEN 30
        WHEN fp.nome LIKE '%Débito%' THEN 1
        WHEN fp.nome LIKE '%Transfer%' THEN 1
        WHEN fp.nome LIKE '%Cheque%' THEN 3
        ELSE 1
    END as dias_compensacao,
    -- Valor mínimo
    CASE 
        WHEN fp.nome LIKE '%Boleto%' THEN 10.00
        ELSE 0.00
    END as valor_minimo
FROM tenants t
CROSS JOIN formas_pagamento fp
WHERE t.id >= 1  -- Incluir TODOS os tenants (incluindo superadmin)
AND fp.ativo = 1
AND NOT EXISTS (
    SELECT 1 FROM tenant_formas_pagamento tfp 
    WHERE tfp.tenant_id = t.id AND tfp.forma_pagamento_id = fp.id
);
