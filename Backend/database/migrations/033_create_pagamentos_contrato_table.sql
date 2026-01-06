-- Criar tabela de pagamentos dos contratos
CREATE TABLE IF NOT EXISTS pagamentos_contrato (
    id INT PRIMARY KEY AUTO_INCREMENT,
    contrato_id INT NOT NULL,
    valor DECIMAL(10,2) NOT NULL,
    data_vencimento DATE NOT NULL,
    data_pagamento DATE NULL,
    status_pagamento_id INT NOT NULL DEFAULT 1,
    forma_pagamento ENUM('cartao', 'pix', 'operadora', 'boleto', 'dinheiro') NOT NULL,
    comprovante VARCHAR(255) NULL COMMENT 'Caminho do arquivo de comprovante',
    observacoes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (contrato_id) REFERENCES tenant_planos_sistema(id) ON DELETE CASCADE,
    FOREIGN KEY (status_pagamento_id) REFERENCES status_pagamento(id),
    
    INDEX idx_contrato (contrato_id),
    INDEX idx_status (status_pagamento_id),
    INDEX idx_vencimento (data_vencimento),
    INDEX idx_pagamento (data_pagamento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
