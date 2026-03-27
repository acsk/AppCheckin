-- Criar tabela de status de créditos do aluno
CREATE TABLE IF NOT EXISTS status_creditos_aluno (
    id INT PRIMARY KEY AUTO_INCREMENT,
    codigo VARCHAR(20) NOT NULL UNIQUE COMMENT 'Código identificador: ativo, utilizado, cancelado',
    nome VARCHAR(50) NOT NULL COMMENT 'Nome de exibição',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inserir status padrão
INSERT INTO status_creditos_aluno (id, codigo, nome) VALUES
    (1, 'ativo', 'Ativo'),
    (2, 'utilizado', 'Utilizado'),
    (3, 'cancelado', 'Cancelado');

-- Criar tabela de créditos do aluno
-- Registra créditos gerados (ex: ao alterar plano, abatendo pagamento anterior)
-- e permite rastreabilidade de quanto foi utilizado e por qual pagamento

CREATE TABLE IF NOT EXISTS creditos_aluno (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tenant_id INT NOT NULL,
    aluno_id INT NOT NULL,
    matricula_origem_id INT NULL COMMENT 'Matrícula que gerou o crédito',
    pagamento_origem_id INT NULL COMMENT 'Pagamento que gerou o crédito',
    valor DECIMAL(10,2) NOT NULL COMMENT 'Valor total do crédito',
    valor_utilizado DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Valor já consumido',
    motivo TEXT NULL COMMENT 'Descrição/motivo do crédito',
    status_credito_id INT NOT NULL DEFAULT 1 COMMENT 'FK para status_creditos_aluno',
    criado_por INT NULL COMMENT 'Admin que gerou o crédito',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_tenant_aluno (tenant_id, aluno_id),
    INDEX idx_status (status_credito_id),
    INDEX idx_matricula_origem (matricula_origem_id),

    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (aluno_id) REFERENCES alunos(id) ON DELETE CASCADE,
    FOREIGN KEY (matricula_origem_id) REFERENCES matriculas(id) ON DELETE SET NULL,
    FOREIGN KEY (pagamento_origem_id) REFERENCES pagamentos_plano(id) ON DELETE SET NULL,
    FOREIGN KEY (criado_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    FOREIGN KEY (status_credito_id) REFERENCES status_creditos_aluno(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Adicionar colunas de desconto e crédito em pagamentos_plano
-- Verificar e adicionar colunas que podem não existir
SET @dbname = DATABASE();

-- Adicionar desconto se não existir
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'pagamentos_plano' AND COLUMN_NAME = 'desconto');
SET @sql = IF(@col_exists = 0, "ALTER TABLE pagamentos_plano ADD COLUMN desconto DECIMAL(10,2) NULL DEFAULT 0.00 COMMENT 'Valor de desconto aplicado' AFTER observacoes", 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Adicionar motivo_desconto se não existir
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'pagamentos_plano' AND COLUMN_NAME = 'motivo_desconto');
SET @sql = IF(@col_exists = 0, "ALTER TABLE pagamentos_plano ADD COLUMN motivo_desconto VARCHAR(255) NULL COMMENT 'Motivo do desconto' AFTER desconto", 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Adicionar credito_id se não existir
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'pagamentos_plano' AND COLUMN_NAME = 'credito_id');
SET @sql = IF(@col_exists = 0, "ALTER TABLE pagamentos_plano ADD COLUMN credito_id INT NULL COMMENT 'Crédito aplicado neste pagamento' AFTER motivo_desconto", 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Adicionar credito_aplicado se não existir
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'pagamentos_plano' AND COLUMN_NAME = 'credito_aplicado');
SET @sql = IF(@col_exists = 0, "ALTER TABLE pagamentos_plano ADD COLUMN credito_aplicado DECIMAL(10,2) NULL COMMENT 'Valor do crédito aplicado neste pagamento' AFTER credito_id", 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Adicionar FK de credito_id para creditos_aluno (ignora se já existir)
SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'pagamentos_plano' AND CONSTRAINT_NAME = 'fk_pagamentos_plano_credito');
SET @sql = IF(@fk_exists = 0, 'ALTER TABLE pagamentos_plano ADD CONSTRAINT fk_pagamentos_plano_credito FOREIGN KEY (credito_id) REFERENCES creditos_aluno(id) ON DELETE SET NULL', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
