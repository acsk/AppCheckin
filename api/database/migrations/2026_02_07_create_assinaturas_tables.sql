-- ============================================
-- MIGRATIONS: Tabela Assinaturas Genérica + Matrículas
-- ============================================
-- Data: 2026-02-07
-- Objetivo: Criar tabela assinaturas genérica para qualquer gateway de pagamento
-- NOTA: Sem ENUMs - usando tabelas de lookup para flexibilidade

-- ============================================
-- 1. TABELAS DE LOOKUP (REFERÊNCIA)
-- ============================================

-- Gateways de pagamento
CREATE TABLE IF NOT EXISTS assinatura_gateways (
  id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  codigo VARCHAR(30) NOT NULL UNIQUE,
  nome VARCHAR(50) NOT NULL,
  ativo BOOLEAN DEFAULT TRUE,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO assinatura_gateways (codigo, nome) VALUES
('mercadopago', 'Mercado Pago'),
('stripe', 'Stripe'),
('pagseguro', 'PagSeguro'),
('pagarme', 'Pagar.me'),
('manual', 'Manual');

-- Status de assinatura
CREATE TABLE IF NOT EXISTS assinatura_status (
  id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  codigo VARCHAR(20) NOT NULL UNIQUE,
  nome VARCHAR(50) NOT NULL,
  descricao VARCHAR(100) NULL,
  cor VARCHAR(7) NULL COMMENT 'Cor hex para UI',
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO assinatura_status (codigo, nome, descricao, cor) VALUES
('pendente', 'Pendente', 'Aguardando confirmação de pagamento', '#FFA500'),
('ativa', 'Ativa', 'Assinatura ativa e em dia', '#28A745'),
('pausada', 'Pausada', 'Assinatura temporariamente suspensa', '#6C757D'),
('cancelada', 'Cancelada', 'Assinatura cancelada', '#DC3545'),
('expirada', 'Expirada', 'Assinatura vencida', '#6C757D');

-- Frequências de cobrança
CREATE TABLE IF NOT EXISTS assinatura_frequencias (
  id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  codigo VARCHAR(20) NOT NULL UNIQUE,
  nome VARCHAR(30) NOT NULL,
  dias INT NOT NULL COMMENT 'Quantidade de dias do ciclo',
  meses INT NULL COMMENT 'Quantidade de meses (alternativa)',
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO assinatura_frequencias (codigo, nome, dias, meses) VALUES
('diario', 'Diário', 1, NULL),
('semanal', 'Semanal', 7, NULL),
('quinzenal', 'Quinzenal', 15, NULL),
('mensal', 'Mensal', 30, 1),
('bimestral', 'Bimestral', 60, 2),
('trimestral', 'Trimestral', 90, 3),
('semestral', 'Semestral', 180, 6),
('anual', 'Anual', 365, 12);

-- Métodos de pagamento
CREATE TABLE IF NOT EXISTS metodos_pagamento (
  id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  codigo VARCHAR(30) NOT NULL UNIQUE,
  nome VARCHAR(50) NOT NULL,
  icone VARCHAR(50) NULL,
  ativo BOOLEAN DEFAULT TRUE,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO metodos_pagamento (codigo, nome) VALUES
('credit_card', 'Cartão de Crédito'),
('debit_card', 'Cartão de Débito'),
('pix', 'PIX'),
('boleto', 'Boleto Bancário'),
('account_money', 'Saldo em Conta'),
('transfer', 'Transferência');

-- Tipos de cancelamento
CREATE TABLE IF NOT EXISTS assinatura_cancelamento_tipos (
  id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  codigo VARCHAR(20) NOT NULL UNIQUE,
  nome VARCHAR(50) NOT NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO assinatura_cancelamento_tipos (codigo, nome) VALUES
('usuario', 'Cancelado pelo Usuário'),
('admin', 'Cancelado pelo Administrador'),
('gateway', 'Cancelado pelo Gateway'),
('sistema', 'Cancelado pelo Sistema');

-- ============================================
-- 2. TABELA ASSINATURAS (PRINCIPAL)
-- ============================================

CREATE TABLE IF NOT EXISTS assinaturas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT NOT NULL,
  aluno_id INT NOT NULL,
  matricula_id INT UNIQUE NULL,
  plano_id INT NULL,
  
  -- Gateway (FK)
  gateway_id TINYINT UNSIGNED NOT NULL,
  gateway_assinatura_id VARCHAR(100) NULL COMMENT 'ID no gateway (preapproval_id, etc)',
  gateway_cliente_id VARCHAR(100) NULL,
  
  -- Status (FK)
  status_id TINYINT UNSIGNED NOT NULL DEFAULT 1,
  status_gateway VARCHAR(50) NULL COMMENT 'Status original do gateway',
  
  -- Valores
  valor DECIMAL(10,2) NOT NULL,
  moeda VARCHAR(3) DEFAULT 'BRL',
  
  -- Ciclo (FK)
  frequencia_id TINYINT UNSIGNED NOT NULL DEFAULT 4,
  dia_cobranca TINYINT UNSIGNED NULL,
  
  -- Datas
  data_inicio DATE NOT NULL,
  data_fim DATE NULL,
  proxima_cobranca DATE NULL,
  ultima_cobranca DATE NULL,
  
  -- Pagamento (FK)
  metodo_pagamento_id TINYINT UNSIGNED NULL,
  cartao_ultimos_digitos VARCHAR(4) NULL,
  cartao_bandeira VARCHAR(20) NULL,
  
  -- Controle
  tentativas_cobranca INT DEFAULT 0,
  motivo_cancelamento TEXT NULL,
  cancelado_por_id TINYINT UNSIGNED NULL,
  
  -- Timestamps
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  -- Foreign Keys
  FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  FOREIGN KEY (aluno_id) REFERENCES alunos(id) ON DELETE CASCADE,
  FOREIGN KEY (matricula_id) REFERENCES matriculas(id) ON DELETE SET NULL,
  FOREIGN KEY (plano_id) REFERENCES planos(id) ON DELETE SET NULL,
  FOREIGN KEY (gateway_id) REFERENCES assinatura_gateways(id),
  FOREIGN KEY (status_id) REFERENCES assinatura_status(id),
  FOREIGN KEY (frequencia_id) REFERENCES assinatura_frequencias(id),
  FOREIGN KEY (metodo_pagamento_id) REFERENCES metodos_pagamento(id),
  FOREIGN KEY (cancelado_por_id) REFERENCES assinatura_cancelamento_tipos(id),
  
  -- Índices
  INDEX idx_tenant (tenant_id),
  INDEX idx_aluno (aluno_id),
  INDEX idx_matricula (matricula_id),
  INDEX idx_gateway (gateway_id, gateway_assinatura_id),
  INDEX idx_status (tenant_id, status_id),
  INDEX idx_proxima_cobranca (proxima_cobranca, status_id),
  INDEX idx_aluno_status (aluno_id, status_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 3. VIEW PARA FACILITAR CONSULTAS
-- ============================================

CREATE OR REPLACE VIEW vw_assinaturas AS
SELECT 
  a.id,
  a.tenant_id,
  a.aluno_id,
  a.matricula_id,
  a.plano_id,
  g.codigo as gateway,
  g.nome as gateway_nome,
  a.gateway_assinatura_id,
  s.codigo as status,
  s.nome as status_nome,
  s.cor as status_cor,
  a.valor,
  a.moeda,
  f.codigo as frequencia,
  f.nome as frequencia_nome,
  f.dias as frequencia_dias,
  a.data_inicio,
  a.data_fim,
  a.proxima_cobranca,
  a.ultima_cobranca,
  mp.codigo as metodo_pagamento,
  mp.nome as metodo_pagamento_nome,
  a.cartao_ultimos_digitos,
  a.cartao_bandeira,
  ct.codigo as cancelado_por,
  a.motivo_cancelamento,
  a.criado_em,
  a.atualizado_em
FROM assinaturas a
INNER JOIN assinatura_gateways g ON a.gateway_id = g.id
INNER JOIN assinatura_status s ON a.status_id = s.id
INNER JOIN assinatura_frequencias f ON a.frequencia_id = f.id
LEFT JOIN metodos_pagamento mp ON a.metodo_pagamento_id = mp.id
LEFT JOIN assinatura_cancelamento_tipos ct ON a.cancelado_por_id = ct.id;

-- ============================================
-- 4. MIGRAR DE ASSINATURAS_MERCADOPAGO (SE EXISTIR)
-- ============================================

/*
INSERT INTO assinaturas (
  tenant_id, aluno_id, matricula_id, plano_id,
  gateway_id, gateway_assinatura_id, gateway_cliente_id,
  status_id, status_gateway, valor,
  frequencia_id, data_inicio, proxima_cobranca, ultima_cobranca,
  metodo_pagamento_id, criado_em, atualizado_em
)
SELECT 
  am.tenant_id, am.aluno_id, am.matricula_id, am.plano_id,
  (SELECT id FROM assinatura_gateways WHERE codigo = 'mercadopago'),
  am.preapproval_id, am.payer_id,
  (SELECT id FROM assinatura_status WHERE codigo = 
    CASE am.status 
      WHEN 'authorized' THEN 'ativa'
      WHEN 'pending' THEN 'pendente'
      WHEN 'paused' THEN 'pausada'
      WHEN 'cancelled' THEN 'cancelada'
      ELSE 'pendente'
    END
  ),
  am.status, am.valor,
  (SELECT id FROM assinatura_frequencias WHERE codigo = 'mensal'),
  am.data_inicio, am.proxima_cobranca, am.ultima_cobranca,
  (SELECT id FROM metodos_pagamento WHERE codigo = am.metodo_pagamento),
  am.criado_em, am.atualizado_em
FROM assinaturas_mercadopago am;

-- Após migrar, pode dropar a tabela antiga:
-- DROP TABLE assinaturas_mercadopago;
*/

-- ============================================
-- MAPEAMENTO DE STATUS POR GATEWAY
-- ============================================
-- 
-- | Status Interno | Mercado Pago | Stripe     | PagSeguro |
-- |----------------|--------------|------------|-----------|
-- | pendente       | pending      | incomplete | pending   |
-- | ativa          | authorized   | active     | active    |
-- | pausada        | paused       | paused     | suspended |
-- | cancelada      | cancelled    | canceled   | canceled  |
-- | expirada       | -            | past_due   | expired   |
--
-- ============================================
-- FIM DA MIGRATION
-- ============================================
