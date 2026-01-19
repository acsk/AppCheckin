-- Criar tabela de Status de Contratos
-- Esta tabela normaliza os status dos contratos da tabela tenant_planos_sistema

CREATE TABLE IF NOT EXISTS status_contrato (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(50) NOT NULL UNIQUE COMMENT 'Nome do status',
    descricao VARCHAR(255) NULL COMMENT 'Descrição detalhada do status',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inserir os status padrão do sistema
INSERT INTO status_contrato (id, nome, descricao) VALUES
(1, 'Ativo', 'Contrato ativo e em vigência'),
(2, 'Pendente', 'Contrato aguardando aprovação ou pagamento'),
(3, 'Cancelado', 'Contrato cancelado pelo cliente ou administrador');

-- Comentário na tabela
ALTER TABLE status_contrato COMMENT = 'Status possíveis para contratos de planos (tenant_planos_sistema)';
