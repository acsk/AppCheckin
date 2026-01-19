-- Criar tabela de pagamentos das matrículas/planos dos alunos
-- Similar a pagamentos_contrato, mas para a relação Tenant Admin -> Aluno

CREATE TABLE IF NOT EXISTS pagamentos_plano (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tenant_id INT NOT NULL,
    matricula_id INT NOT NULL,
    usuario_id INT NOT NULL COMMENT 'ID do aluno',
    plano_id INT NOT NULL,
    valor DECIMAL(10,2) NOT NULL,
    data_vencimento DATE NOT NULL,
    data_pagamento DATE NULL,
    status_pagamento_id INT NOT NULL DEFAULT 1 COMMENT '1=Aguardando, 2=Pago, 3=Atrasado, 4=Cancelado',
    forma_pagamento_id INT NULL,
    comprovante VARCHAR(255) NULL COMMENT 'Caminho do arquivo de comprovante',
    observacoes TEXT NULL,
    criado_por INT NULL COMMENT 'Admin que criou',
    baixado_por INT NULL COMMENT 'Admin que deu baixa',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (matricula_id) REFERENCES matriculas(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (plano_id) REFERENCES planos(id) ON DELETE RESTRICT,
    FOREIGN KEY (status_pagamento_id) REFERENCES status_pagamento(id),
    FOREIGN KEY (forma_pagamento_id) REFERENCES formas_pagamento(id),
    FOREIGN KEY (criado_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    FOREIGN KEY (baixado_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    
    INDEX idx_tenant_matricula (tenant_id, matricula_id),
    INDEX idx_tenant_usuario (tenant_id, usuario_id),
    INDEX idx_matricula (matricula_id),
    INDEX idx_usuario (usuario_id),
    INDEX idx_plano (plano_id),
    INDEX idx_status (status_pagamento_id),
    INDEX idx_vencimento (data_vencimento),
    INDEX idx_pagamento (data_pagamento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Índice composto para consultas de pagamentos por tenant e status
CREATE INDEX idx_tenant_status ON pagamentos_plano(tenant_id, status_pagamento_id);

-- Índice para consultas de pagamentos vencidos
CREATE INDEX idx_tenant_vencimento_status ON pagamentos_plano(tenant_id, data_vencimento, status_pagamento_id);
