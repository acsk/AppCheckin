-- Criar tabela de tipos de baixa de pagamentos
CREATE TABLE IF NOT EXISTS tipos_baixa (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nome VARCHAR(50) NOT NULL UNIQUE COMMENT 'Nome do tipo de baixa: Manual, Automática, etc.',
    descricao VARCHAR(255) NULL COMMENT 'Descrição detalhada do tipo de baixa',
    ativo BOOLEAN DEFAULT TRUE COMMENT 'Se o tipo está ativo para uso',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_nome (nome),
    INDEX idx_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Popular com tipos iniciais
INSERT INTO tipos_baixa (id, nome, descricao, ativo) VALUES
(1, 'Manual', 'Baixa realizada manualmente pelo administrador do sistema', TRUE),
(2, 'Automática', 'Baixa realizada automaticamente pelo sistema', TRUE),
(3, 'Importação', 'Baixa realizada através de importação de dados', TRUE),
(4, 'Integração', 'Baixa realizada através de integração com sistema externo', TRUE);
