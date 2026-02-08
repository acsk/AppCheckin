-- Migration: Criar tabelas para ciclos de planos e assinaturas recorrentes
-- 
-- Esta migration cria:
-- 1. assinatura_frequencias - Frequências de assinatura (mensal, trimestral, semestral, anual)
-- 2. plano_ciclos - Diferentes ciclos de pagamento para cada plano
-- 3. assinaturas_mercadopago - Assinaturas recorrentes do MercadoPago
-- 4. Colunas extras em matriculas para vincular ciclo e tipo de cobrança
--
-- Execução:
-- mysql -u user -p database < 2026_02_07_001_create_plano_ciclos_table.sql

-- =====================================================================
-- 1. TABELA assinatura_frequencias (tabela de referência)
-- =====================================================================

CREATE TABLE IF NOT EXISTS assinatura_frequencias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(50) NOT NULL COMMENT 'Mensal, Trimestral, Semestral, Anual',
    codigo VARCHAR(20) NOT NULL UNIQUE COMMENT 'mensal, trimestral, semestral, anual',
    meses INT NOT NULL DEFAULT 1 COMMENT 'Quantidade de meses do ciclo',
    ordem INT DEFAULT 1 COMMENT 'Ordem de exibição',
    ativo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_assinatura_frequencias_codigo (codigo),
    INDEX idx_assinatura_frequencias_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Frequências de assinatura (mensal, trimestral, semestral, anual)';

-- Inserir frequências padrão
INSERT INTO assinatura_frequencias (nome, codigo, meses, ordem) VALUES 
    ('Mensal', 'mensal', 1, 1),
    ('Bimestral', 'bimestral', 2, 2),
    ('Trimestral', 'trimestral', 3, 3),
    ('Quadrimestral', 'quadrimestral', 4, 4),
    ('Semestral', 'semestral', 6, 5),
    ('Anual', 'anual', 12, 6);

-- =====================================================================
-- 2. TABELA plano_ciclos
-- =====================================================================

CREATE TABLE IF NOT EXISTS plano_ciclos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    plano_id INT NOT NULL,
    assinatura_frequencia_id INT NOT NULL COMMENT 'FK para assinatura_frequencias',
    meses INT NOT NULL DEFAULT 1 COMMENT 'Copiado de assinatura_frequencias para cálculo',
    valor DECIMAL(10,2) NOT NULL COMMENT 'Valor total do ciclo',
    valor_mensal_equivalente DECIMAL(10,2) GENERATED ALWAYS AS (valor / meses) STORED COMMENT 'Valor mensal equivalente calculado',
    desconto_percentual DECIMAL(5,2) DEFAULT 0 COMMENT 'Percentual de desconto em relação ao mensal',
    permite_recorrencia TINYINT(1) DEFAULT 1 COMMENT 'Se permite cobrança recorrente (assinatura)',
    ativo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_plano_ciclos_tenant (tenant_id),
    INDEX idx_plano_ciclos_plano (plano_id),
    INDEX idx_plano_ciclos_frequencia (assinatura_frequencia_id),
    INDEX idx_plano_ciclos_ativo (ativo),
    
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (plano_id) REFERENCES planos(id) ON DELETE CASCADE,
    FOREIGN KEY (assinatura_frequencia_id) REFERENCES assinatura_frequencias(id) ON DELETE RESTRICT,
    
    UNIQUE KEY uk_plano_frequencia (plano_id, assinatura_frequencia_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Ciclos de pagamento dos planos';

-- =====================================================================
-- 2. TABELA assinaturas_mercadopago
-- =====================================================================

CREATE TABLE IF NOT EXISTS assinaturas_mercadopago (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    matricula_id INT NOT NULL,
    aluno_id INT NOT NULL,
    plano_ciclo_id INT NULL COMMENT 'Ciclo contratado',
    
    -- Dados do MercadoPago
    mp_preapproval_id VARCHAR(100) NULL COMMENT 'ID da assinatura no MP',
    mp_plan_id VARCHAR(100) NULL COMMENT 'ID do plano no MP (se usar plano pré-criado)',
    mp_payer_id VARCHAR(100) NULL COMMENT 'ID do pagador no MP',
    
    -- Status e valores
    status ENUM('pending', 'authorized', 'paused', 'cancelled', 'finished') DEFAULT 'pending',
    valor DECIMAL(10,2) NOT NULL,
    moeda VARCHAR(3) DEFAULT 'BRL',
    
    -- Datas de cobrança
    dia_cobranca INT DEFAULT 1 COMMENT 'Dia do mês para cobrança',
    data_inicio DATE NOT NULL,
    data_fim DATE NULL COMMENT 'Data de término (se não for indefinido)',
    proxima_cobranca DATE NULL,
    ultima_cobranca DATE NULL,
    
    -- Controle
    tentativas_falha INT DEFAULT 0,
    motivo_cancelamento TEXT NULL,
    cancelado_por INT NULL,
    data_cancelamento DATETIME NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_assinaturas_tenant (tenant_id),
    INDEX idx_assinaturas_matricula (matricula_id),
    INDEX idx_assinaturas_aluno (aluno_id),
    INDEX idx_assinaturas_status (status),
    INDEX idx_assinaturas_mp_id (mp_preapproval_id),
    INDEX idx_assinaturas_proxima_cobranca (proxima_cobranca),
    
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (matricula_id) REFERENCES matriculas(id) ON DELETE CASCADE,
    FOREIGN KEY (aluno_id) REFERENCES alunos(id) ON DELETE CASCADE,
    FOREIGN KEY (plano_ciclo_id) REFERENCES plano_ciclos(id) ON DELETE SET NULL,
    FOREIGN KEY (cancelado_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Assinaturas recorrentes do MercadoPago';

-- =====================================================================
-- 3. ADICIONAR COLUNAS EM matriculas
-- =====================================================================

-- Coluna para vincular ao ciclo do plano
-- Nota: Execute apenas se a coluna não existir
-- SHOW COLUMNS FROM matriculas LIKE 'plano_ciclo_id'; -- para verificar
ALTER TABLE matriculas 
ADD COLUMN plano_ciclo_id INT NULL AFTER plano_id;

-- Coluna para tipo de cobrança (avulso ou recorrente)
-- SHOW COLUMNS FROM matriculas LIKE 'tipo_cobranca'; -- para verificar
ALTER TABLE matriculas 
ADD COLUMN tipo_cobranca ENUM('avulso', 'recorrente') DEFAULT 'avulso' AFTER plano_ciclo_id;

-- Foreign key (execute após adicionar a coluna)
ALTER TABLE matriculas 
ADD CONSTRAINT fk_matriculas_plano_ciclo 
FOREIGN KEY (plano_ciclo_id) REFERENCES plano_ciclos(id) ON DELETE SET NULL;

-- =====================================================================
-- 4. ÍNDICES ADICIONAIS
-- =====================================================================

-- Índice para buscar assinaturas ativas por tenant
CREATE INDEX idx_assinaturas_tenant_status ON assinaturas_mercadopago(tenant_id, status);

-- Índice para buscar ciclos ativos por plano
CREATE INDEX idx_plano_ciclos_plano_ativo ON plano_ciclos(plano_id, ativo);
