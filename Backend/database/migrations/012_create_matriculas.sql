-- Migration: Criar tabela de matrículas
-- Separa o conceito de cadastro de aluno da associação com planos

CREATE TABLE IF NOT EXISTS matriculas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tenant_id INT NOT NULL,
    usuario_id INT NOT NULL,
    plano_id INT NOT NULL,
    
    -- Datas e valores
    data_matricula DATE NOT NULL,
    data_inicio DATE NOT NULL,
    data_vencimento DATE NOT NULL,
    valor DECIMAL(10,2) NOT NULL,
    
    -- Status e controle
    status ENUM('ativa', 'vencida', 'cancelada', 'finalizada') DEFAULT 'ativa',
    motivo ENUM('nova', 'renovacao', 'upgrade', 'downgrade') NOT NULL DEFAULT 'nova',
    
    -- Histórico (para upgrades/downgrades)
    matricula_anterior_id INT NULL,
    plano_anterior_id INT NULL,
    
    -- Observações e auditoria
    observacoes TEXT NULL,
    criado_por INT NULL,
    cancelado_por INT NULL,
    data_cancelamento DATE NULL,
    motivo_cancelamento TEXT NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Índices
    INDEX idx_tenant_usuario (tenant_id, usuario_id),
    INDEX idx_status (status),
    INDEX idx_data_vencimento (data_vencimento),
    INDEX idx_plano (plano_id),
    
    -- Foreign keys
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (plano_id) REFERENCES planos(id) ON DELETE RESTRICT,
    FOREIGN KEY (matricula_anterior_id) REFERENCES matriculas(id) ON DELETE SET NULL,
    FOREIGN KEY (plano_anterior_id) REFERENCES planos(id) ON DELETE SET NULL,
    FOREIGN KEY (criado_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    FOREIGN KEY (cancelado_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Trigger para atualizar status de matrícula vencida
DELIMITER $$
CREATE TRIGGER update_matricula_vencida
BEFORE UPDATE ON matriculas
FOR EACH ROW
BEGIN
    IF NEW.data_vencimento < CURDATE() AND NEW.status = 'ativa' THEN
        SET NEW.status = 'vencida';
    END IF;
END$$
DELIMITER ;

-- Comentários
ALTER TABLE matriculas 
    COMMENT = 'Registra as matrículas dos alunos nos planos - separado do cadastro básico';
