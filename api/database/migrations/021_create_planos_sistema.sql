-- Criar tabela de Planos do Sistema (planos que as academias contratam)
-- Diferente da tabela 'planos' que são planos dos alunos dentro de cada academia

CREATE TABLE IF NOT EXISTS planos_sistema (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL COMMENT 'Nome do plano (ex: Starter, Professional, Enterprise)',
    descricao TEXT COMMENT 'Descrição detalhada do plano',
    valor DECIMAL(10,2) NOT NULL COMMENT 'Valor mensal do plano',
    duracao_dias INT NOT NULL DEFAULT 30 COMMENT 'Duração em dias (30, 90, 365, etc)',
    max_alunos INT NULL COMMENT 'Capacidade máxima de alunos (NULL = ilimitado)',
    max_admins INT NULL DEFAULT 1 COMMENT 'Número máximo de administradores',
    features JSON NULL COMMENT 'Recursos inclusos no plano em formato JSON',
    ativo BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'Plano ativo para venda',
    atual BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'Plano atual (disponível para novos contratos)',
    ordem INT NULL DEFAULT 0 COMMENT 'Ordem de exibição',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_disponiveis (atual, ativo),
    INDEX idx_ordem (ordem)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Planos de assinatura do sistema que as academias contratam';

-- Inserir planos padrão do sistema
INSERT INTO planos_sistema (nome, descricao, valor, duracao_dias, max_alunos, max_admins, ordem, features) VALUES
('Starter', 'Plano inicial para pequenas academias', 99.00, 30, 50, 1, 1, '{"checkins": true, "relatorios_basicos": true, "app_mobile": true}'),
('Professional', 'Plano completo para academias em crescimento', 199.00, 30, 150, 3, 2, '{"checkins": true, "relatorios_avancados": true, "app_mobile": true, "turmas": true, "multi_admin": true}'),
('Enterprise', 'Plano ilimitado para grandes academias', 399.00, 30, NULL, 10, 3, '{"checkins": true, "relatorios_avancados": true, "app_mobile": true, "turmas": true, "multi_admin": true, "api_access": true, "suporte_prioritario": true}');

