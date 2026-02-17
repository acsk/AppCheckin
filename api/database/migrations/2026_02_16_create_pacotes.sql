-- Migration: Criar estrutura de pacotes (plano família, etc)

CREATE TABLE IF NOT EXISTS pacotes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT NULL,
    valor_total DECIMAL(10,2) NOT NULL,
    qtd_beneficiarios INT NOT NULL,
    plano_id INT NOT NULL,
    plano_ciclo_id INT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tenant (tenant_id),
    INDEX idx_plano (plano_id),
    INDEX idx_ciclo (plano_ciclo_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pacote_contratos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    pacote_id INT NOT NULL,
    pagante_usuario_id INT NOT NULL,
    pagamento_id VARCHAR(100) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pendente',
    valor_total DECIMAL(10,2) NOT NULL,
    data_inicio DATE NULL,
    data_fim DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tenant (tenant_id),
    INDEX idx_pacote (pacote_id),
    INDEX idx_pagante (pagante_usuario_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pacote_beneficiarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    pacote_contrato_id INT NOT NULL,
    aluno_id INT NOT NULL,
    matricula_id INT NULL,
    valor_rateado DECIMAL(10,2) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pendente',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tenant (tenant_id),
    INDEX idx_contrato (pacote_contrato_id),
    INDEX idx_aluno (aluno_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Adicionar vínculo do pacote nas matrículas (se não existir)
SET @exist_pacote_contrato := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'matriculas'
      AND COLUMN_NAME = 'pacote_contrato_id'
);
SET @sql_add_pacote_contrato = IF(@exist_pacote_contrato = 0,
    'ALTER TABLE matriculas ADD COLUMN pacote_contrato_id INT NULL AFTER plano_ciclo_id',
    'SELECT \"pacote_contrato_id já existe\" AS info'
);
PREPARE stmt FROM @sql_add_pacote_contrato;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exist_valor_rateado := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'matriculas'
      AND COLUMN_NAME = 'valor_rateado'
);
SET @sql_add_valor_rateado = IF(@exist_valor_rateado = 0,
    'ALTER TABLE matriculas ADD COLUMN valor_rateado DECIMAL(10,2) NULL AFTER valor',
    'SELECT \"valor_rateado já existe\" AS info'
);
PREPARE stmt FROM @sql_add_valor_rateado;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Adicionar vínculo do pacote nos pagamentos_plano (se não existir)
SET @exist_pacote_contrato_pp := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'pagamentos_plano'
      AND COLUMN_NAME = 'pacote_contrato_id'
);
SET @sql_add_pacote_contrato_pp = IF(@exist_pacote_contrato_pp = 0,
    'ALTER TABLE pagamentos_plano ADD COLUMN pacote_contrato_id INT NULL AFTER matricula_id',
    'SELECT \"pacote_contrato_id em pagamentos_plano já existe\" AS info'
);
PREPARE stmt FROM @sql_add_pacote_contrato_pp;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
