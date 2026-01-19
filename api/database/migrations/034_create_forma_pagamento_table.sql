-- Criar tabela de formas de pagamento
CREATE TABLE IF NOT EXISTS forma_pagamento (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(50) NOT NULL UNIQUE,
    descricao VARCHAR(255),
    ativo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inserir formas de pagamento
INSERT INTO forma_pagamento (id, nome, descricao) VALUES
(1, 'PIX', 'Pagamento via PIX'),
(2, 'Cartão', 'Cartão de crédito ou débito'),
(3, 'Boleto', 'Boleto bancário'),
(4, 'Dinheiro', 'Pagamento em dinheiro'),
(5, 'Operadora', 'Pagamento via operadora de cartões')
ON DUPLICATE KEY UPDATE nome = VALUES(nome);

-- Adicionar coluna forma_pagamento_id na tabela pagamentos_contrato
ALTER TABLE pagamentos_contrato 
ADD COLUMN forma_pagamento_id INT NULL AFTER status_pagamento_id,
ADD INDEX idx_forma_pagamento (forma_pagamento_id);

-- Migrar dados existentes
UPDATE pagamentos_contrato SET forma_pagamento_id = 
    CASE 
        WHEN forma_pagamento = 'pix' THEN 1
        WHEN forma_pagamento = 'cartao' THEN 2
        WHEN forma_pagamento = 'boleto' THEN 3
        WHEN forma_pagamento = 'dinheiro' THEN 4
        WHEN forma_pagamento = 'operadora' THEN 5
        ELSE 1
    END
WHERE forma_pagamento_id IS NULL;

-- Adicionar coluna forma_pagamento_id na tabela tenant_planos_sistema
ALTER TABLE tenant_planos_sistema 
ADD COLUMN forma_pagamento_id INT NULL AFTER status_id,
ADD INDEX idx_forma_pagamento_contrato (forma_pagamento_id);

-- Migrar dados existentes dos contratos
UPDATE tenant_planos_sistema SET forma_pagamento_id = 
    CASE 
        WHEN forma_pagamento = 'pix' THEN 1
        WHEN forma_pagamento = 'cartao' THEN 2
        WHEN forma_pagamento = 'boleto' THEN 3
        WHEN forma_pagamento = 'dinheiro' THEN 4
        WHEN forma_pagamento = 'operadora' THEN 5
        ELSE 1
    END
WHERE forma_pagamento_id IS NULL;

-- Adicionar foreign keys
ALTER TABLE pagamentos_contrato 
ADD CONSTRAINT fk_pagamento_forma_pagamento 
FOREIGN KEY (forma_pagamento_id) REFERENCES forma_pagamento(id);

ALTER TABLE tenant_planos_sistema 
ADD CONSTRAINT fk_contrato_forma_pagamento 
FOREIGN KEY (forma_pagamento_id) REFERENCES forma_pagamento(id);

SELECT 'Migration 034 executada com sucesso!' as status;
