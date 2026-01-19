-- Criar tabela de status de pagamento
CREATE TABLE IF NOT EXISTS status_pagamento (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nome VARCHAR(50) NOT NULL UNIQUE,
    descricao TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Inserir status padrão
INSERT INTO status_pagamento (id, nome, descricao) VALUES
(1, 'Aguardando', 'Pagamento aguardando confirmação'),
(2, 'Pago', 'Pagamento confirmado'),
(3, 'Atrasado', 'Pagamento em atraso'),
(4, 'Cancelado', 'Pagamento cancelado');
