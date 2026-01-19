-- Tabela de planos
CREATE TABLE IF NOT EXISTS planos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT,
    valor DECIMAL(10,2) NOT NULL,
    duracao_dias INT NOT NULL COMMENT 'Duração em dias (30, 90, 365, etc)',
    checkins_mensais INT NULL COMMENT 'Limite de checkins por mês (NULL = ilimitado)',
    ativo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    INDEX idx_tenant_planos (tenant_id),
    INDEX idx_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Adicionar campo plano_id em usuarios
ALTER TABLE usuarios ADD COLUMN plano_id INT NULL AFTER role;
ALTER TABLE usuarios ADD COLUMN data_vencimento_plano DATE NULL AFTER plano_id;
ALTER TABLE usuarios ADD FOREIGN KEY (plano_id) REFERENCES planos(id) ON DELETE SET NULL;

-- Inserir planos padrão
INSERT INTO planos (tenant_id, nome, descricao, valor, duracao_dias, checkins_mensais) VALUES
(1, 'Mensal Básico', 'Plano mensal com 12 checkins', 99.90, 30, 12),
(1, 'Mensal Ilimitado', 'Plano mensal com checkins ilimitados', 149.90, 30, NULL),
(1, 'Trimestral', 'Plano trimestral com checkins ilimitados', 399.90, 90, NULL),
(1, 'Anual', 'Plano anual com checkins ilimitados e desconto', 1299.90, 365, NULL);
