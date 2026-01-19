-- Tabela de Histórico de Mudanças de Planos
CREATE TABLE IF NOT EXISTS historico_planos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    plano_anterior_id INT NULL,
    plano_novo_id INT NULL,
    data_inicio DATE NOT NULL,
    data_vencimento DATE NULL,
    valor_pago DECIMAL(10,2) NULL,
    motivo VARCHAR(100) NULL COMMENT 'novo, renovacao, upgrade, downgrade, cancelamento',
    observacoes TEXT NULL,
    criado_por INT NULL COMMENT 'ID do admin que fez a alteração',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (plano_anterior_id) REFERENCES planos(id) ON DELETE SET NULL,
    FOREIGN KEY (plano_novo_id) REFERENCES planos(id) ON DELETE SET NULL,
    FOREIGN KEY (criado_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_usuario_historico (usuario_id),
    INDEX idx_plano_anterior (plano_anterior_id),
    INDEX idx_plano_novo (plano_novo_id),
    INDEX idx_data_inicio (data_inicio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Adicionar comentários nas colunas
ALTER TABLE historico_planos 
MODIFY COLUMN motivo VARCHAR(100) NULL 
COMMENT 'Tipos: novo (primeiro plano), renovacao (mesmo plano), upgrade (plano melhor), downgrade (plano menor), cancelamento (removeu plano)';

