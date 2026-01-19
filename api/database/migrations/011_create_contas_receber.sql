-- Tabela de Contas a Receber
CREATE TABLE IF NOT EXISTS contas_receber (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    usuario_id INT NOT NULL,
    plano_id INT NOT NULL,
    historico_plano_id INT NULL COMMENT 'Referência ao histórico que gerou esta conta',
    valor DECIMAL(10,2) NOT NULL,
    data_vencimento DATE NOT NULL,
    data_pagamento DATE NULL,
    status ENUM('pendente', 'pago', 'vencido', 'cancelado') DEFAULT 'pendente',
    forma_pagamento VARCHAR(50) NULL COMMENT 'dinheiro, cartao_credito, cartao_debito, pix, transferencia',
    observacoes TEXT NULL,
    referencia_mes VARCHAR(7) NULL COMMENT 'Formato YYYY-MM para controle mensal',
    recorrente BOOLEAN DEFAULT FALSE COMMENT 'Se true, gera próxima parcela ao dar baixa',
    intervalo_dias INT NULL COMMENT 'Dias para gerar próxima parcela (30, 90, 180, 365)',
    proxima_conta_id INT NULL COMMENT 'ID da próxima conta gerada automaticamente',
    conta_origem_id INT NULL COMMENT 'ID da conta que originou esta (para rastreamento)',
    criado_por INT NULL COMMENT 'ID do admin que criou',
    baixa_por INT NULL COMMENT 'ID do admin que deu baixa',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (plano_id) REFERENCES planos(id) ON DELETE RESTRICT,
    FOREIGN KEY (historico_plano_id) REFERENCES historico_planos(id) ON DELETE SET NULL,
    FOREIGN KEY (proxima_conta_id) REFERENCES contas_receber(id) ON DELETE SET NULL,
    FOREIGN KEY (conta_origem_id) REFERENCES contas_receber(id) ON DELETE SET NULL,
    FOREIGN KEY (criado_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    FOREIGN KEY (baixa_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_tenant_usuario (tenant_id, usuario_id),
    INDEX idx_status (status),
    INDEX idx_data_vencimento (data_vencimento),
    INDEX idx_plano (plano_id),
    INDEX idx_referencia (referencia_mes)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Trigger para atualizar status automaticamente quando vencido
DELIMITER //
CREATE TRIGGER atualizar_status_vencido 
BEFORE UPDATE ON contas_receber
FOR EACH ROW
BEGIN
    IF NEW.status = 'pendente' AND NEW.data_vencimento < CURDATE() THEN
        SET NEW.status = 'vencido';
    END IF;
END//
DELIMITER ;

