-- Criar tabela de modalidades por tenant
CREATE TABLE IF NOT EXISTS modalidades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT NULL,
    valor_mensalidade DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    cor VARCHAR(7) NULL COMMENT 'Cor em hexadecimal para identificação visual',
    icone VARCHAR(50) NULL COMMENT 'Nome do ícone para exibição',
    ativo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    INDEX idx_tenant (tenant_id),
    INDEX idx_ativo (ativo),
    UNIQUE KEY unique_tenant_modalidade (tenant_id, nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inserir modalidades padrão para academias existentes
INSERT INTO modalidades (tenant_id, nome, descricao, valor_mensalidade, cor, icone, ativo)
SELECT 
    id as tenant_id,
    'Musculação' as nome,
    'Treinamento de força e condicionamento físico' as descricao,
    0.00 as valor_mensalidade,
    '#f97316' as cor,
    'activity' as icone,
    1 as ativo
FROM tenants
WHERE ativo = 1;

SELECT 'Migration 039 executada com sucesso! Tabela modalidades criada.' as status;
